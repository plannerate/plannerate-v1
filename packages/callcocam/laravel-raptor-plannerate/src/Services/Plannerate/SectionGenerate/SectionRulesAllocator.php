<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate\SectionGenerate;

use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\RankedProductDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\ShelfLayoutDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\SectionGenerate\SectionAllocationItemDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\SectionGenerate\SectionAllocationResultDTO;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate\MerchandisingRulesService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Alocação por regras para UMA section: distribui produtos nas prateleiras
 * respeitando largura da section e altura das prateleiras.
 *
 * Retorna o mesmo formato do PlanogramSectionAllocator (SectionAllocationResultDTO).
 */
class SectionRulesAllocator
{
    public function __construct(
        protected MerchandisingRulesService $merchandisingRules
    ) {}

    /**
     * Alocar produtos às prateleiras desta section.
     *
     * @param  Collection<int, RankedProductDTO>  $rankedProducts
     */
    public function allocate(
        Section $section,
        Collection $rankedProducts,
        AutoGenerateConfigDTO $config
    ): SectionAllocationResultDTO {
        $section->loadMissing('shelves', 'gondola');

        // Hot zone: se flow=right_to_left, inverter ordem dos produtos
        // para que produtos de maior score comecem na direita (área de maior visibilidade)
        $gondolaFlow = $section->gondola->flow ?? 'left_to_right';
        if ($gondolaFlow === 'right_to_left') {
            $rankedProducts = $rankedProducts->reverse()->values();
            Log::debug('🔄 Hot zone RTL: Produtos invertidos para começar da direita', [
                'section_id' => $section->id,
                'gondola_flow' => $gondolaFlow,
                'products_count' => $rankedProducts->count(),
            ]);
        }

        $shelves = $this->createShelfLayouts($section);
        if ($shelves === []) {
            return new SectionAllocationResultDTO(
                reasoning: 'Section sem prateleiras.',
                allocation: [],
                unallocated: $rankedProducts->pluck('product.id')->map(fn ($id) => (string) $id)->all(),
            );
        }

        $maxSales = $rankedProducts->max('salesTotal') ?: 1.0;
        foreach ($rankedProducts as $product) {
            $facings = $this->merchandisingRules->calculateFacings($product, $config, $maxSales);
            $product->setFacings($facings);
        }

        $grouped = $this->merchandisingRules->groupBySubcategory($rankedProducts->toArray(), $config);
        $totalShelves = count($shelves);
        $maxScore = $rankedProducts->max('score') ?: 1.0;
        $unallocatedIds = [];
        $unallocatedByReason = [];
        $unallocatedReasonByProduct = [];
        $splitCandidates = 0;
        $splitResolved = 0;
        $splitFailed = 0;

        foreach ($grouped as $group) {
            foreach ($group as $product) {
                $scoreRatio = $maxScore > 0 ? ($product->score / $maxScore) : 0.5;
                $idealIndex = $this->merchandisingRules->determineShelfIndex($product, $totalShelves, $scoreRatio);

                if ($this->tryAllocate($shelves, $product, $idealIndex)) {
                    continue;
                }
                if ($this->tryAllocateNearby($shelves, $product, $idealIndex, max(1, $totalShelves - 1))) {
                    continue;
                }

                $splitPlanResult = $this->buildSplitPlan(
                    shelves: $shelves,
                    product: $product,
                    idealIndex: $idealIndex,
                    maxDistance: max(1, $totalShelves - 1),
                );

                if ($splitPlanResult['is_candidate']) {
                    $splitCandidates++;
                }

                if ($splitPlanResult['plan'] !== [] && $this->applySplitPlan($shelves, $product, $splitPlanResult['plan'])) {
                    $splitResolved++;

                    continue;
                }

                if ($splitPlanResult['is_candidate']) {
                    $splitFailed++;
                }

                $unallocatedIds[] = $product->product->id;
                $reason = $this->detectRejectionReason($shelves, $product);
                $unallocatedByReason[$reason] = ($unallocatedByReason[$reason] ?? 0) + 1;
                $unallocatedReasonByProduct[(string) $product->product->id] = $reason;
            }
        }

        $allocation = $this->shelvesToAllocationItems($shelves);

        Log::info('📐 Section alocada (regras)', [
            'section_id' => $section->id,
            'allocated' => count($allocation),
            'unallocated' => count($unallocatedIds),
            'unallocated_by_reason' => $unallocatedByReason,
            'split_candidates' => $splitCandidates,
            'split_resolved' => $splitResolved,
            'split_failed' => $splitFailed,
        ]);

        return new SectionAllocationResultDTO(
            reasoning: 'Alocação por regras de merchandising (ABC, score, dimensões).',
            allocation: $allocation,
            unallocated: array_map(fn ($id) => (string) $id, $unallocatedIds),
            unallocatedByReason: $unallocatedByReason,
            unallocatedReasonByProduct: $unallocatedReasonByProduct,
            allocationDiagnostics: [
                'split_candidates' => $splitCandidates,
                'split_resolved' => $splitResolved,
                'split_failed' => $splitFailed,
            ],
        );
    }

    /**
     * @return ShelfLayoutDTO[]
     */
    protected function createShelfLayouts(Section $section): array
    {
        $section->loadMissing('gondola');

        $list = [];
        $sectionWidth = (float) ($section->width ?? 0);
        $computedSectionWidth = (float) ($section->section_width ?? 0);
        $sectionHeight = (float) ($section->height ?? 0);
        $scaleFactor = (float) ($section->gondola?->scale_factor ?? 1.0);
        if ($scaleFactor <= 0) {
            $scaleFactor = 1.0;
        }

        $defaultWidth = $sectionWidth > 0
            ? $sectionWidth
            : ($computedSectionWidth > 0 ? $computedSectionWidth : 100.0);

        $orderedShelves = $section->shelves
            ->sortBy(fn ($shelf) => (float) ($shelf->shelf_position ?? 0))
            ->values();

        Log::debug('Section dimensões para alocação', [
            'section_id' => $section->id,
            'section_width' => $sectionWidth,
            'section_width_computed' => $computedSectionWidth,
            'section_height' => $sectionHeight,
            'scale_factor' => $scaleFactor,
            'shelves_count' => $orderedShelves->count(),
            'shelves' => $orderedShelves->map(fn ($shelf) => [
                'id' => $shelf->id,
                'shelf_position' => (float) ($shelf->shelf_position ?? 0),
                'shelf_width' => (float) ($shelf->shelf_width ?? 0),
                'shelf_height' => (float) ($shelf->shelf_height ?? $shelf->height ?? 30),
                'shelf_depth' => (float) ($shelf->shelf_depth ?? 40),
            ])->values()->all(),
        ]);

        foreach ($orderedShelves as $index => $shelf) {
            $rawShelfWidth = (float) ($shelf->shelf_width ?? 0);
            $fallbackShelfHeight = (float) ($shelf->shelf_height ?? $shelf->height ?? 30);
            $shelfDepth = (float) ($shelf->shelf_depth ?? 40);
            $rawShelfPosition = (float) ($shelf->shelf_position ?? 0);
            $nextShelfPosition = isset($orderedShelves[$index + 1])
                ? (float) ($orderedShelves[$index + 1]->shelf_position ?? 0)
                : $sectionHeight;
            $rawGap = $nextShelfPosition - $rawShelfPosition;

            // Altura útil = distância entre prateleiras (ou topo da section), escalada.
            $heightFromPosition = $rawGap > 0 ? ($rawGap * $scaleFactor) : 0.0;

            // Alguns tenants salvam medidas em "unidades visuais" (ex.: 4) e não em cm.
            // Se a largura da shelf vier muito baixa, usa largura padrão da section.
            $width = $rawShelfWidth > 0 && $rawShelfWidth >= 10
                ? $rawShelfWidth
                : $defaultWidth;

            $height = $heightFromPosition > 0
                ? $heightFromPosition
                : $fallbackShelfHeight;

            if ($height <= 0) {
                $height = 30.0;
            }

            Log::debug('Shelf normalizada para alocação', [
                'section_id' => $section->id,
                'shelf_id' => $shelf->id,
                'raw_width' => $rawShelfWidth,
                'raw_height' => $fallbackShelfHeight,
                'shelf_depth' => $shelfDepth,
                'raw_position' => $rawShelfPosition,
                'next_position' => $nextShelfPosition,
                'raw_gap' => $rawGap,
                'height_from_position' => $heightFromPosition,
                'scale_factor' => $scaleFactor,
                'normalized_width' => $width,
                'normalized_height' => $height,
            ]);

            $list[$index] = new ShelfLayoutDTO(
                id: $shelf->id,
                shelfIndex: $index,
                height: $height,
                availableWidth: $width,
                depth: $shelfDepth,
            );
        }

        return $list;
    }

    protected function tryAllocate(array $shelves, RankedProductDTO $product, int $shelfIndex): bool
    {
        if (! isset($shelves[$shelfIndex])) {
            return false;
        }
        $shelf = $shelves[$shelfIndex];
        $productHeight = (float) ($product->product->height ?? 0);
        if ($productHeight > 0 && $productHeight > $shelf->height) {
            return false;
        }

        return $shelf->addProduct($product);
    }

    protected function tryAllocateNearby(array $shelves, RankedProductDTO $product, int $idealIndex, int $maxDistance): bool
    {
        $total = count($shelves);
        for ($d = 1; $d <= $maxDistance; $d++) {
            $upper = $idealIndex + $d;
            if ($upper < $total && $this->tryAllocate($shelves, $product, $upper)) {
                return true;
            }
            $lower = $idealIndex - $d;
            if ($lower >= 0 && $this->tryAllocate($shelves, $product, $lower)) {
                return true;
            }
        }

        return false;
    }

    protected function detectRejectionReason(array $shelves, RankedProductDTO $product): string
    {
        $productHeight = (float) ($product->product->height ?? 0);
        $productDepth = (float) ($product->product->depth ?? 0);
        $productWidth = (float) ($product->product->width ?? 10) * $product->facings;

        $heightEligibleShelves = [];
        foreach ($shelves as $shelf) {
            if ($productHeight > 0 && $productHeight > $shelf->height) {
                continue;
            }
            $heightEligibleShelves[] = $shelf;
        }

        if ($heightEligibleShelves === []) {
            return 'height_exceeded';
        }

        $depthEligibleShelves = [];
        foreach ($heightEligibleShelves as $shelf) {
            if ($productDepth > 0 && $productDepth > $shelf->depth) {
                continue;
            }
            $depthEligibleShelves[] = $shelf;
        }

        if ($depthEligibleShelves === []) {
            return 'depth_exceeded';
        }

        foreach ($depthEligibleShelves as $shelf) {
            if (($shelf->occupiedWidth + $productWidth) <= $shelf->availableWidth) {
                return 'no_shelf_found';
            }
        }

        return 'width_exceeded';
    }

    /**
     * @param  ShelfLayoutDTO[]  $shelves
     * @return array{plan: array<int, int>, is_candidate: bool}
     */
    protected function buildSplitPlan(
        array $shelves,
        RankedProductDTO $product,
        int $idealIndex,
        int $maxDistance,
    ): array {
        $unitWidth = (float) ($product->product->width ?? 10);
        if ($unitWidth <= 0) {
            return ['plan' => [], 'is_candidate' => false];
        }

        $requiredFacings = max(1, $product->facings);
        $orderedIndexes = $this->orderedShelfIndexes(count($shelves), $idealIndex, $maxDistance);

        $eligible = [];
        foreach ($orderedIndexes as $index) {
            if (! isset($shelves[$index])) {
                continue;
            }

            $shelf = $shelves[$index];
            if (! $this->isShelfEligibleForProduct($shelf, $product)) {
                continue;
            }

            $remainingWidth = $shelf->availableWidth - $shelf->occupiedWidth;
            $availableFacings = (int) floor($remainingWidth / $unitWidth);
            if ($availableFacings <= 0) {
                continue;
            }

            $eligible[$index] = $availableFacings;
        }

        if ($eligible === []) {
            return ['plan' => [], 'is_candidate' => false];
        }

        $totalAvailableFacings = array_sum($eligible);
        $isCandidate = $totalAvailableFacings >= $requiredFacings && count($eligible) >= 2;
        if (! $isCandidate) {
            return ['plan' => [], 'is_candidate' => false];
        }

        $remainingFacings = $requiredFacings;
        $plan = [];
        foreach ($eligible as $index => $availableFacings) {
            if ($remainingFacings <= 0) {
                break;
            }

            $assignedFacings = min($remainingFacings, $availableFacings);
            if ($assignedFacings <= 0) {
                continue;
            }

            $plan[$index] = $assignedFacings;
            $remainingFacings -= $assignedFacings;
        }

        if ($remainingFacings > 0) {
            return ['plan' => [], 'is_candidate' => true];
        }

        return ['plan' => $plan, 'is_candidate' => true];
    }

    /**
     * @param  ShelfLayoutDTO[]  $shelves
     * @param  array<int, int>  $plan
     */
    protected function applySplitPlan(array $shelves, RankedProductDTO $product, array $plan): bool
    {
        if ($plan === []) {
            return false;
        }

        foreach ($plan as $index => $facings) {
            if (! isset($shelves[$index])) {
                return false;
            }

            $splitProduct = clone $product;
            $splitProduct->setFacings($facings);

            if (! $shelves[$index]->addProduct($splitProduct)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, int>
     */
    protected function orderedShelfIndexes(int $totalShelves, int $idealIndex, int $maxDistance): array
    {
        if ($totalShelves <= 0) {
            return [];
        }

        $indexes = [];
        if ($idealIndex >= 0 && $idealIndex < $totalShelves) {
            $indexes[] = $idealIndex;
        }

        for ($distance = 1; $distance <= $maxDistance; $distance++) {
            $upper = $idealIndex + $distance;
            if ($upper < $totalShelves) {
                $indexes[] = $upper;
            }

            $lower = $idealIndex - $distance;
            if ($lower >= 0) {
                $indexes[] = $lower;
            }
        }

        return array_values(array_unique($indexes));
    }

    protected function isShelfEligibleForProduct(ShelfLayoutDTO $shelf, RankedProductDTO $product): bool
    {
        $productHeight = (float) ($product->product->height ?? 0);
        if ($productHeight > 0 && $productHeight > $shelf->height) {
            return false;
        }

        $productDepth = (float) ($product->product->depth ?? 0);
        if ($productDepth > 0 && $productDepth > $shelf->depth) {
            return false;
        }

        return true;
    }

    /**
     * @param  ShelfLayoutDTO[]  $shelves
     * @return SectionAllocationItemDTO[]
     */
    protected function shelvesToAllocationItems(array $shelves): array
    {
        $items = [];
        foreach ($shelves as $layout) {
            foreach ($layout->products as $ranked) {
                $items[] = new SectionAllocationItemDTO(
                    shelfId: $layout->id,
                    productId: $ranked->product->id,
                    facings: $ranked->facings,
                    productWidth: (float) ($ranked->product->width ?? 10),
                    productDepth: (float) ($ranked->product->depth ?? 0),
                    productHeight: (float) ($ranked->product->height ?? 0),
                );
            }
        }

        return $items;
    }
}
