<?php

namespace App\Services\AutoPlanogram\Grouping;

use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ProductBlock;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Support\Collection;

final class HierarchicalBlockGrouper implements BlockGrouperInterface
{
    /**
     * @param  Collection<int, ScoredProduct>  $scoredProducts
     * @return Collection<int, ProductBlock>
     */
    public function group(Collection $scoredProducts, PlacementSettings $settings): Collection
    {
        $blockLevel = $settings->resolvedBlockHierarchyLevel();
        $adjacencyLevel = $settings->resolvedAdjacencyHierarchyLevel();

        return $scoredProducts
            ->groupBy(function (ScoredProduct $scoredProduct) use ($blockLevel): string {
                $resolvedCategory = $this->resolveCategoryAtLevel($scoredProduct->product, $blockLevel);

                return $resolvedCategory?->id ?? 'singleton:'.$scoredProduct->product->id;
            })
            ->map(function (Collection $children, string $key) use ($blockLevel, $adjacencyLevel): ProductBlock {
                $firstProduct = $children->first()?->product;
                $totalWeight = (float) $children->sum(fn (ScoredProduct $product): float => (float) ($product->metadata['estimated_facing'] ?? 1));
                $aggregateScore = $totalWeight > 0
                    ? $children->sum(fn (ScoredProduct $product): float => $product->score * (float) ($product->metadata['estimated_facing'] ?? 1)) / $totalWeight
                    : 0.0;

                $blockCategory = $firstProduct ? $this->resolveCategoryAtLevel($firstProduct, $blockLevel) : null;
                $adjacencyCategory = $firstProduct ? $this->resolveCategoryAtLevel($firstProduct, $adjacencyLevel) : null;

                return new ProductBlock(
                    children: $children->values(),
                    aggregateScore: $aggregateScore,
                    groupingKey: $key,
                    totalWidthEstimate: $this->estimateBlockWidth($children),
                    blockHierarchyLevel: $blockLevel,
                    adjacencyCategoryId: $adjacencyCategory?->id,
                    isPlaceholder: $blockCategory === null,
                );
            })
            ->sortByDesc('aggregateScore')
            ->values();
    }

    private function resolveCategoryAtLevel(Product $product, int $targetLevel): ?Category
    {
        $category = $product->relationLoaded('category') ? $product->category : $product->category()->first();

        while ($category instanceof Category && $category->hierarchy_position > $targetLevel) {
            $category = $category->relationLoaded('parent') ? $category->parent : $category->parent()->first();
        }

        if (! $category instanceof Category || (bool) $category->is_placeholder) {
            return null;
        }

        return $category->hierarchy_position === $targetLevel ? $category : null;
    }

    /**
     * @param  Collection<int, ScoredProduct>  $children
     */
    private function estimateBlockWidth(Collection $children): float
    {
        return $children->sum(function (ScoredProduct $product): float {
            $facing = (float) ($product->metadata['estimated_facing'] ?? 1);
            $width = (float) ($product->product->width ?? 10);

            return $width * $facing;
        });
    }
}
