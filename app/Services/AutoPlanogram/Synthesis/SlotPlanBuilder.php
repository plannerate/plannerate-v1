<?php

namespace App\Services\AutoPlanogram\Synthesis;

use App\Enums\CategoryRole;
use App\Models\Category;
use App\Services\AutoPlanogram\DTO\CategoryAbcSummary;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\SlotPlanEntry;
use App\Services\AutoPlanogram\ShelfZoneResolver;
use Illuminate\Support\Collection;

/**
 * Produz o plano de slots do template sintetizado: para cada (módulo, prateleira),
 * qual subcategoria ocupa, com min_facings e visual_criteria.
 *
 * Algoritmo (block-partition):
 * 1. Ordenar subcategorias: hot-preferring primeiro (destino/impulso), por ABC e giro.
 * 2. Construir slots ordenados por zona dentro de cada módulo (hot first) → blocagem vertical.
 * 3. Particionar slots em blocos contíguos, um por subcategoria.
 *    Como hot subcats vêm primeiro E hot slots vêm primeiro, hot subcats aterrissam naturalmente
 *    em hot slots quando as proporções são compatíveis.
 *
 * ABC é SEMPRE o primeiro critério visual (score_abc desc) — não é configurável.
 */
class SlotPlanBuilder
{
    /**
     * Peso relativo de cada classe ABC para derivar min_facings.
     * Escalados para o intervalo [minFacings, maxFacings] no momento de uso.
     */
    private const ABC_FACING_WEIGHT = ['A' => 3, 'B' => 2, 'C' => 1, null => 2];

    /**
     * Papéis que preferem zona quente (eye/hand).
     */
    private const HOT_ROLES = [
        CategoryRole::Destino,
        CategoryRole::Impulso,
        CategoryRole::Sazonal,
    ];

    /**
     * Constrói o plano de slots para a gôndola.
     *
     * @param  Collection<int, array{category: Category, summary: CategoryAbcSummary, role: CategoryRole}>  $subcategories
     *                                                                                                                      Filhas elegíveis da categoria selecionada, já com papel e resumo ABC calculados.
     *                                                                                                                      Coleção vazia = categoria selecionada é folha → um único bloco cobre tudo.
     * @return list<SlotPlanEntry>
     */
    public function build(
        Category $selectedCategory,
        Collection $subcategories,
        int $numModules,
        int $shelvesPerModule,
        PlacementSettings $settings,
    ): array {
        $orderedSlots = $this->buildOrderedSlots($numModules, $shelvesPerModule);

        if ($subcategories->isEmpty()) {
            return $this->buildLeafPlan($selectedCategory, $orderedSlots, $settings);
        }

        $sorted = $this->sortSubcategories($subcategories);

        return $this->partitionIntoBlocks($sorted, $orderedSlots, $settings);
    }

    /**
     * Caso folha: preenche todos os slots com a própria categoria selecionada.
     *
     * @param  list<array{module: int, shelf_order: int, zone: 'hot'|'cold'|'neutral'}>  $orderedSlots
     * @return list<SlotPlanEntry>
     */
    private function buildLeafPlan(
        Category $selectedCategory,
        array $orderedSlots,
        PlacementSettings $settings,
    ): array {
        return array_map(fn (array $slot): SlotPlanEntry => new SlotPlanEntry(
            categoryId: $selectedCategory->id,
            moduleNumber: $slot['module'],
            shelfOrder: $slot['shelf_order'],
            minFacings: $settings->minFacings,
            visualCriteria: $this->buildVisualCriteria(),
            zone: $slot['zone'],
            roleOverride: $selectedCategory->role,
            maxFacings: $settings->maxFacings > 0 ? $settings->maxFacings : null,
            facingExpansion: $settings->facingExpansion,
            useTargetStock: $settings->useTargetStock,
            spaceFallback: $settings->spaceFallback,
            maxSharePerSku: $settings->maxSharePerSku,
            maxSharePerBrand: $settings->maxSharePerBrand,
            maxSharePerSubcategory: $settings->maxSharePerSubcategory,
        ), $orderedSlots);
    }

    /**
     * Ordena subcategorias: hot-preferring primeiro, depois cold; por ABC e giro dentro de cada grupo.
     *
     * @param  Collection<int, array{category: Category, summary: CategoryAbcSummary, role: CategoryRole}>  $subcategories
     * @return Collection<int, array{category: Category, summary: CategoryAbcSummary, role: CategoryRole}>
     */
    private function sortSubcategories(Collection $subcategories): Collection
    {
        return $subcategories->sortBy([
            fn ($item) => $this->prefersHot($item['role']) ? 0 : 1,
            fn ($item) => match ($item['summary']->dominantAbcClass) {
                'A' => 0,
                'C' => 2,
                default => 1,
            },
            fn ($item) => -$item['summary']->totalQuantity,
        ])->values();
    }

    /**
     * Gera todos os pares (módulo, prateleira) em ordem de prioridade:
     * dentro de cada módulo, hot shelves primeiro (blocagem vertical);
     * módulos em sequência (1, 2, …, N).
     *
     * @return list<array{module: int, shelf_order: int, zone: 'hot'|'cold'|'neutral'}>
     */
    private function buildOrderedSlots(int $numModules, int $shelvesPerModule): array
    {
        $slots = [];

        for ($mod = 1; $mod <= $numModules; $mod++) {
            $moduleSlots = [];

            for ($shelfOrder = 1; $shelfOrder <= $shelvesPerModule; $shelfOrder++) {
                $position = $shelvesPerModule - $shelfOrder; // 0=topo, N-1=chão
                $zone = ShelfZoneResolver::resolve($position, $shelvesPerModule);
                // hot=0 vem antes de cold=1 dentro do módulo
                $zonePriority = $zone === 'hot' ? 0 : 1;

                $moduleSlots[] = ['module' => $mod, 'shelf_order' => $shelfOrder, 'zone' => $zone, 'zone_priority' => $zonePriority];
            }

            usort($moduleSlots, fn ($a, $b) => $a['zone_priority'] <=> $b['zone_priority']);
            $slots = array_merge($slots, $moduleSlots);
        }

        return $slots;
    }

    /**
     * Particiona slots em blocos contíguos — um bloco por subcategoria.
     * Como hot subcats estão no topo da lista E hot slots estão no início da sequência,
     * eles se encontram naturalmente quando as proporções permitem.
     *
     * @param  Collection<int, array{category: Category, summary: CategoryAbcSummary, role: CategoryRole}>  $sorted
     * @param  list<array{module: int, shelf_order: int, zone: string, zone_priority: int}>  $orderedSlots
     * @return list<SlotPlanEntry>
     */
    private function partitionIntoBlocks(Collection $sorted, array $orderedSlots, PlacementSettings $settings): array
    {
        $totalSlots = count($orderedSlots);
        $numSubcats = $sorted->count();
        $entries = [];

        foreach ($sorted as $i => $item) {
            $blockStart = (int) floor($totalSlots * $i / $numSubcats);
            $blockEnd = (int) floor($totalSlots * ($i + 1) / $numSubcats) - 1;

            $minFacings = $this->deriveMinFacings(
                $item['summary']->dominantAbcClass,
                $settings->minFacings,
                $settings->maxFacings,
            );

            for ($s = $blockStart; $s <= $blockEnd; $s++) {
                $slot = $orderedSlots[$s];
                $entries[] = new SlotPlanEntry(
                    categoryId: $item['category']->id,
                    moduleNumber: $slot['module'],
                    shelfOrder: $slot['shelf_order'],
                    minFacings: $minFacings,
                    visualCriteria: $this->buildVisualCriteria(),
                    zone: $slot['zone'],
                    roleOverride: $item['role'],
                    maxFacings: $settings->maxFacings > 0 ? $settings->maxFacings : null,
                    facingExpansion: $settings->facingExpansion,
                    useTargetStock: $settings->useTargetStock,
                    spaceFallback: $settings->spaceFallback,
                    maxSharePerSku: $settings->maxSharePerSku,
                    maxSharePerBrand: $settings->maxSharePerBrand,
                    maxSharePerSubcategory: $settings->maxSharePerSubcategory,
                );
            }
        }

        return $entries;
    }

    /**
     * Deriva min_facings escalando o peso ABC para o intervalo [min, max].
     * A → max, C → min, B/null → meio.
     */
    private function deriveMinFacings(?string $abcClass, int $min, int $max): int
    {
        $weight = self::ABC_FACING_WEIGHT[$abcClass] ?? self::ABC_FACING_WEIGHT[null];
        $maxWeight = self::ABC_FACING_WEIGHT['A'];
        $minWeight = self::ABC_FACING_WEIGHT['C'];

        if ($maxWeight === $minWeight || $min >= $max) {
            return $min;
        }

        $ratio = ($weight - $minWeight) / ($maxWeight - $minWeight);
        $facing = (int) round($min + $ratio * ($max - $min));

        return max($min, min($max, $facing));
    }

    /**
     * Visual criteria com ABC sempre em primeiro lugar.
     * Segundo critério padrão: margem desc.
     *
     * @return list<array{key: string, direction: string}>
     */
    private function buildVisualCriteria(): array
    {
        return [
            ['key' => 'score_abc', 'direction' => 'desc'],
            ['key' => 'margem', 'direction' => 'desc'],
        ];
    }

    private function prefersHot(CategoryRole $role): bool
    {
        return in_array($role, self::HOT_ROLES, true);
    }
}
