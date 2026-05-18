<?php

namespace App\Services\AutoPlanogram\Validation\Rules;

use App\Enums\AdjacencyRuleType;
use App\Models\AdjacencyRule as AdjacencyRuleModel;
use App\Models\Product;
use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\DTO\PlacementResult;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\Validation\ValidationResult;
use App\Services\AutoPlanogram\Validation\ValidationRuleInterface;
use Illuminate\Support\Collection;

/**
 * Valida que regras de adjacência MustAvoid entre categorias são respeitadas.
 */
final class AdjacencyRule implements ValidationRuleInterface
{
    public function name(): string
    {
        return 'adjacency';
    }

    /**
     * @param  Collection<int, PlacedSegment>  $placedSegments
     * @return array<int, ValidationResult>
     */
    public function evaluate(Collection $placedSegments, PlanogramInput $input, PlacementResult $result): array
    {
        $avoidedPairs = $this->loadAvoidedPairs($input->tenantId);

        if (empty($avoidedPairs)) {
            return [];
        }

        $categoryMap = $this->loadCategoryMap($placedSegments);

        $results = [];
        $segmentsByShelf = $placedSegments->groupBy(fn (PlacedSegment $s) => $s->shelfId);

        foreach ($segmentsByShelf as $shelfId => $segments) {
            $sorted = $segments->sortBy('ordering')->values();

            for ($i = 0; $i < $sorted->count() - 1; $i++) {
                $currentCats = $this->segmentCategories($sorted[$i], $categoryMap);
                $nextCats = $this->segmentCategories($sorted[$i + 1], $categoryMap);

                foreach ($currentCats as $catA) {
                    foreach ($nextCats as $catB) {
                        if (isset($avoidedPairs[$this->pairKey($catA, $catB)])) {
                            $results[] = ValidationResult::error(
                                $this->name(),
                                "Categorias adjacentes violam regra de afastamento obrigatório.",
                                [],
                                (string) $shelfId,
                            );
                        }
                    }
                }
            }
        }

        return $results;
    }

    /** @return array<string, true> */
    private function loadAvoidedPairs(string $tenantId): array
    {
        $pairs = [];

        AdjacencyRuleModel::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('rule_type', AdjacencyRuleType::MustAvoid)
            ->get(['source_category_id', 'target_category_id'])
            ->each(function (AdjacencyRuleModel $rule) use (&$pairs): void {
                $pairs[$this->pairKey($rule->source_category_id, $rule->target_category_id)] = true;
            });

        return $pairs;
    }

    /** @return array<string, string|null> product_id => category_id */
    private function loadCategoryMap(Collection $segments): array
    {
        $productIds = $segments
            ->flatMap(fn (PlacedSegment $s) => $s->layers->pluck('productId'))
            ->unique()
            ->all();

        if (empty($productIds)) {
            return [];
        }

        return Product::withoutTenantScope()
            ->whereIn('id', $productIds)
            ->pluck('category_id', 'id')
            ->all();
    }

    /** @return array<int, string> */
    private function segmentCategories(PlacedSegment $segment, array $categoryMap): array
    {
        return $segment->layers
            ->map(fn ($layer) => $categoryMap[$layer->productId] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function pairKey(string $a, string $b): string
    {
        return $a < $b ? "$a:$b" : "$b:$a";
    }
}
