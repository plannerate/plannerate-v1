<?php

namespace App\Services\AutoPlanogram\Grouping;

use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ProductBlock;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use Illuminate\Support\Collection;

/**
 * Agrupador que cria um bloco individual por produto (sem agrupamento real).
 *
 * Preserva o comportamento atual: cada produto é tratado como unidade independente.
 */
final class NoOpBlockGrouper implements BlockGrouperInterface
{
    /**
     * @param  Collection<int, ScoredProduct>  $scoredProducts
     * @return Collection<int, ProductBlock>
     */
    public function group(Collection $scoredProducts, PlacementSettings $settings): Collection
    {
        unset($settings);

        return $scoredProducts->map(fn (ScoredProduct $product) => new ProductBlock(
            children: collect([$product]),
            aggregateScore: $product->score,
            groupingKey: $product->metadata['abc_class'] ?? 'none',
            totalWidthEstimate: (float) (($product->product->width ?? 10)),
            blockHierarchyLevel: 0,
        ))->values();
    }
}
