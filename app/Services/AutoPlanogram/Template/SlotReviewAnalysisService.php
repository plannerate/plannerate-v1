<?php

namespace App\Services\AutoPlanogram\Template;

use App\Enums\BrandExposure;
use App\Enums\PlacementFailureReason;
use App\Enums\PriceOrder;
use App\Enums\SizeOrder;
use App\Models\PlanogramTemplateSlot;
use App\Models\Product;
use App\Models\Sale;
use App\Services\AutoPlanogram\ProductWidthResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final readonly class SlotReviewAnalysisService
{
    public function __construct(private ProductWidthResolver $widthResolver) {}

    /**
     * @return array{
     *   summary: array{
     *     slot_id: string,
     *     grouping: string,
     *     shelf_width_cm: float,
     *     occupied_width_cm: float,
     *     free_width_cm: float,
     *     total_products: int,
     *     placed_products: int,
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
     *     status: 'entrou'|'fora',
     *     reason: string,
     *     facing_used: int,
     *     required_width_cm: int
     *   }>
     * }
     */
    public function analyze(PlanogramTemplateSlot $slot, float $shelfWidthCm = 100.0): array
    {
        $candidates = Product::query()
            ->select([
                'id',
                'name',
                'ean',
                'codigo_erp',
                'brand',
                'grouping',
                'grouping_normalized',
                'status',
                'packaging_content',
                'url',
                'width',
                'height',
                'depth',
                'unit',
            ])
            ->where('grouping_normalized', $slot->grouping_normalized)
            ->where('status', '!=', 'draft')
            ->get();

        $productIds = $candidates->pluck('id')->filter()->values();
        $eans = $candidates->pluck('ean')->filter(fn (mixed $ean): bool => is_string($ean) && trim($ean) !== '')->values();

        $salesProductIds = Sale::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        $salesEans = Sale::query()
            ->whereIn('ean', $eans)
            ->whereNotNull('ean')
            ->distinct()
            ->pluck('ean')
            ->map(fn (mixed $ean): string => (string) $ean)
            ->values()
            ->all();

        $ordered = $this->orderCandidates($candidates, $slot);

        $rows = [];
        $occupiedWidth = 0.0;
        $rejectedIndexes = [];

        foreach ($ordered as $index => $product) {
            $facing = max(1, $slot->min_facings);
            $productWidth = $this->widthResolver->resolve($product);
            $requiredWidth = (int) round($productWidth * $facing);

            if ($occupiedWidth + $requiredWidth <= $shelfWidthCm) {
                $occupiedWidth += $requiredWidth;
                $rows[] = [
                    'product_id' => (string) $product->id,
                    'name' => (string) $product->name,
                    'ean' => (string) ($product->ean ?? ''),
                    'codigo_erp' => (string) ($product->codigo_erp ?? ''),
                    'brand' => (string) ($product->brand ?? ''),
                    'has_sales' => in_array((string) $product->id, $salesProductIds, true)
                        || in_array((string) ($product->ean ?? ''), $salesEans, true),
                    'dimensions' => $this->formatDimensions($product),
                    'status' => 'entrou',
                    'reason' => 'Cabe na largura disponível',
                    'facing_used' => $facing,
                    'required_width_cm' => $requiredWidth,
                    'url' => Storage::disk('public')->url($product->url ?? ''),
                ];

                continue;
            }

            $rows[] = [
                'product_id' => (string) $product->id,
                'name' => (string) $product->name,
                'ean' => (string) ($product->ean ?? ''),
                'codigo_erp' => (string) ($product->codigo_erp ?? ''),
                'brand' => (string) ($product->brand ?? ''),
                'has_sales' => in_array((string) $product->id, $salesProductIds, true)
                    || in_array((string) ($product->ean ?? ''), $salesEans, true),
                'dimensions' => $this->formatDimensions($product),
                'status' => 'fora',
                'reason' => PlacementFailureReason::NoHorizontalSpace->label(),
                'facing_used' => $facing,
                'required_width_cm' => $requiredWidth,
                'url' => Storage::disk('public')->url($product->url ?? ''),
            ];
            $rejectedIndexes[] = $index;
        }

        if ($slot->space_fallback->value === 'reduce_facings' && $rejectedIndexes !== []) {
            $remainingWidth = max(0.0, $shelfWidthCm - $occupiedWidth);
            foreach ($rejectedIndexes as $index) {
                $product = $ordered[$index];
                $widthOneFacing = (int) round($this->widthResolver->resolve($product));

                if ($widthOneFacing <= 0 || $widthOneFacing > $remainingWidth) {
                    continue;
                }

                $remainingWidth -= $widthOneFacing;
                $occupiedWidth += $widthOneFacing;
                $rows[$index]['status'] = 'entrou';
                $rows[$index]['reason'] = 'Entrou via fallback (reduce_facings)';
                $rows[$index]['facing_used'] = 1;
                $rows[$index]['required_width_cm'] = $widthOneFacing;
            }
        }

        $placedProducts = collect($rows)->where('status', 'entrou')->count();
        $rejectedProducts = collect($rows)->where('status', 'fora')->count();

        return [
            'summary' => [
                'slot_id' => (string) $slot->id,
                'grouping' => (string) $slot->grouping,
                'shelf_width_cm' => round($shelfWidthCm, 1),
                'occupied_width_cm' => round($occupiedWidth, 1),
                'free_width_cm' => round(max(0.0, $shelfWidthCm - $occupiedWidth), 1),
                'total_products' => count($rows),
                'placed_products' => $placedProducts,
                'rejected_products' => $rejectedProducts,
            ],
            'rows' => array_values($rows),
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
