<?php

namespace App\Services\AutoPlanogram\Synthesis;

use App\Enums\CategoryRole;
use App\Models\Category;
use App\Services\AutoPlanogram\DTO\CategoryAbcSummary;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\SlotPlanEntry;
use App\Services\AutoPlanogram\ShelfZoneResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Produz o plano de slots do template sintetizado: para cada (módulo, prateleira),
 * qual subcategoria ocupa, com min_facings e visual_criteria.
 *
 * Algoritmo (block-partition):
 * 1. Ordenar subcategorias: hot-preferring primeiro (destino/impulso), por ABC e giro.
 * 2. Construir slots ordenados por zona dentro de cada módulo (hot first) → blocagem vertical.
 * 3. Particionar slots em blocos proporcionais à demanda de cada subcategoria:
 *    - Demanda principal: largura total dos produtos (totalWidth) × ceil(totalWidth / shelfWidth).
 *    - Fallback quando sem dados de largura: proporcional ao volume (totalQuantity).
 *    - "Não super-provisionar": se demanda total < capacidade física, gera menos prateleiras.
 *    - Subcategoria sem produto elegível (skuCount=0 e totalQuantity=0) não gera slot.
 *
 * ABC é SEMPRE o primeiro critério visual (score_abc desc) — não é configurável.
 */
class SlotPlanBuilder
{
    /**
     * Número mínimo de frentes por classe ABC.
     *
     * Estes são valores absolutos de piso de exibição — NÃO escalados até maxFacings.
     * O maxFacings é teto de expansão (Phase 2 de placement) e não deve ser confundido
     * com o piso mínimo de frentes necessário para um produto aparecer na gôndola.
     *
     * A→3, B→2, C→1, ''(desconhecido)→2.
     */
    private const ABC_MIN_FACINGS = ['A' => 3, 'B' => 2, 'C' => 1, '' => 2];

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
     * @param  float  $shelfWidth  Largura útil da prateleira (cm). Usada para calcular demanda real.
     * @return list<SlotPlanEntry>
     */
    public function build(
        Category $selectedCategory,
        Collection $subcategories,
        int $numModules,
        int $shelvesPerModule,
        PlacementSettings $settings,
        float $shelfWidth = 100.0,
        bool $useFullCapacity = false,
    ): array {
        $orderedSlots = $this->buildOrderedSlots($numModules, $shelvesPerModule);

        if ($subcategories->isEmpty()) {
            return $this->buildLeafPlan($selectedCategory, $orderedSlots, $settings);
        }

        $sorted = $this->sortSubcategories($subcategories);

        return $this->partitionIntoBlocks($sorted, $orderedSlots, $settings, $shelfWidth, $useFullCapacity);
    }

    /**
     * Caso folha: preenche todos os slots com a própria categoria selecionada.
     *
     * Aplica deriveMinFacings com a classe ABC dominante extraída do abcClassMap,
     * garantindo que o WARNING de "todos os slots têm mesmo min_facings" não dispare
     * quando há diversidade ABC nos produtos da categoria folha.
     *
     * @param  list<array{module: int, shelf_order: int, zone: 'hot'|'cold'|'neutral'}>  $orderedSlots
     * @return list<SlotPlanEntry>
     */
    private function buildLeafPlan(
        Category $selectedCategory,
        array $orderedSlots,
        PlacementSettings $settings,
    ): array {
        // Deriva a classe ABC dominante dos produtos conhecidos via abcClassMap
        $dominantAbc = $this->deriveDominantAbcFromMap($settings->abcClassMap);
        $minFacings = $this->deriveMinFacings($dominantAbc, $settings->minFacings, $settings->maxFacings);

        return array_map(fn (array $slot): SlotPlanEntry => new SlotPlanEntry(
            categoryId: $selectedCategory->id,
            moduleNumber: $slot['module'],
            shelfOrder: $slot['shelf_order'],
            minFacings: $minFacings,
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
     * Particiona slots em blocos proporcionais à demanda de cada subcategoria.
     *
     * Estratégia de demanda (hasSomeWidth = true):
     * - Computa demanda individual: ceil(totalWidth / shelfWidth), mínimo 1 slot por subcat.
     * - totalDemandado = soma das demandas individuais (substitui o max uniforme anterior).
     * - Capacidade suficiente: usa contagens individuais diretamente (sem distorção Hare).
     *   Subcategoria grande recebe mais prateleiras; pequena recebe menos.
     * - Capacidade insuficiente: aplica Hare puro com as contagens como pesos.
     *
     * Fallback quando sem dados de largura: usa totalQuantity como peso proporcional (Hare com
     * garantia de 1 slot por subcategoria). Toda a capacidade disponível é usada.
     *
     * Subcategorias sem produto elegível (skuCount = 0 e totalQuantity = 0) são excluídas.
     *
     * Regeneração (useFullCapacity = true): usa toda a capacidade disponível sem redução por demanda.
     * Isso preserva a estrutura física existente da gôndola ao regenerar um planograma.
     *
     * @param  Collection<int, array{category: Category, summary: CategoryAbcSummary, role: CategoryRole}>  $sorted
     * @param  list<array{module: int, shelf_order: int, zone: string, zone_priority: int}>  $orderedSlots
     * @return list<SlotPlanEntry>
     */
    private function partitionIntoBlocks(
        Collection $sorted,
        array $orderedSlots,
        PlacementSettings $settings,
        float $shelfWidth = 100.0,
        bool $useFullCapacity = false,
    ): array {
        $totalCapacity = count($orderedSlots);

        // 1. Filtrar subcategorias com demanda real (algum produto elegível)
        $withDemand = $sorted->filter(
            fn ($item) => $item['summary']->skuCount > 0 || $item['summary']->totalQuantity > 0
        );

        if ($withDemand->isEmpty()) {
            // Fallback: sem dados de demanda alguma, distribuir igualmente entre todas
            $withDemand = $sorted;
        }

        $numWithDemand = $withDemand->count();

        // 2. Calcular demanda por subcategoria
        $hasSomeWidth = $withDemand->some(fn ($item) => $item['summary']->totalWidth > 0);

        if ($hasSomeWidth) {
            // Demanda individual: ceil(totalWidth / shelfWidth), mínimo 1.
            // Subcategoria com 267 cm → 3 slots; com 44 cm → 1 slot; com 5 cm → 1 slot.
            $subcatSlotsNeeded = $this->computePerSubcatSlots($withDemand, $shelfWidth);
            $demandedTotal = (int) array_sum($subcatSlotsNeeded);
        } else {
            // Sem dados de largura: fallback por quantidade (Hare com garantia de 1)
            $subcatSlotsNeeded = null;
            $demandedTotal = $totalCapacity;
        }

        // 3. Não super-provisionar (apenas para gôndolas novas).
        //    Regeneração (useFullCapacity=true): preservar estrutura física existente, usar toda capacidade.
        $totalUsed = $useFullCapacity ? $totalCapacity : min($totalCapacity, $demandedTotal);
        $activeSlots = array_slice($orderedSlots, 0, $totalUsed);

        // 4. Distribuir slots por subcategoria
        if ($hasSomeWidth && $subcatSlotsNeeded !== null) {
            if (! $useFullCapacity && $totalCapacity >= $demandedTotal) {
                // Capacidade suficiente: usa contagens individuais diretamente.
                // Regra-mor: subcategoria com mais demanda recebe mais prateleiras.
                $slotCounts = $subcatSlotsNeeded;
            } else {
                // Capacidade insuficiente ou regeneração: Hare puro com demandas como pesos.
                // Hare puro (sem pré-alocação de 1) preserva proporção ao escalar.
                $slotCounts = $this->distributeByPureHare(
                    array_map('floatval', $subcatSlotsNeeded),
                    $totalUsed,
                );
            }
        } else {
            // Fallback por quantidade: Hare com garantia de 1 slot por subcategoria
            $demandWeights = $withDemand->values()->map(
                fn ($item) => max(1.0, $item['summary']->totalQuantity)
            )->all();
            $slotCounts = $this->distributeProportionally($demandWeights, $totalUsed);
        }

        // 5. Log de subcategorias excluídas por falta de demanda
        $excluded = $sorted->count() - $numWithDemand;
        if ($excluded > 0) {
            Log::info('SlotPlanBuilder: subcategorias excluídas por falta de produto elegível', [
                'excluídas' => $excluded,
                'com_demanda' => $numWithDemand,
            ]);
        }

        // 6. Montar entradas do plano
        $entries = [];
        $slotIndex = 0;

        foreach ($withDemand->values() as $i => $item) {
            $count = $slotCounts[$i] ?? 1;

            $minFacings = $this->deriveMinFacings(
                $item['summary']->dominantAbcClass,
                $settings->minFacings,
                $settings->maxFacings,
            );

            for ($s = 0; $s < $count; $s++) {
                if ($slotIndex >= count($activeSlots)) {
                    break;
                }

                $slot = $activeSlots[$slotIndex++];
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
     * Distribui $total slots entre N subcategorias proporcionalmente aos pesos fornecidos.
     *
     * Estratégia em duas etapas (garante soma exata = $total sem violar invariante):
     * 1. Pré-alocação: cada subcategoria recebe 1 slot base (garante mínimo sem violar soma).
     * 2. Remainder: distribui os ($total - N) slots restantes via Hare (resto-maior).
     *
     * Caso especial: se $total < N, as primeiras $total subcategorias recebem 1 slot
     * e as demais ficam com 0 (aceita que algumas subcategorias fiquem sem slot).
     *
     * @param  array<int, float>  $weights  Peso de cada subcategoria (índices 0..N-1).
     * @return array<int, int> Quantidade de slots por índice (soma = $total).
     */
    private function distributeProportionally(array $weights, int $total): array
    {
        $n = count($weights);

        if ($n === 0 || $total <= 0) {
            return [];
        }

        // Capacidade insuficiente para 1 por subcat: atribuir às primeiras $total
        if ($total < $n) {
            $result = array_fill(0, $n, 0);
            for ($i = 0; $i < $total; $i++) {
                $result[$i] = 1;
            }

            return $result;
        }

        // Pré-alocação: 1 slot garantido para cada subcategoria
        $remaining = $total - $n;
        $result = array_fill(0, $n, 1);

        if ($remaining === 0) {
            return $result;
        }

        // Hare LR aplicado apenas sobre os slots restantes
        $totalWeight = (float) max(array_sum($weights), 1e-9);
        $floors = [];
        $remainders = [];
        $floorSum = 0;

        foreach ($weights as $i => $w) {
            $rawShare = $remaining * ($w / $totalWeight);
            $floor = (int) floor($rawShare);
            $floors[$i] = $floor;
            $remainders[$i] = $rawShare - $floor;
            $floorSum += $floor;
        }

        // Distribuir os slots de arredondamento pelos maiores restos
        $extraRemaining = $remaining - $floorSum;
        arsort($remainders);
        $count = 0;

        foreach (array_keys($remainders) as $i) {
            if ($count >= $extraRemaining) {
                break;
            }

            $floors[$i]++;
            $count++;
        }

        // Somar os extras à pré-alocação
        foreach ($floors as $i => $extra) {
            $result[$i] += $extra;
        }

        ksort($result);

        return array_values($result);
    }

    /**
     * Calcula a demanda individual de slots por subcategoria: ceil(totalWidth / shelfWidth), mínimo 1.
     *
     * Resolve a regra-mor: subcategoria com mix de 267 cm → 3 prateleiras de 100 cm;
     * subcategoria com 44 cm → 1 prateleira. Sem ambiguidade de "soma global ÷ total subcats".
     *
     * @param  Collection<int, array{category: Category, summary: CategoryAbcSummary, role: CategoryRole}>  $withDemand
     * @return array<int, int> Contagem de slots por índice (valores ≥ 1).
     */
    private function computePerSubcatSlots(Collection $withDemand, float $shelfWidth): array
    {
        return $withDemand->values()->map(
            fn ($item) => max(1, (int) ceil($item['summary']->totalWidth / max($shelfWidth, 1.0)))
        )->all();
    }

    /**
     * Distribui $total slots proporcionalmente aos pesos via Hare puro (sem pré-alocação de 1).
     *
     * Diferença do distributeProportionally: não garante 1 slot por subcategoria.
     * Isso preserva a proporção correta ao escalar para baixo (capacidade < demanda):
     *   - Ex.: pesos=[3,1,1], total=3 → [1.8,0.6,0.6] → floors=[1,0,0] + extras=[1,1] = [2,1,0]
     *   - A subcategoria pequena com 0 slots não entra no plano (aceitável sob restrição).
     *
     * Deve ser usado apenas quando hasSomeWidth=true e a capacidade é insuficiente para demanda plena.
     *
     * @param  array<int, float>  $weights  Pesos (slots demandados por subcategoria).
     * @return array<int, int> Soma exata = $total.
     */
    private function distributeByPureHare(array $weights, int $total): array
    {
        $n = count($weights);

        if ($n === 0 || $total <= 0) {
            return [];
        }

        $totalWeight = (float) max(array_sum($weights), 1e-9);
        $result = array_fill(0, $n, 0);
        $remainders = [];
        $floorSum = 0;

        foreach ($weights as $i => $w) {
            $rawShare = $total * ($w / $totalWeight);
            $floor = (int) floor($rawShare);
            $result[$i] = $floor;
            $remainders[$i] = $rawShare - $floor;
            $floorSum += $floor;
        }

        // Distribuir os slots de arredondamento pelos maiores restos
        $extra = $total - $floorSum;
        arsort($remainders);
        $count = 0;

        foreach (array_keys($remainders) as $i) {
            if ($count >= $extra) {
                break;
            }

            $result[$i]++;
            $count++;
        }

        return array_values($result);
    }

    /**
     * Deriva a classe ABC dominante a partir do abcClassMap (product_id → classe).
     * Retorna null se o mapa estiver vazio.
     *
     * @param  array<string, string>  $abcClassMap
     */
    private function deriveDominantAbcFromMap(array $abcClassMap): ?string
    {
        if (empty($abcClassMap)) {
            return null;
        }

        $counts = [];

        foreach ($abcClassMap as $abc) {
            $counts[$abc] = ($counts[$abc] ?? 0) + 1;
        }

        arsort($counts);
        $first = array_key_first($counts);

        return $first !== null ? (string) $first : null;
    }

    /**
     * Deriva min_facings para um slot baseado na classe ABC dominante.
     *
     * Usa valores fixos por ABC (ABC_MIN_FACINGS) em vez de escalar até maxFacings.
     * Isso evita que o piso mínimo atinja o teto de expansão (maxFacings), o que tornaria
     * produtos de largura realista impossíveis de posicionar em prateleiras padrão.
     *
     * Exemplos (min=1, max=10):
     *   A → 3  (3 × 23cm = 69cm ≤ 96cm ✓)
     *   B → 2
     *   C → 1
     *
     * O resultado é sempre clamped em [min, max] para respeitar os limites configurados.
     */
    private function deriveMinFacings(?string $abcClass, int $min, int $max): int
    {
        $abcMin = self::ABC_MIN_FACINGS[$abcClass ?? ''] ?? self::ABC_MIN_FACINGS[''];

        return max($min, min($max, $abcMin));
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
