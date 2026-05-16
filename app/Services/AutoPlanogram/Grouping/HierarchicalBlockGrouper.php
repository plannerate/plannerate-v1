<?php

namespace App\Services\AutoPlanogram\Grouping;

use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ProductBlock;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class HierarchicalBlockGrouper implements BlockGrouperInterface
{
    public function __construct(
        private readonly ProductWidthResolver $widthResolver,
    ) {}

    /**
     * @param  Collection<int, ScoredProduct>  $scoredProducts
     * @return Collection<int, ProductBlock>
     */
    public function group(Collection $scoredProducts, PlacementSettings $settings): Collection
    {
        $blockLevel = $settings->resolvedBlockHierarchyLevel();
        $adjacencyLevel = $settings->resolvedAdjacencyHierarchyLevel();

        $blocks = $scoredProducts
            ->groupBy(function (ScoredProduct $scoredProduct) use ($blockLevel): string {
                $resolvedCategory = $this->resolveNonPlaceholderCategory($scoredProduct->product, $blockLevel);

                return $resolvedCategory instanceof Category
                    ? sprintf('block:lvl%d:%s', $resolvedCategory->hierarchy_position, $resolvedCategory->id)
                    : 'singleton:'.$scoredProduct->product->id;
            })
            ->map(function (Collection $children, string $key) use ($adjacencyLevel): ProductBlock {
                $firstProduct = $children->first()?->product;
                $totalWeight = (float) $children->sum(fn (ScoredProduct $product): float => $this->facingFromMetadata($product));
                $aggregateScore = $totalWeight > 0
                    ? $children->sum(fn (ScoredProduct $product): float => $product->score * $this->facingFromMetadata($product)) / $totalWeight
                    : 0.0;

                $blockHierarchyLevel = $firstProduct ? $this->blockHierarchyLevelFromGroupingKey($key) : 0;
                $adjacencyCategory = $firstProduct ? $this->resolveNonPlaceholderCategory($firstProduct, $adjacencyLevel) : null;

                return new ProductBlock(
                    children: $children->values(),
                    aggregateScore: $aggregateScore,
                    groupingKey: $key,
                    totalWidthEstimate: $this->estimateBlockWidth($children),
                    blockHierarchyLevel: $blockHierarchyLevel,
                    adjacencyCategoryId: $adjacencyCategory?->id,
                    isPlaceholder: $blockHierarchyLevel === 0,
                );
            })
            ->sortByDesc('aggregateScore')
            ->values();

        Log::info('HierarchicalBlockGrouper: distribuição de blocos', [
            'total_blocks' => $blocks->count(),
            'singletons' => $blocks->filter(fn (ProductBlock $block): bool => str_starts_with($block->groupingKey, 'singleton:'))->count(),
            'blocks_by_level' => $blocks
                ->filter(fn (ProductBlock $block): bool => str_starts_with($block->groupingKey, 'block:'))
                ->groupBy(fn (ProductBlock $block): int => $block->blockHierarchyLevel)
                ->map->count()
                ->all(),
            'avg_block_size' => round((float) $blocks->avg(fn (ProductBlock $block): int => $block->children->count()), 2),
        ]);

        return $blocks;
    }

    private function resolveCategoryAtLevel(Product $product, int $targetLevel): ?Category
    {
        $category = $this->productCategory($product);

        while ($category instanceof Category && $category->hierarchy_position > $targetLevel) {
            $parent = $this->categoryParent($category);

            if (! $parent instanceof Category) {
                break;
            }

            $category = $parent;
        }

        return $category;
    }

    private function resolveNonPlaceholderCategory(Product $product, int $targetLevel): ?Category
    {
        $category = $this->resolveCategoryAtLevel($product, $targetLevel);

        while ($category instanceof Category && (bool) $category->is_placeholder) {
            $category = $this->categoryParent($category);
        }

        return $category;
    }

    private function productCategory(Product $product): ?Category
    {
        if (! $product->relationLoaded('category') && empty($product->category_id)) {
            return null;
        }

        $category = $product->relationLoaded('category') ? $product->category : $product->category()->first();

        return $category instanceof Category ? $category : null;
    }

    private function categoryParent(Category $category): ?Category
    {
        $parent = $category->relationLoaded('parent') ? $category->parent : $category->parent()->first();

        return $parent instanceof Category ? $parent : null;
    }

    private function blockHierarchyLevelFromGroupingKey(string $key): int
    {
        if (! preg_match('/^block:lvl(\d+):/', $key, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    /**
     * @param  Collection<int, ScoredProduct>  $children
     */
    private function estimateBlockWidth(Collection $children): float
    {
        return $children->sum(function (ScoredProduct $product): float {
            $facing = $this->facingFromMetadata($product);
            $width = $this->widthResolver->resolve($product->product);

            return $width * $facing;
        });
    }

    private function facingFromMetadata(ScoredProduct $product): float
    {
        return (float) ($product->metadata['facing_final']
            ?? $product->metadata['estimated_facing']
            ?? $product->metadata['facing_ideal']
            ?? 1);
    }
}
