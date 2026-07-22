<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Template;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductOrderingService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductSizeResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ShelfZoneResolver;
use Callcocam\LaravelRaptorPlannerate\Enums\FacingExpansion;
use Callcocam\LaravelRaptorPlannerate\Enums\FlowDirection;
use Callcocam\LaravelRaptorPlannerate\Enums\PlacementFailureReason;
use Callcocam\LaravelRaptorPlannerate\Enums\SpaceFallback;
use Callcocam\LaravelRaptorPlannerate\Enums\ZonePriority;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class SlotReviewAnalysisService
{
    public function __construct(
        private ProductWidthResolver $widthResolver,
        private ProductSizeResolver $sizeResolver,
        private ProductOrderingService $orderingService,
    ) {}

    /**
     * Simulates the full sequential allocation for this slot using the same logic as
     * TemplatePlacementEngine — visual_criteria, facing_expansion, participation limits,
     * mandatory/blocked rules, and zone priority — without persisting anything.
     *
     * @param  EloquentCollection<int, PlanogramTemplateSlot>|null  $allCategorySlots
     * @return array{
     *   summary: array<string, mixed>,
     *   rows: list<array<string, mixed>>
     * }
     */
    public function analyze(
        PlanogramTemplateSlot $slot,
        float $shelfWidthCm = 100.0,
        ?EloquentCollection $allCategorySlots = null,
    ): array {
        $slot->loadMissing(['subtemplate.slots']);

        $categoryIds = $slot->category_id
            ? Category::getDescendantIds($slot->category_id)
            : [];

        $allCandidates = Product::query()
            ->select([
                'id', 'name', 'ean', 'codigo_erp', 'brand',
                'category_id', 'status', 'packaging_content',
                'weight', 'url', 'width', 'height', 'depth', 'unit',
                'price', 'packaging_type',
            ])
            // `current_stock` sai de `product_store` (métrica POR LOJA) e por isso
            // não está na lista acima. Template não é por loja, então a expansão de
            // facings por estoque usa o consolidado de todas as lojas.
            // withStoreMetrics() depois do select(): select() zeraria os subselects.
            ->withStoreMetrics()
            ->when(
                $categoryIds !== [],
                fn ($q) => $q->whereIn('category_id', $categoryIds),
                fn ($q) => $q->whereRaw('1 = 0'),
            )
            ->where('status', '!=', 'draft')
            ->get();

        $productIds = $allCandidates->pluck('id')->filter()->map(fn ($id) => (string) $id)->values();
        $eans = $allCandidates->pluck('ean')
            ->filter(fn (mixed $v): bool => is_string($v) && trim($v) !== '')
            ->values();

        // ── Sales metrics: has_sales, ABC map, zone metrics ──────────────────────
        [$salesProductIds, $salesEans, $abcClassMap, $zoneMetricsMap] = $this->loadSalesMetrics($productIds, $eans);

        // ── Mandatory / blocked rules ─────────────────────────────────────────────
        [$mandatoryProductIds, $blockedProductIds, $blockedBrands, $blockedSubcategoryIds] = $this->loadProductRules();

        // ── Subtemplate zone priority and shelf count ─────────────────────────────
        $subtemplate = $slot->subtemplate;
        $hotZonePriority = $subtemplate?->hot_zone_priority ?? ZonePriority::None;
        $coldZonePriority = $subtemplate?->cold_zone_priority ?? ZonePriority::None;
        $flowDirection = $subtemplate?->flow_direction ?? FlowDirection::LeftToRight;
        $numShelves = max(1, $subtemplate?->slots->max('shelf_order') ?? 1);
        $shelfPosition = $numShelves - $slot->shelf_order;
        $zone = ShelfZoneResolver::resolve($shelfPosition, $numShelves);
        $zonePriority = match ($zone) {
            'hot' => $hotZonePriority,
            'cold' => $coldZonePriority,
            default => ZonePriority::None,
        };

        // ── Separate blocked candidates ───────────────────────────────────────────
        $totalProducts = $allCandidates->count();
        $blockedCandidates = $allCandidates->filter(
            fn ($p) => $this->isBlocked($p, $blockedProductIds, $blockedBrands, $blockedSubcategoryIds),
        );
        $validCandidates = $allCandidates->reject(
            fn ($p) => $this->isBlocked($p, $blockedProductIds, $blockedBrands, $blockedSubcategoryIds),
        )->values();

        // ── Determine which products reach this slot (multi-shelf simulation) ─────
        $previouslyPlacedCount = 0;
        $candidates = $validCandidates;
        $afterSlots = collect();

        if ($allCategorySlots !== null && $allCategorySlots->count() > 1) {
            $beforeSlots = $allCategorySlots
                ->filter(fn (PlanogramTemplateSlot $s): bool => $s->shelf_order < $slot->shelf_order)
                ->sortBy('shelf_order')
                ->values();

            $afterSlots = $allCategorySlots
                ->filter(fn (PlanogramTemplateSlot $s): bool => $s->shelf_order > $slot->shelf_order)
                ->sortBy('shelf_order')
                ->values();

            $remaining = $validCandidates;

            foreach ($beforeSlots as $prevSlot) {
                $prevOrdered = $this->orderCandidates($prevSlot, $remaining, $abcClassMap, $zoneMetricsMap, $mandatoryProductIds, $numShelves, $hotZonePriority, $coldZonePriority);
                $prevOccupied = 0.0;
                $notFit = collect();

                foreach ($prevOrdered as $product) {
                    $rawWidth = isset($product->width) ? (float) $product->width : null;
                    if ($rawWidth === null || $rawWidth <= 0) {
                        continue;
                    }

                    $facing = max(1, $prevSlot->min_facings);
                    $required = (int) round($this->widthResolver->resolve($product) * $facing);

                    if ($prevOccupied + $required <= $shelfWidthCm) {
                        $prevOccupied += $required;
                        $previouslyPlacedCount++;
                    } else {
                        $notFit->push($product);
                    }
                }

                $remaining = $notFit;
            }

            $candidates = $remaining;
        }

        // ── Phase 1: order and fill this slot ─────────────────────────────────────
        $ordered = $this->orderCandidates($slot, $candidates, $abcClassMap, $zoneMetricsMap, $mandatoryProductIds, $numShelves, $hotZonePriority, $coldZonePriority);
        $rows = [];
        $occupiedWidth = 0.0;
        $rejectedIndexes = [];

        /** @var array<int, array{product: Product, facings: int, singleWidth: float, ordering: int}> $placedItems */
        $placedItems = [];
        $ordering = 0;

        foreach ($ordered as $product) {
            $rawWidth = isset($product->width) ? (float) $product->width : null;

            if ($rawWidth === null || $rawWidth <= 0) {
                $rows[$product->id] = $this->buildRow($product, 'fora', PlacementFailureReason::MissingDimensions->label(), 0, 0, 0, $salesProductIds, $salesEans, $abcClassMap, $mandatoryProductIds);

                continue;
            }

            $facing = max(1, $slot->min_facings);
            $singleWidth = $this->widthResolver->resolve($product);
            $requiredWidth = (int) round($singleWidth * $facing);

            if ($occupiedWidth + $requiredWidth <= $shelfWidthCm) {
                $placedItems[$product->id] = [
                    'product' => $product,
                    'facings' => $facing,
                    'singleWidth' => $singleWidth,
                    'ordering' => $ordering++,
                ];
                $occupiedWidth += $requiredWidth;
            } else {
                $rows[$product->id] = $this->buildRow($product, 'fora', PlacementFailureReason::NoHorizontalSpace->label(), $facing, $requiredWidth, 0, $salesProductIds, $salesEans, $abcClassMap, $mandatoryProductIds);
                $rejectedIndexes[] = $product->id;
            }
        }

        // ── Phase 2: expand facings ───────────────────────────────────────────────
        if ($slot->facing_expansion !== FacingExpansion::None && $placedItems !== []) {
            [$placedItems, $occupiedWidth] = $this->expandFacings($placedItems, $slot, $shelfWidthCm, $occupiedWidth, $abcClassMap);
        }

        // ── Phase 3: space_fallback reduce_facings ────────────────────────────────
        if ($slot->space_fallback === SpaceFallback::ReduceFacings && $rejectedIndexes !== []) {
            $remainingWidth = max(0.0, $shelfWidthCm - $occupiedWidth);
            foreach ($rejectedIndexes as $productId) {
                $row = $rows[$productId] ?? null;
                if ($row === null) {
                    continue;
                }
                $product = $ordered->first(fn (Product $p) => (string) $p->id === $productId);
                if ($product === null) {
                    continue;
                }
                $widthOneFacing = (int) round($this->widthResolver->resolve($product));
                if ($widthOneFacing <= 0 || $widthOneFacing > $remainingWidth) {
                    continue;
                }
                $remainingWidth -= $widthOneFacing;
                $occupiedWidth += $widthOneFacing;
                $placedItems[$productId] = [
                    'product' => $product,
                    'facings' => 1,
                    'singleWidth' => (float) $widthOneFacing,
                    'ordering' => $ordering++,
                ];
                unset($rows[$productId]);
            }
        }

        // ── Build placed rows with position ───────────────────────────────────────
        $x = 0.0;
        $placedByOrdering = collect($placedItems)->sortBy('ordering')->values();

        foreach ($placedByOrdering as $item) {
            $product = $item['product'];
            $facings = $item['facings'];
            $singleWidth = $item['singleWidth'];
            $width = (int) round($singleWidth * $facings);

            $rows[$product->id] = $this->buildRow(
                $product,
                'entrou',
                'Cabe na largura disponível',
                $facings,
                $width,
                (int) round($x),
                $salesProductIds,
                $salesEans,
                $abcClassMap,
                $mandatoryProductIds,
            );
            $x += $width;
        }

        // Apply RightToLeft mirroring to positions
        if ($flowDirection === FlowDirection::RightToLeft) {
            $totalWidth = (int) round($x);
            foreach ($rows as $id => $row) {
                if ($row['status'] === 'entrou') {
                    $rows[$id]['position_cm'] = $totalWidth - $row['position_cm'] - $row['required_width_cm'];
                }
            }
        }

        // ── Phase 4: blocked products rows ────────────────────────────────────────
        foreach ($blockedCandidates as $product) {
            $rows[$product->id] = $this->buildRow($product, 'fora', 'Bloqueado por regra', 0, 0, 0, $salesProductIds, $salesEans, $abcClassMap, $mandatoryProductIds);
        }

        // ── Phase 5: check subsequent slots for products still 'fora' ────────────
        if ($afterSlots->isNotEmpty()) {
            $stillFora = collect($rows)
                ->filter(fn (array $r): bool => $r['status'] === 'fora')
                ->map(fn (array $r) => $ordered->first(fn (Product $p): bool => (string) $p->id === $r['product_id']))
                ->filter()
                ->values();

            foreach ($afterSlots as $sibling) {
                if ($stillFora->isEmpty()) {
                    break;
                }

                $siblingOrdered = $this->orderCandidates($sibling, $stillFora, $abcClassMap, $zoneMetricsMap, $mandatoryProductIds, $numShelves, $hotZonePriority, $coldZonePriority);
                $siblingOccupied = 0.0;
                $nextFora = collect();

                foreach ($siblingOrdered as $product) {
                    $rawWidth = isset($product->width) ? (float) $product->width : null;
                    if ($rawWidth === null || $rawWidth <= 0) {
                        continue;
                    }

                    $facing = max(1, $sibling->min_facings);
                    $required = (int) round($this->widthResolver->resolve($product) * $facing);

                    if ($siblingOccupied + $required <= $shelfWidthCm) {
                        $siblingOccupied += $required;
                        $rows[(string) $product->id]['status'] = 'outro_slot';
                        $rows[(string) $product->id]['reason'] = "Prateleira {$sibling->shelf_order}";
                    } else {
                        $nextFora->push($product);
                    }
                }

                $stillFora = $nextFora;
            }
        }

        $finalRows = array_values($rows);
        $placedCount = collect($finalRows)->where('status', 'entrou')->count();
        $outroSlotCount = collect($finalRows)->where('status', 'outro_slot')->count();
        $rejectedCount = collect($finalRows)->where('status', 'fora')->count();

        return [
            'summary' => [
                'slot_id' => (string) $slot->id,
                'category_id' => $slot->category_id,
                'shelf_width_cm' => round($shelfWidthCm, 1),
                'occupied_width_cm' => round($occupiedWidth, 1),
                'free_width_cm' => round(max(0.0, $shelfWidthCm - $occupiedWidth), 1),
                'total_products' => $totalProducts,
                'previous_slots_placed' => $previouslyPlacedCount,
                'placed_products' => $placedCount,
                'outro_slot_products' => $outroSlotCount,
                'rejected_products' => $rejectedCount,
                'zone' => $zone,
                'num_shelves' => $numShelves,
                'missing_dimensions' => collect($finalRows)->where('status', 'fora')->where('reason', PlacementFailureReason::MissingDimensions->label())->count(),
            ],
            'rows' => $finalRows,
        ];
    }

    /**
     * Orders candidates using the full engine pipeline:
     * visual_criteria cascade → zone priority → mandatory first.
     *
     * @param  Collection<int, Product>  $products
     * @param  array<string, string>  $abcClassMap
     * @param  array<string, array{giro: float, margem: float}>  $zoneMetricsMap
     * @param  array<string, true>  $mandatoryProductIds
     * @return Collection<int, Product>
     */
    private function orderCandidates(
        PlanogramTemplateSlot $slot,
        Collection $products,
        array $abcClassMap,
        array $zoneMetricsMap,
        array $mandatoryProductIds,
        int $numShelves,
        ZonePriority $hotZonePriority,
        ZonePriority $coldZonePriority,
    ): Collection {
        $sorted = $this->orderingService->orderBySlot($products, $slot, $abcClassMap, $zoneMetricsMap);

        // Zone thermal ordering — applied last so it's the primary sort
        $shelfPosition = $numShelves - $slot->shelf_order;
        $zone = ShelfZoneResolver::resolve($shelfPosition, $numShelves);
        $zonePriority = match ($zone) {
            'hot' => $hotZonePriority,
            'cold' => $coldZonePriority,
            default => ZonePriority::None,
        };

        if ($zonePriority !== ZonePriority::None) {
            $sorted = $this->applyZonePriority($sorted, $zonePriority, $abcClassMap, $zoneMetricsMap);
        }

        // Mandatory products always first
        if (! empty($mandatoryProductIds)) {
            $sorted = $sorted->sortBy(fn ($p) => isset($mandatoryProductIds[$p->id]) ? 0 : 1);
        }

        return $sorted->values();
    }

    /**
     * Applies zone-based priority ordering.
     *
     * @param  Collection<int, Product>  $products
     * @param  array<string, string>  $abcClassMap
     * @param  array<string, array{giro: float, margem: float}>  $zoneMetricsMap
     * @return Collection<int, Product>
     */
    private function applyZonePriority(Collection $products, ZonePriority $priority, array $abcClassMap, array $zoneMetricsMap): Collection
    {
        return match ($priority) {
            ZonePriority::MaiorMargem => $products->sortByDesc(
                fn ($p) => (float) ($zoneMetricsMap[$p->id]['margem'] ?? 0),
            ),
            ZonePriority::MaiorGiro => $products->sortByDesc(
                fn ($p) => (float) ($zoneMetricsMap[$p->id]['giro'] ?? 0),
            ),
            ZonePriority::MaiorValorVendido => $products->sortByDesc(
                fn ($p) => (float) ($zoneMetricsMap[$p->id]['giro'] ?? 0) * (float) ($p->price ?? 0),
            ),
            ZonePriority::CurvaA => $products->sortBy(
                fn ($p) => match ($abcClassMap[$p->id] ?? 'C') {
                    'A' => 0,
                    'B' => 1,
                    'C' => 2,
                    default => 1,
                },
            ),
            ZonePriority::MenorMargem => $products->sortBy(
                fn ($p) => (float) ($zoneMetricsMap[$p->id]['margem'] ?? 0),
            ),
            ZonePriority::ComplementarFria => $products->sortBy(
                fn ($p) => match ($abcClassMap[$p->id] ?? 'A') {
                    'C' => 0,
                    'B' => 1,
                    'A' => 2,
                    default => 1,
                },
            ),
            ZonePriority::MaiorVolume => $products->sortByDesc(
                fn ($p) => $this->sizeResolver->resolve($p),
            ),
            ZonePriority::MenorPrioridade => $products->sortBy(
                fn ($p) => (float) ($zoneMetricsMap[$p->id]['giro'] ?? 0),
            ),
            default => $products,
        };
    }

    /**
     * Expands facings using leftover shelf space, respecting max_facings and participation limits.
     *
     * NOTA (divergência conhecida): diferente do TemplatePlacementEngine::expandFacings, este
     * preview NÃO aplica o teto de frentes por estoque alvo (use_target_stock). O serviço de
     * análise não carrega o targetStockMap, então a contagem de frentes prevista pode ficar
     * acima da real quando o slot usa estoque alvo. Follow-up: injetar targetStockMap +
     * profundidade aqui para espelhar o teto do engine.
     *
     * @param  array<string, array{product: Product, facings: int, singleWidth: float, ordering: int}>  $placedItems
     * @param  array<string, string>  $abcClassMap
     * @return array{0: array<string, array{product: Product, facings: int, singleWidth: float, ordering: int}>, 1: float}
     */
    private function expandFacings(array $placedItems, PlanogramTemplateSlot $slot, float $available, float $occupied, array $abcClassMap): array
    {
        $maxFacings = max($slot->max_facings, 1);
        $remainingWidth = $available - $occupied;

        if ($remainingWidth <= 0 || $maxFacings <= 1) {
            return [$placedItems, $occupied];
        }

        $expansionOrder = $this->expansionOrder($placedItems, $slot->facing_expansion, $abcClassMap);
        $changed = true;

        while ($changed && $remainingWidth > 0) {
            $changed = false;

            foreach ($expansionOrder as $productId) {
                if (! isset($placedItems[$productId])) {
                    continue;
                }

                if ($placedItems[$productId]['facings'] >= $maxFacings) {
                    continue;
                }

                $singleWidth = $placedItems[$productId]['singleWidth'];

                if ($remainingWidth < $singleWidth) {
                    continue;
                }

                if ($this->violatesParticipationLimit($placedItems, $productId, $slot, $available)) {
                    continue;
                }

                $placedItems[$productId]['facings']++;
                $remainingWidth -= $singleWidth;
                $occupied += $singleWidth;
                $changed = true;
            }
        }

        return [$placedItems, $occupied];
    }

    /**
     * Returns product IDs ordered by expansion priority.
     *
     * @param  array<string, array{product: Product, facings: int, singleWidth: float, ordering: int}>  $placedItems
     * @param  array<string, string>  $abcClassMap
     * @return list<string>
     */
    private function expansionOrder(array $placedItems, FacingExpansion $mode, array $abcClassMap): array
    {
        $ids = array_keys($placedItems);

        if ($mode === FacingExpansion::CurrentStock) {
            usort($ids, fn (string $a, string $b): int => (float) ($placedItems[$b]['product']->current_stock ?? 0)
                <=> (float) ($placedItems[$a]['product']->current_stock ?? 0)
            );
        }

        // Score, Equal, TargetStock (no target stock map): use existing order
        return $ids;
    }

    /**
     * Checks if giving +1 facing to $productId would violate participation limits.
     *
     * @param  array<string, array{product: Product, facings: int, singleWidth: float, ordering: int}>  $placedItems
     */
    private function violatesParticipationLimit(array $placedItems, string $productId, PlanogramTemplateSlot $slot, float $available): bool
    {
        if ($available <= 0 || ! isset($placedItems[$productId])) {
            return false;
        }

        $item = $placedItems[$productId];
        $singleWidth = $item['singleWidth'];
        $newFacings = $item['facings'] + 1;
        $newSkuWidth = $singleWidth * $newFacings;

        if ($slot->max_share_per_sku !== null && $slot->max_share_per_sku > 0) {
            if (($newSkuWidth / $available) * 100 > $slot->max_share_per_sku) {
                return true;
            }
        }

        if ($slot->max_share_per_brand !== null && $slot->max_share_per_brand > 0) {
            $brand = $item['product']->brand ?? null;
            $brandWidth = 0.0;

            foreach ($placedItems as $pid => $p) {
                if ($pid !== $productId && ($p['product']->brand ?? null) === $brand) {
                    $brandWidth += $p['singleWidth'] * $p['facings'];
                }
            }

            $brandWidth += $singleWidth * $item['facings'];

            if ((($brandWidth + $singleWidth) / $available) * 100 > $slot->max_share_per_brand) {
                return true;
            }
        }

        if ($slot->max_share_per_subcategory !== null && $slot->max_share_per_subcategory > 0) {
            $subcatId = $item['product']->category_id ?? null;
            $subcatWidth = 0.0;

            foreach ($placedItems as $pid => $p) {
                if ($pid !== $productId && ($p['product']->category_id ?? null) === $subcatId) {
                    $subcatWidth += $p['singleWidth'] * $p['facings'];
                }
            }

            $subcatWidth += $singleWidth * $item['facings'];

            if ((($subcatWidth + $singleWidth) / $available) * 100 > $slot->max_share_per_subcategory) {
                return true;
            }
        }

        return false;
    }

    private function isBlocked(mixed $product, array $blockedProductIds, array $blockedBrands, array $blockedSubcategoryIds): bool
    {
        if (isset($blockedProductIds[(string) $product->id])) {
            return true;
        }

        $brand = $product->brand ?? null;
        if ($brand !== null && isset($blockedBrands[$brand])) {
            return true;
        }

        $categoryId = $product->category_id ?? null;
        if ($categoryId !== null && isset($blockedSubcategoryIds[$categoryId])) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<int, string>  $salesProductIds
     * @param  array<int, string>  $salesEans
     * @param  array<string, string>  $abcClassMap
     * @param  array<string, true>  $mandatoryProductIds
     * @return array<string, mixed>
     */
    private function buildRow(
        Product $product,
        string $status,
        string $reason,
        int $facing,
        int $requiredWidth,
        int $positionCm,
        array $salesProductIds,
        array $salesEans,
        array $abcClassMap,
        array $mandatoryProductIds,
    ): array {
        return [
            'product_id' => (string) $product->id,
            'name' => (string) $product->name,
            'ean' => (string) ($product->ean ?? ''),
            'codigo_erp' => (string) ($product->codigo_erp ?? ''),
            'brand' => (string) ($product->brand ?? ''),
            'has_sales' => in_array((string) $product->id, $salesProductIds, true)
                || in_array((string) ($product->ean ?? ''), $salesEans, true),
            'dimensions' => $this->formatDimensions($product),
            'status' => $status,
            'reason' => $reason,
            'facing_used' => $facing,
            'required_width_cm' => $requiredWidth,
            'position_cm' => $positionCm,
            'abc_class' => $abcClassMap[(string) $product->id] ?? null,
            'is_mandatory' => isset($mandatoryProductIds[(string) $product->id]),
            'url' => Storage::disk('public')->url($product->url ?? ''),
        ];
    }

    /**
     * Loads sales metrics from the Sale model.
     * Returns: [salesProductIds, salesEans, abcClassMap, zoneMetricsMap]
     *
     * @param  Collection<int, string>  $productIds
     * @param  Collection<int, string>  $eans
     * @return array{
     *   0: list<string>,
     *   1: list<string>,
     *   2: array<string, string>,
     *   3: array<string, array{giro: float, margem: float}>
     * }
     */
    private function loadSalesMetrics(Collection $productIds, Collection $eans): array
    {
        if ($productIds->isEmpty()) {
            return [[], [], [], []];
        }

        $metrics = Sale::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->selectRaw('CAST(product_id AS VARCHAR) as pid, SUM(total_sale_quantity) as total_qty, SUM(margem_contribuicao) as total_margem')
            ->get()
            ->keyBy('pid');

        $salesEans = Sale::query()
            ->whereIn('ean', $eans)
            ->whereNotNull('ean')
            ->distinct()
            ->pluck('ean')
            ->map(fn (mixed $e): string => (string) $e)
            ->values()
            ->all();

        $salesProductIds = $metrics->keys()->all();

        // ── Zone metrics map ──────────────────────────────────────────────────────
        $zoneMetricsMap = $metrics->mapWithKeys(fn ($row) => [
            (string) $row->pid => [
                'giro' => (float) ($row->total_qty ?? 0),
                'margem' => (float) ($row->total_margem ?? 0),
            ],
        ])->all();

        // ── ABC classification by sales quantity quantiles ────────────────────────
        $abcClassMap = [];

        if ($metrics->isNotEmpty()) {
            $ranked = $metrics->sortByDesc('total_qty')->keys()->values();
            $total = $ranked->count();
            $thresholdA = max(1, (int) ceil($total * 0.20));
            $thresholdAB = max($thresholdA, (int) ceil($total * 0.50));

            foreach ($ranked as $index => $pid) {
                $abcClassMap[(string) $pid] = match (true) {
                    $index < $thresholdA => 'A',
                    $index < $thresholdAB => 'B',
                    default => 'C',
                };
            }
        }

        return [$salesProductIds, $salesEans, $abcClassMap, $zoneMetricsMap];
    }

    /**
     * Loads mandatory/blocked product rules for the current tenant.
     * Returns: [mandatoryProductIds, blockedProductIds, blockedBrands, blockedSubcategoryIds]
     *
     * @return array{
     *   0: array<string, true>,
     *   1: array<string, true>,
     *   2: array<string, true>,
     *   3: array<string, true>
     * }
     */
    private function loadProductRules(): array
    {
        $mandatoryProductIds = [];
        $blockedProductIds = [];
        $blockedBrands = [];
        $blockedSubcategoryIds = [];

        try {
            $rows = DB::connection('tenant')
                ->table('planogram_product_rules')
                ->whereNull('deleted_at')
                ->get(['type', 'product_id', 'brand', 'subcategory_id']);

            foreach ($rows as $row) {
                if ($row->type === 'mandatory' && $row->product_id !== null) {
                    $mandatoryProductIds[$row->product_id] = true;
                }

                if ($row->type === 'blocked') {
                    if ($row->product_id !== null) {
                        $blockedProductIds[$row->product_id] = true;
                    }
                    if ($row->brand !== null) {
                        $blockedBrands[$row->brand] = true;
                    }
                    if ($row->subcategory_id !== null) {
                        $blockedSubcategoryIds[$row->subcategory_id] = true;
                    }
                }
            }
        } catch (\Throwable) {
            // Tenant connection not available in tests (SQLite) — rules are empty
        }

        return [$mandatoryProductIds, $blockedProductIds, $blockedBrands, $blockedSubcategoryIds];
    }

    private function formatDimensions(Product $product): string
    {
        $width = (float) ($product->width ?? 0);
        $height = (float) ($product->height ?? 0);
        $depth = (float) ($product->depth ?? 0);

        if ($width <= 0 || $height <= 0 || $depth <= 0) {
            return '-';
        }

        $unit = (string) ($product->unit ?? 'cm');

        return sprintf(
            '%sx%sx%s %s',
            number_format($width, 1, '.', ''),
            number_format($height, 1, '.', ''),
            number_format($depth, 1, '.', ''),
            $unit,
        );
    }
}
