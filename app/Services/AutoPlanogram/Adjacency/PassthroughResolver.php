<?php

namespace App\Services\AutoPlanogram\Adjacency;

use App\Services\AutoPlanogram\DTO\OrderedBlock;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ProductBlock;
use Illuminate\Support\Collection;

/**
 * Resolver de adjacência que mantém a ordem do scorer sem alterações.
 *
 * Preserva o comportamento atual: ordem determinada apenas pelo score.
 */
final class PassthroughResolver implements AdjacencyResolverInterface
{
    /**
     * @param  Collection<int, ProductBlock>  $blocks
     * @return Collection<int, OrderedBlock>
     */
    public function resolve(Collection $blocks, PlacementSettings $settings): Collection
    {
        return $blocks->values()->map(fn (ProductBlock $block, int $index) => new OrderedBlock(
            block: $block,
            sequenceOrder: $index,
        ));
    }
}
