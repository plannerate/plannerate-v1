<?php

namespace App\Services\AutoPlanogram\Template;

use App\Enums\BrandExposure;
use App\Enums\PlacementFailureReason;
use App\Enums\PriceOrder;
use App\Enums\SizeOrder;
use App\Models\Category;
use App\Models\PlanogramTemplateSlot;
use App\Models\Product;
use App\Models\Sale;
use App\Services\AutoPlanogram\ProductWidthResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final readonly class SlotReviewAnalysisService
{
    public function __construct(private ProductWidthResolver $widthResolver) {}

    /**
     * Simulates the full sequential allocation for this slot's category.
     *
     * When allCategorySlots is provided (multiple shelves sharing the same category_id):
     *   - Slots before the target are simulated first to consume their share of products.
     *   - The target slot only receives products that weren't placed in earlier shelves.
     *   - Products that fit in subsequent shelves are marked 'outro_slot'.
     *
     * @param  EloquentCollection<int, PlanogramTemplateSlot>|null  $allCategorySlots
     *                                                                                 All slots (including $slot) sharing the same category_id, ordered by shelf_order.
     * @return array{
     *   summary: array{
     *     slot_id: string,
     *     category_id: string|null,
     *     shelf_width_cm: float,
     *     occupied_width_cm: float,
     *     free_width_cm: float,
     *     total_products: int,
     *     previous_slots_placed: int,
     *     placed_products: int,
     *     outro_slot_products: int,
     *     rejected_products: int
     *   },
     *   rows: list<array{
     *     product_id: string,
     *     name: string,
     *     ean: string,
     *     codigo_erp: string,
     *     brand: string,
     *     has_sales: bool,
     *     dimensions: string,
     *     status: 'entrou'|'fora'|'outro_slot',
     *     reason: string,
     *     facing_used: int,
     *     required_width_cm: int
     *   }>
     * }
     */
    public function analyze(
        PlanogramTemplateSlot $slot,
        float $shelfWidthCm = 100.0,
        ?EloquentCollection $allCategorySlots = null,
    ): array {
        $categoryIds = $slot->category_id
            ? Category::getDescendantIds($slot->category_id)
            : [];

        $allCandidates = Product::query()
            ->select([
                'id', 'name', 'ean', 'codigo_erp', 'brand',
                'category_id', 'status', 'packaging_content',
                'url', 'width', 'height', 'depth', 'unit',
            ])
            ->when(
                $categoryIds !== [],
                fn ($q) => $q->whereIn('category_id', $categoryIds),
                fn ($q) => $q->whereRaw('1 = 0'),
            )
            ->where('status', '!=', 'draft')
            ->get();

        $productIds = $allCandidates->pluck('id')->filter()->values();
        $eans = $allCandidates->pluck('ean')
            ->filter(fn (mixed $v): bool => is_string($v) && trim($v) !== '')
            ->values();

        $salesProductIds = Sale::query()
            ->whereIn('product_id', $productIds)->whereNotNull('product_id')
            ->distinct()->pluck('product_id')
            ->map(fn (mixed $id): string => (string) $id)->values()->all();

        $salesEans = Sale::query()
            ->whereIn('ean', $eans)->whereNotNull('ean')
            ->distinct()->pluck('ean')
            ->map(fn (mixed $ean): string => (string) $ean)->values()->all();

        // ── Determine which products actually reach this slot ─────────────────
        $totalProducts = $allCandidates->count();
        $previouslyPlacedCount = 0;
        $candidates = $allCandidates;
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

            $remaining = $allCandidates;

            foreach ($beforeSlots as $prevSlot) {
                $prevOrdered = $this->orderCandidates($remaining, $prevSlot);
                $prevOccupied = 0.0;
                $notFit = collect();

                foreach ($prevOrdered as $product) {
                    $rawWidth = isset($product->width) ? (float) $product->width : null;
                    if ($rawWidth === null || $rawWidth <= 0) {
                        continue; // MissingDimensions — never a candidate for any slot
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

        // ── Phase 1: fill this slot ───────────────────────────────────────────
        $ordered = $this->orderCandidates($candidates, $slot);
        $rows = [];
        $occupiedWidth = 0.0;
        $rejectedIndexes = [];

        foreach ($ordered as $index => $product) {
            $rawWidth = isset($product->width) ? (float) $product->width : null;
            if ($rawWidth === null || $rawWidth <= 0) {
                $rows[] = $this->buildRow($product, 'fora', PlacementFailureReason::MissingDimensions->label(), 0, 0, $salesProductIds, $salesEans);

                continue;
            }

            $facing = max(1, $slot->min_facings);
            $productWidth = $this->widthResolver->resolve($product);
            $requiredWidth = (int) round($productWidth * $facing);

            if ($occupiedWidth + $requiredWidth <= $shelfWidthCm) {
                $occupiedWidth += $requiredWidth;
                $rows[] = $this->buildRow($product, 'entrou', 'Cabe na largura disponível', $facing, $requiredWidth, $salesProductIds, $salesEans);

                continue;
            }

            $rows[] = $this->buildRow($product, 'fora', PlacementFailureReason::NoHorizontalSpace->label(), $facing, $requiredWidth, $salesProductIds, $salesEans);
            $rejectedIndexes[] = $index;
        }

        // space_fallback: reduce_facings — retry with 1 facing
        if ($slot->space_fallback->value === 'reduce_facings' && $rejectedIndexes !== []) {
            $remainingWidth = max(0.0, $shelfWidthCm - $occupiedWidth);
            foreach ($rejectedIndexes as $idx) {
                $product = $ordered[$idx];
                $widthOneFacing = (int) round($this->widthResolver->resolve($product));

                if ($widthOneFacing <= 0 || $widthOneFacing > $remainingWidth) {
                    continue;
                }

                $remainingWidth -= $widthOneFacing;
                $occupiedWidth += $widthOneFacing;
                $rows[$idx]['status'] = 'entrou';
                $rows[$idx]['reason'] = 'Entrou via fallback (reduce_facings)';
                $rows[$idx]['facing_used'] = 1;
                $rows[$idx]['required_width_cm'] = $widthOneFacing;
            }
        }

        // ── Phase 2: check subsequent slots for products still 'fora' ─────────
        if ($afterSlots->isNotEmpty()) {
            $rowIndexByProductId = [];
            foreach ($rows as $idx => $row) {
                $rowIndexByProductId[$row['product_id']] = $idx;
            }

            $stillFora = collect($rows)
                ->filter(fn (array $r): bool => $r['status'] === 'fora')
                ->map(fn (array $r): ?Product => $ordered->first(fn (Product $p): bool => (string) $p->id === $r['product_id']))
                ->filter()
                ->values();

            foreach ($afterSlots as $sibling) {
                if ($stillFora->isEmpty()) {
                    break;
                }

                $siblingOrdered = $this->orderCandidates($stillFora, $sibling);
                $siblingOccupied = 0.0;
                $nextFora = collect();

                foreach ($siblingOrdered as $product) {
                    $rawWidth = isset($product->width) ? (float) $product->width : null;
                    if ($rawWidth === null || $rawWidth <= 0) {
                        continue; // MissingDimensions — stays 'fora', never 'outro_slot'
                    }

                    $facing = max(1, $sibling->min_facings);
                    $required = (int) round($this->widthResolver->resolve($product) * $facing);

                    if ($siblingOccupied + $required <= $shelfWidthCm) {
                        $siblingOccupied += $required;
                        $rowIdx = $rowIndexByProductId[(string) $product->id] ?? null;
                        if ($rowIdx !== null) {
                            $rows[$rowIdx]['status'] = 'outro_slot';
                            $rows[$rowIdx]['reason'] = "Prateleira {$sibling->shelf_order}";
                        }
                    } else {
                        $nextFora->push($product);
                    }
                }

                $stillFora = $nextFora;
            }
        }

        $placedProducts = collect($rows)->where('status', 'entrou')->count();
        $outroSlotProducts = collect($rows)->where('status', 'outro_slot')->count();
        $rejectedProducts = collect($rows)->where('status', 'fora')->count();

        return [
            'summary' => [
                'slot_id' => (string) $slot->id,
                'category_id' => $slot->category_id,
                'shelf_width_cm' => round($shelfWidthCm, 1),
                'occupied_width_cm' => round($occupiedWidth, 1),
                'free_width_cm' => round(max(0.0, $shelfWidthCm - $occupiedWidth), 1),
                'total_products' => $totalProducts,
                'previous_slots_placed' => $previouslyPlacedCount,
                'placed_products' => $placedProducts,
                'outro_slot_products' => $outroSlotProducts,
                'rejected_products' => $rejectedProducts,
            ],
            'rows' => array_values($rows),
        ];
    }

    /**
     * @param  array<int, string>  $salesProductIds
     * @param  array<int, string>  $salesEans
     * @return array<string, mixed>
     */
    private function buildRow(
        Product $product,
        string $status,
        string $reason,
        int $facing,
        int $requiredWidth,
        array $salesProductIds,
        array $salesEans,
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
            'url' => Storage::disk('public')->url($product->url ?? ''),
        ];
    }

    /** @param Collection<int, Product> $products */
    private function orderCandidates(Collection $products, PlanogramTemplateSlot $slot): Collection
    {
        $sorted = $products;

        if ($slot->size_order !== SizeOrder::None) {
            $sorted = $sorted->sortBy(
                fn (Product $product): float => $this->parseSize((string) ($product->package_content ?? '0')),
                SORT_NUMERIC,
                $slot->size_order === SizeOrder::Desc,
            );
        }

        if ($slot->price_order !== PriceOrder::None && $products->first()?->price !== null) {
            $sorted = $sorted->sortBy(
                fn (Product $product): float => (float) ($product->price ?? 0),
                SORT_NUMERIC,
                $slot->price_order === PriceOrder::Desc,
            );
        }

        if ($slot->brand_exposure === BrandExposure::Vertical) {
            $sorted = $sorted->groupBy(fn (Product $product): string => $product->brand ?? 'SEM MARCA')->flatten(1);
        }

        return $sorted->values();
    }

    private function parseSize(string $content): float
    {
        preg_match('/[\d.]+/', $content, $matches);
        $value = (float) ($matches[0] ?? 0);
        $unit = strtolower((string) preg_replace('/[\d. ]+/', '', $content));

        return match ($unit) {
            'ml' => $value / 1000,
            'g' => $value / 1000,
            'l', 'kg' => $value,
            default => $value,
        };
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
