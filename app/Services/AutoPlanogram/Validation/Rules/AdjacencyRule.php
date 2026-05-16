<?php

namespace App\Services\AutoPlanogram\Validation\Rules;

use App\Enums\AdjacencyRuleType;
use App\Models\AdjacencyRule as AdjacencyRuleModel;
use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\Validation\ValidationResult;
use App\Services\AutoPlanogram\Validation\ValidationRuleInterface;
use Illuminate\Support\Collection;

/**
 * Validates that adjacency rules (MUST_AVOID, MUST_BE_NEAR) are respected.
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
    public function evaluate(Collection $placedSegments, PlanogramInput $input): array
    {
        $results = [];

        // Group segments by shelf
        $segmentsByShelf = $placedSegments->groupBy(fn (PlacedSegment $s) => $s->shelfId);

        foreach ($segmentsByShelf as $shelfId => $segments) {
            // Sort by ordering to check adjacency
            $sorted = $segments->sortBy('ordering')->values();

            // Check each adjacent pair
            for ($i = 0; $i < $sorted->count() - 1; $i++) {
                $current = $sorted[$i];
                $next = $sorted[$i + 1];

                $currentProducts = $current->layers->pluck('productId')->all();
                $nextProducts = $next->layers->pluck('productId')->all();

                // Check adjacency rules between these products
                $violations = $this->checkAdjacencyViolations(
                    $currentProducts,
                    $nextProducts,
                    $input->tenantId
                );

                foreach ($violations as $violation) {
                    $results[] = $violation;
                }
            }
        }

        return $results;
    }

    /**
     * Check for MUST_AVOID violations between two groups of products.
     *
     * @param  array<int, string>  $currentProducts
     * @param  array<int, string>  $nextProducts
     * @return array<int, ValidationResult>
     */
    private function checkAdjacencyViolations(
        array $currentProducts,
        array $nextProducts,
        string $tenantId
    ): array {
        $violations = [];

        foreach ($currentProducts as $currentProductId) {
            foreach ($nextProducts as $nextProductId) {
                // Check for MUST_AVOID rules
                $avoided = AdjacencyRuleModel::where('tenant_id', $tenantId)
                    ->where('type', AdjacencyRuleType::MustAvoid)
                    ->where(function ($q) use ($currentProductId, $nextProductId) {
                        $q->where(function ($subQ) use ($currentProductId, $nextProductId) {
                            $subQ->where('from_category_id', $currentProductId)
                                ->where('to_category_id', $nextProductId);
                        })->orWhere(function ($subQ) use ($currentProductId, $nextProductId) {
                            $subQ->where('from_category_id', $nextProductId)
                                ->where('to_category_id', $currentProductId);
                        });
                    })
                    ->exists();

                if ($avoided) {
                    $violations[] = ValidationResult::error(
                        $this->name(),
                        "Produtos {$currentProductId} e {$nextProductId} violam regra de adjacência (não podem ficar pertos).",
                        [$currentProductId, $nextProductId],
                    );
                }
            }
        }

        return $violations;
    }
}
