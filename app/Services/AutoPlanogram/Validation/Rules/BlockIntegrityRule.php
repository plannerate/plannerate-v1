<?php

namespace App\Services\AutoPlanogram\Validation\Rules;

use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\DTO\PlacementResult;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\Validation\ValidationResult;
use App\Services\AutoPlanogram\Validation\ValidationRuleInterface;
use Illuminate\Support\Collection;

/**
 * Validates that products from the same block stay logically together.
 *
 * Note: This rule checks for segment fragmentation across sections.
 * If the placement algorithm fragments blocks unnecessarily, this rule will warn.
 */
final class BlockIntegrityRule implements ValidationRuleInterface
{
    public function name(): string
    {
        return 'block_integrity';
    }

    /**
     * @param  Collection<int, PlacedSegment>  $placedSegments
     * @return array<int, ValidationResult>
     */
    public function evaluate(Collection $placedSegments, PlanogramInput $input, PlacementResult $result): array
    {
        $results = [];

        // Check for excessive segment fragmentation across sections
        // If many segments are scattered across sections, it may indicate poor block integrity
        $segmentsByShelf = $placedSegments->groupBy('shelfId');
        $fragmentationScore = $segmentsByShelf->count() / max(1, $placedSegments->count());

        // If fragmentation is high (many small segments), warn
        if ($fragmentationScore > 0.7 && $placedSegments->count() > 5) {
            $results[] = ValidationResult::info(
                $this->name(),
                'Planograma tem alta fragmentação de segmentos. Considere revisar o agrupamento de produtos.',
                [],
            );
        }

        return $results;
    }
}
