<?php

namespace App\Services\AutoPlanogram\Adjacency;

use App\Services\AutoPlanogram\DTO\OrderedBlock;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ProductBlock;
use Illuminate\Support\Collection;

class RuleBasedResolver implements AdjacencyResolverInterface
{
    /**
     * @param  Collection<int, ProductBlock>  $blocks
     * @return Collection<int, OrderedBlock>
     */
    public function resolve(Collection $blocks, PlacementSettings $settings): Collection
    {
        if ($blocks->isEmpty()) {
            return collect();
        }

        $matrix = $this->loadMatrix($settings->tenantId);
        $remaining = $blocks
            ->sortByDesc('aggregateScore')
            ->keyBy(fn (ProductBlock $block): string => $block->groupingKey);

        /** @var ProductBlock $current */
        $current = $remaining->shift();
        $ordered = collect([$this->wrap($current, 0)]);

        while ($remaining->isNotEmpty()) {
            $next = $this->pickNext($current, $remaining, $matrix);
            $remaining->forget($next->groupingKey);
            $ordered->push($this->wrap($next, $ordered->count()));
            $current = $next;
        }

        return $ordered;
    }

    /**
     * @param  Collection<string, ProductBlock>  $candidates
     */
    private function pickNext(ProductBlock $current, Collection $candidates, AdjacencyMatrix $matrix): ProductBlock
    {
        $availableNonForbidden = $candidates->contains(
            fn (ProductBlock $candidate): bool => ! $matrix->isForbidden($current->adjacencyCategoryId, $candidate->adjacencyCategoryId)
        );

        /** @var array{block: ProductBlock, score: float} $result */
        $result = $candidates
            ->map(function (ProductBlock $candidate) use ($availableNonForbidden, $current, $matrix): array {
                $isForbidden = $matrix->isForbidden($current->adjacencyCategoryId, $candidate->adjacencyCategoryId);
                $adjacencyWeight = $matrix->weightBetween($current->adjacencyCategoryId, $candidate->adjacencyCategoryId);

                return [
                    'block' => $candidate,
                    'score' => $availableNonForbidden && $isForbidden
                        ? -INF
                        : $candidate->aggregateScore + $adjacencyWeight,
                ];
            })
            ->sortByDesc('score')
            ->first();

        return $result['block'];
    }

    private function wrap(ProductBlock $block, int $order): OrderedBlock
    {
        return new OrderedBlock(block: $block, sequenceOrder: $order);
    }

    protected function loadMatrix(?string $tenantId): AdjacencyMatrix
    {
        if ($tenantId === null || $tenantId === '') {
            return new AdjacencyMatrix([]);
        }

        return AdjacencyMatrix::loadForTenant($tenantId);
    }
}
