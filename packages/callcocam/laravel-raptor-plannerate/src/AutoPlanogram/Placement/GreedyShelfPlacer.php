<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\OrderedBlock;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedLayer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementResult;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementSettings;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\RankedProductDTO;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\ScoredProduct;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\ShelfLayoutDTO;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ScoredProductMapper;
use Callcocam\LaravelRaptorPlannerate\Enums\PlacementFailureReason;
use Callcocam\LaravelRaptorPlannerate\Enums\ShelfLevel;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Motor de posicionamento que replica o algoritmo guloso do LayoutOptimizationService.
 *
 * Converte OrderedBlocks em PlacedSegments preservando exatamente o comportamento atual:
 * - ABC determina o range de prateleiras (A=superior, B=médio, C=inferior)
 * - Score relativo determina a posição exata dentro do range
 * - Produto não alocado tenta prateleiras adjacentes (±3)
 * - Produto não cabe verticalmente = descartado da prateleira
 */
final class GreedyShelfPlacer implements PlacementEngineInterface
{
    public function __construct(
        private readonly ProductWidthResolver $widthResolver,
    ) {}

    /** @param  Collection<int, Section>  $sections */
    public function totalAvailableWidth(Collection $sections): float
    {
        return (float) $sections->sum(fn (Section $section): float => $section->shelves->count() * $this->getShelfAvailableWidth($section));
    }

    /**
     * @param  Collection<int, OrderedBlock>  $orderedBlocks
     * @param  Collection<int, Section>  $sections
     * @param  array<string, float>  $reservedWidthPerShelf
     */
    public function place(Collection $orderedBlocks, Collection $sections, PlacementSettings $settings, array $reservedWidthPerShelf = []): PlacementResult
    {
        $strategy = $settings->tenantId ? new ShelfLevelStrategy($settings->tenantId) : null;
        $totalInput = $orderedBlocks->sum(fn (OrderedBlock $orderedBlock): int => $orderedBlock->block->children->count());
        $brokenBlocks = 0;
        $rejected = collect();

        $sectionLayouts = $this->buildSectionShelfStructure($sections, $reservedWidthPerShelf);

        $filtered = $this->prefilterByHeight($orderedBlocks, $sections);
        $rankedBlocks = $filtered['placeable']
            ->sortBy('sequenceOrder')
            ->values();
        $rejected = $rejected->merge($filtered['rejected']);

        /** @var Collection<int, Collection<int, RankedProductDTO>> $productsByBlock */
        $productsByBlock = $rankedBlocks
            ->map(fn (OrderedBlock $orderedBlock): Collection => $orderedBlock->block->children->map(
                fn (ScoredProduct $product) => $this->toRankedProductDto($product)
            )->values())
            ->values();

        $rankedProducts = $productsByBlock->flatten(1)->values();

        if ($rankedProducts->isEmpty()) {
            $placedSegments = collect();

            $this->logPlacementSummary($totalInput, $placedSegments, $rejected, $brokenBlocks);

            return new PlacementResult($placedSegments, $rejected);
        }

        // Distribui blocos do mesmo nível entre sections (módulos) em round-robin.
        // Evita que todos os blocos HIGH se acumulem no módulo 1.
        /** @var array<string, int> $levelSectionPointer */
        $levelSectionPointer = [];

        foreach ($productsByBlock as $blockIndex => $blockProducts) {
            $orderedBlock = $rankedBlocks[$blockIndex];
            $currentSectionIndex = 0;
            $currentShelfIndex = 0;
            $hiddenShelves = null;
            $preferredLevel = null;

            if ($strategy) {
                $preferredLevel = $strategy->decidePreferredLevel($orderedBlock->block);
                Log::debug('GreedyShelfPlacer: block→level', ['score' => round($orderedBlock->block->aggregateScore, 3), 'level' => $preferredLevel->value]);
                // Esconde shelves fora do fallback — produto não transborda para nível inadequado
                $hiddenShelves = $this->hideShelvesByLevel($sectionLayouts, $preferredLevel->fallbackOrder());
                $this->reorderShelvesByLevel($sectionLayouts, $preferredLevel);
                // Avança a section de início para distribuir entre módulos
                $currentSectionIndex = $levelSectionPointer[$preferredLevel->value] ?? 0;
            }

            if ($this->placeWholeBlock($sectionLayouts, $blockProducts->all(), $currentSectionIndex, $currentShelfIndex)) {
                if ($preferredLevel !== null) {
                    $levelSectionPointer[$preferredLevel->value] = ($currentSectionIndex + 1) % count($sectionLayouts);
                }

                if ($hiddenShelves !== null) {
                    $this->restoreHiddenShelves($sectionLayouts, $hiddenShelves);
                }

                continue;
            }

            $split = $this->placeBlockWithSplit($sectionLayouts, $blockProducts->all(), $currentSectionIndex, $currentShelfIndex);
            $rejected = $rejected->merge($split['rejected']);
            $brokenBlocks += $split['sections_used'] > 1 ? 1 : 0;

            if ($preferredLevel !== null) {
                $levelSectionPointer[$preferredLevel->value] = ($currentSectionIndex + 1) % count($sectionLayouts);
            }

            if ($hiddenShelves !== null) {
                $this->restoreHiddenShelves($sectionLayouts, $hiddenShelves);
            }
        }

        $shelfLevelMap = [];
        foreach ($sectionLayouts as $layout) {
            $shelfLevelMap += $layout['shelf_levels'];
        }

        $placedSegments = $this->convertToPlacedSegments($this->flattenShelves($sectionLayouts), $sections, $reservedWidthPerShelf, $shelfLevelMap);
        $this->logPlacementSummary($totalInput, $placedSegments, $rejected, $brokenBlocks);
        $this->logLevelDistribution($placedSegments);

        return new PlacementResult($placedSegments, $rejected);
    }

    /**
     * @param  Collection<int, Section>  $sections
     * @param  array<string, float>  $reservedWidthPerShelf
     */
    private function buildSectionShelfStructure(Collection $sections, array $reservedWidthPerShelf = []): array
    {
        $sectionLayouts = [];
        $index = 0;

        foreach ($sections as $section) {
            // Sort ascending by shelf_position: smallest position = topmost shelf
            $clearances = $this->shelfClearances($section);
            $sectionShelves = $section->shelves->sortBy('shelf_position')->values();
            $numShelves = $sectionShelves->count();
            $shelves = [];
            $shelfLevels = []; // shelfId => ShelfLevel (computed from sorted index)

            foreach ($sectionShelves as $shelfIndex => $shelf) {
                $shelfPos = (float) ($shelf->shelf_position ?? 0);
                $reserved = $reservedWidthPerShelf[$shelf->id] ?? 0.0;

                $shelves[] = new ShelfLayoutDTO(
                    id: $shelf->id,
                    shelfIndex: $index,
                    height: $clearances[$shelf->id] ?? 0.0,
                    availableWidth: max(0.0, $this->getShelfAvailableWidth($section) - $reserved),
                    depth: (float) ($shelf->shelf_depth ?? 40),
                    shelfPosition: (int) $shelfPos,
                );

                // Level é derivado do índice ordenado (0=topo), não da posição física
                $shelfLevels[$shelf->id] = ShelfLevel::fromShelfPosition($shelfIndex, $numShelves);
                $index++;
            }

            $sectionLayouts[] = [
                'section_id' => $section->id,
                'shelves' => $shelves,
                'num_shelves' => $numShelves,
                'shelf_levels' => $shelfLevels,
            ];
        }

        return $sectionLayouts;
    }

    /**
     * Calcula a altura útil (vão livre em cm) de cada prateleira da seção.
     *
     * Compartilhado com o TemplatePlacementEngine para que ambos os engines
     * apliquem o mesmo critério de rejeição por altura (HeightExceedsShelf).
     *
     * @return array<string, float> [shelf_id => clearance_cm]
     */
    public function shelfClearances(Section $section): array
    {
        $shelves = $section->shelves->sortBy('shelf_position')->values();
        $clearances = [];

        foreach ($shelves as $index => $shelf) {
            if ($index === 0) {
                $clearance = (float) ($shelf->shelf_position ?? 0);

                if ($clearance <= 0 && $shelves->has($index + 1)) {
                    $below = $shelves[$index + 1];
                    $clearance = (float) ($below->shelf_position ?? 0)
                        - ((float) ($shelf->shelf_position ?? 0) + (float) ($shelf->shelf_height ?? 0));
                }
            } else {
                $above = $shelves[$index - 1];
                $clearance = (float) ($shelf->shelf_position ?? 0)
                    - ((float) ($above->shelf_position ?? 0) + (float) ($above->shelf_height ?? 0));
            }

            $clearances[$shelf->id] = max(0.0, $clearance);
        }

        return $clearances;
    }

    private function getShelfAvailableWidth(Section $section): float
    {
        $sectionWidth = (float) ($section->width ?? 100.0);
        $cremalheiraWidth = (float) ($section->cremalheira_width ?? 0.0);

        return max(0.0, $sectionWidth - $cremalheiraWidth);
    }

    /**
     * @param  Collection<int, OrderedBlock>  $orderedBlocks
     * @param  Collection<int, Section>  $sections
     * @return array{placeable: Collection<int, OrderedBlock>, rejected: Collection<int, array{product: mixed, reason: PlacementFailureReason}>}
     */
    private function prefilterByHeight(Collection $orderedBlocks, Collection $sections): array
    {
        $maxClearance = (float) ($sections
            ->flatMap(fn (Section $section): array => array_values($this->shelfClearances($section)))
            ->max() ?? 0.0);

        $rejected = collect();

        $placeable = $orderedBlocks
            ->map(function (OrderedBlock $orderedBlock) use ($maxClearance, $rejected): OrderedBlock {
                $children = $orderedBlock->block->children
                    ->filter(function (ScoredProduct $scoredProduct) use ($maxClearance, $rejected): bool {
                        $height = (float) ($scoredProduct->product->height ?? 0);

                        if ($height > $maxClearance) {
                            $rejected->push([
                                'product' => $scoredProduct->product,
                                'reason' => PlacementFailureReason::HeightExceedsShelf,
                            ]);

                            return false;
                        }

                        return true;
                    })
                    ->values();

                return $orderedBlock->withChildren($children, $this->widthResolver);
            })
            ->filter(fn (OrderedBlock $orderedBlock): bool => $orderedBlock->block->children->isNotEmpty())
            ->values();

        return ['placeable' => $placeable, 'rejected' => $rejected];
    }

    /**
     * Reordena as shelves visíveis de cada section pela ordem de fallback do nível preferido.
     * Shelves fora do fallbackOrder ficam no final (rank PHP_INT_MAX).
     *
     * @param  array<int, array{section_id: string, shelves: array<int, ShelfLayoutDTO>, num_shelves: int, shelf_levels: array<string, ShelfLevel>}>  $sectionLayouts
     */
    private function reorderShelvesByLevel(array &$sectionLayouts, ShelfLevel $preferred): void
    {
        $fallbackOrder = $preferred->fallbackOrder();

        foreach ($sectionLayouts as &$layout) {
            $shelfLevels = $layout['shelf_levels'];

            usort($layout['shelves'], function (ShelfLayoutDTO $a, ShelfLayoutDTO $b) use ($fallbackOrder, $shelfLevels): int {
                $levelA = $shelfLevels[$a->id] ?? ShelfLevel::Low;
                $levelB = $shelfLevels[$b->id] ?? ShelfLevel::Low;
                $rankA = array_search($levelA, $fallbackOrder, true);
                $rankB = array_search($levelB, $fallbackOrder, true);

                if ($rankA === false) {
                    $rankA = PHP_INT_MAX;
                }

                if ($rankB === false) {
                    $rankB = PHP_INT_MAX;
                }

                return $rankA <=> $rankB;
            });
        }
        unset($layout);
    }

    /**
     * Remove temporariamente shelves cujo nível não está em $acceptableLevels.
     * Retorna as shelves removidas (por índice de section) para restauração posterior.
     *
     * @param  array<int, ShelfLevel>  $acceptableLevels
     * @return array<int, array<int, ShelfLayoutDTO>>
     */
    private function hideShelvesByLevel(array &$sectionLayouts, array $acceptableLevels): array
    {
        $hidden = [];

        foreach ($sectionLayouts as $i => &$layout) {
            $shelfLevels = $layout['shelf_levels'];
            $visible = [];
            $hiddenHere = [];

            foreach ($layout['shelves'] as $shelf) {
                $level = $shelfLevels[$shelf->id] ?? ShelfLevel::Low;

                if (in_array($level, $acceptableLevels, true)) {
                    $visible[] = $shelf;
                } else {
                    $hiddenHere[] = $shelf;
                }
            }

            $layout['shelves'] = $visible;
            $hidden[$i] = $hiddenHere;
        }
        unset($layout);

        return $hidden;
    }

    /**
     * Restaura shelves anteriormente escondidas por hideShelvesByLevel.
     * As shelves são reinseridas em ordem de shelfPosition (topo primeiro).
     *
     * @param  array<int, array<int, ShelfLayoutDTO>>  $hiddenShelves
     */
    private function restoreHiddenShelves(array &$sectionLayouts, array $hiddenShelves): void
    {
        foreach ($hiddenShelves as $i => $shelves) {
            foreach ($shelves as $shelf) {
                $sectionLayouts[$i]['shelves'][] = $shelf;
            }

            usort($sectionLayouts[$i]['shelves'], fn (ShelfLayoutDTO $a, ShelfLayoutDTO $b): int => $a->shelfPosition <=> $b->shelfPosition);
        }
    }

    private function toRankedProductDto(ScoredProduct $sp): RankedProductDTO
    {
        return ScoredProductMapper::toRankedWithFacings($sp);
    }

    private function tryAllocate(array $shelves, RankedProductDTO $product, int $index): bool
    {
        if (! isset($shelves[$index])) {
            return false;
        }

        $shelf = $shelves[$index];
        $productHeight = $product->product->height ?? 0;

        if ($productHeight > 0 && $productHeight > $shelf->height) {
            Log::debug('GreedyShelfPlacer: produto não cabe verticalmente', [
                'product' => $product->product->name,
                'product_height' => $productHeight,
                'shelf_height' => $shelf->height,
            ]);

            return false;
        }

        $allocated = $shelf->addProduct($product, $this->widthResolver->resolve($product->product));

        return $allocated;
    }

    /** @param  array<int, array{section_id: string, shelves: array<int, ShelfLayoutDTO>}>  $sectionLayouts */
    private function placeWholeBlock(array &$sectionLayouts, array $products, int &$currentSectionIndex, int &$currentShelfIndex): bool
    {
        $totalSections = count($sectionLayouts);
        $startSectionIndex = $currentSectionIndex;

        for ($attempt = 0; $attempt < $totalSections; $attempt++) {
            $sectionIndex = ($startSectionIndex + $attempt) % $totalSections;
            $startShelfIndex = ($attempt === 0) ? $currentShelfIndex : 0;
            $lastShelfIndex = $this->tryPlaceProductsInSection($sectionLayouts[$sectionIndex]['shelves'], $products, $startShelfIndex);

            if ($lastShelfIndex !== null) {
                $currentSectionIndex = $sectionIndex;
                $currentShelfIndex = $lastShelfIndex;

                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, ShelfLayoutDTO>  $shelves
     * @param  array<int, RankedProductDTO>  $products
     */
    private function tryPlaceProductsInSection(array &$shelves, array $products, int $startShelfIndex): ?int
    {
        if ($products === [] || $shelves === []) {
            return null;
        }

        $workingShelves = $this->cloneShelves($shelves);
        $currentShelfIndex = max(0, $startShelfIndex);

        foreach ($products as $product) {
            $placed = false;

            for ($shelfIndex = $currentShelfIndex; $shelfIndex < count($workingShelves); $shelfIndex++) {
                if (! $this->tryAllocate($workingShelves, $product, $shelfIndex)) {
                    continue;
                }

                $currentShelfIndex = $shelfIndex;
                $placed = true;

                break;
            }

            if (! $placed) {
                return null;
            }
        }

        $this->syncShelves($shelves, $workingShelves);

        return $currentShelfIndex;
    }

    /**
     * @param  array<int, array{section_id: string, shelves: array<int, ShelfLayoutDTO>}>  $sectionLayouts
     * @param  array<int, RankedProductDTO>  $products
     * @return array{rejected: Collection<int, array{product: mixed, reason: PlacementFailureReason}>, sections_used: int}
     */
    private function placeBlockWithSplit(
        array &$sectionLayouts,
        array $products,
        int &$currentSectionIndex,
        int &$currentShelfIndex,
    ): array {
        $cursor = 0;
        $sectionsUsed = 0;
        $rejected = collect();
        $totalSections = count($sectionLayouts);
        $startSectionIndex = $currentSectionIndex;
        $lastUsedSectionIndex = $startSectionIndex;

        for ($attempt = 0; $attempt < $totalSections && $cursor < count($products); $attempt++) {
            $sectionIndex = ($startSectionIndex + $attempt) % $totalSections;
            $startShelfIndex = ($attempt === 0) ? $currentShelfIndex : 0;
            $fit = $this->fitContiguousRun(
                $sectionLayouts[$sectionIndex]['shelves'],
                array_slice($products, $cursor),
                $startShelfIndex,
            );

            if ($fit['count'] > 0) {
                $cursor += $fit['count'];
                $lastUsedSectionIndex = $sectionIndex;
                $currentShelfIndex = $fit['last_shelf_index'];
                $sectionsUsed++;
            }
        }

        $currentSectionIndex = $lastUsedSectionIndex;

        foreach (array_slice($products, $cursor) as $product) {
            $rejected->push([
                'product' => $product->product,
                'reason' => PlacementFailureReason::NoHorizontalSpace,
            ]);
        }

        return ['rejected' => $rejected, 'sections_used' => $sectionsUsed];
    }

    /**
     * @param  array<int, ShelfLayoutDTO>  $shelves
     * @param  array<int, RankedProductDTO>  $products
     * @return array{count: int, last_shelf_index: int}
     */
    private function fitContiguousRun(array &$shelves, array $products, int $startShelfIndex): array
    {
        $placedCount = 0;
        $currentShelfIndex = max(0, $startShelfIndex);
        $lastShelfIndex = $currentShelfIndex;

        foreach ($products as $product) {
            $placed = false;

            for ($shelfIndex = $currentShelfIndex; $shelfIndex < count($shelves); $shelfIndex++) {
                if (! $this->tryAllocate($shelves, $product, $shelfIndex)) {
                    continue;
                }

                $lastShelfIndex = $shelfIndex;
                $currentShelfIndex = $shelfIndex;
                $placed = true;

                break;
            }

            if (! $placed) {
                break;
            }

            $placedCount++;
        }

        return ['count' => $placedCount, 'last_shelf_index' => $lastShelfIndex];
    }

    /**
     * @param  array<int, ShelfLayoutDTO>  $shelves
     * @return array<int, ShelfLayoutDTO>
     */
    private function cloneShelves(array $shelves): array
    {
        return array_map(function (ShelfLayoutDTO $shelf): ShelfLayoutDTO {
            $clone = new ShelfLayoutDTO(
                id: $shelf->id,
                shelfIndex: $shelf->shelfIndex,
                height: $shelf->height,
                availableWidth: $shelf->availableWidth,
                depth: $shelf->depth,
                shelfPosition: $shelf->shelfPosition,
            );

            $clone->products = $shelf->products;
            $clone->occupiedWidth = $shelf->occupiedWidth;

            return $clone;
        }, $shelves);
    }

    /**
     * @param  array<int, ShelfLayoutDTO>  $targetShelves
     * @param  array<int, ShelfLayoutDTO>  $sourceShelves
     */
    private function syncShelves(array &$targetShelves, array $sourceShelves): void
    {
        foreach ($sourceShelves as $index => $shelf) {
            $targetShelves[$index]->products = $shelf->products;
            $targetShelves[$index]->occupiedWidth = $shelf->occupiedWidth;
        }
    }

    /**
     * @param  array<int, array{section_id: string, shelves: array<int, ShelfLayoutDTO>}>  $sectionLayouts
     * @return array<int, ShelfLayoutDTO>
     */
    private function flattenShelves(array $sectionLayouts): array
    {
        return array_values(array_merge(
            [],
            ...array_map(fn (array $layout): array => $layout['shelves'], $sectionLayouts),
        ));
    }

    /**
     * Converte ShelfLayoutDTOs para PlacedSegments, mantendo o mapeamento correto
     * para section_id e shelf_id com base nas sections e suas shelves.
     *
     * @param  ShelfLayoutDTO[]  $shelves
     * @param  Collection<int, Section>  $sections
     * @param  array<string, float>  $reservedWidthPerShelf  Starting X offset per shelf (from vertical placer)
     * @param  array<string, ShelfLevel>  $shelfLevelMap  shelfId => ShelfLevel for logging
     * @return Collection<int, PlacedSegment>
     */
    private function convertToPlacedSegments(array $shelves, Collection $sections, array $reservedWidthPerShelf = [], array $shelfLevelMap = []): Collection
    {
        // Monta mapa shelfId → sectionId para look-up rápido
        $shelfToSection = [];
        foreach ($sections as $section) {
            foreach ($section->shelves as $shelf) {
                $shelfToSection[$shelf->id] = $section->id;
            }
        }

        $placedSegments = collect();

        foreach ($shelves as $shelfLayout) {
            if (empty($shelfLayout->products)) {
                continue;
            }

            $sectionId = $shelfToSection[$shelfLayout->id] ?? '';
            $ordering = 0;
            // Cursor em float: as posições/larguras persistidas são inteiras (cm), mas
            // arredondar cada largura e somá-las acumula erro ao longo da prateleira.
            // Arredondamos os PONTOS (início/fim) da posição exata — segmentos contíguos,
            // sem gaps nem sobreposição. Mesmo critério do TemplatePlacementEngine.
            $cursorX = (float) ($reservedWidthPerShelf[$shelfLayout->id] ?? 0.0);
            $shelfLevel = $shelfLevelMap[$shelfLayout->id] ?? null;

            foreach ($shelfLayout->products as $rankedProduct) {
                $exactWidth = $this->widthResolver->resolve($rankedProduct->product) * $rankedProduct->facings;

                [$startCm, $productWidth] = PlacementMath::segmentBounds($cursorX, $exactWidth);

                $layer = new PlacedLayer(
                    productId: $rankedProduct->product->id,
                    ean: (string) ($rankedProduct->product->ean ?? $rankedProduct->product->codigo_erp ?? ''),
                    quantity: $rankedProduct->facings,
                    height: 1,
                );

                $placedSegments->push(new PlacedSegment(
                    sectionId: $sectionId,
                    shelfId: $shelfLayout->id,
                    ordering: $ordering,
                    position: $startCm,
                    width: $productWidth,
                    distributedWidth: $productWidth,
                    layers: collect([$layer]),
                    shelfLevel: $shelfLevel,
                ));

                $ordering++;
                $cursorX += $exactWidth;
            }
        }

        return $placedSegments;
    }

    private function logPlacementSummary(int $totalInput, Collection $placedSegments, Collection $rejected, int $brokenBlocks): void
    {
        Log::info('GreedyShelfPlacer: resumo do placement', [
            'produtos_entrada' => $totalInput,
            'segmentos_posicionados' => $placedSegments->count(),
            'rejeitados_altura' => $rejected->where('reason', PlacementFailureReason::HeightExceedsShelf)->count(),
            'rejeitados_espaco' => $rejected->where('reason', PlacementFailureReason::NoHorizontalSpace)->count(),
            'blocos_quebrados' => $brokenBlocks,
            'taxa_ocupacao' => $totalInput > 0 ? round($placedSegments->count() / $totalInput, 3) : 0,
        ]);
    }

    /** @param  Collection<int, PlacedSegment>  $placedSegments */
    private function logLevelDistribution(Collection $placedSegments): void
    {
        Log::info('GreedyShelfPlacer: distribuição por nível', [
            'eye' => $placedSegments->filter(fn (PlacedSegment $s) => $s->shelfLevel === ShelfLevel::Eye)->count(),
            'hand' => $placedSegments->filter(fn (PlacedSegment $s) => $s->shelfLevel === ShelfLevel::Hand)->count(),
            'low' => $placedSegments->filter(fn (PlacedSegment $s) => $s->shelfLevel === ShelfLevel::Low)->count(),
            'high' => $placedSegments->filter(fn (PlacedSegment $s) => $s->shelfLevel === ShelfLevel::High)->count(),
        ]);
    }
}
