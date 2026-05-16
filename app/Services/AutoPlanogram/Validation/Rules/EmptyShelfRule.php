<?php

namespace App\Services\AutoPlanogram\Validation\Rules;

use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\DTO\PlacementResult;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\Validation\ValidationResult;
use App\Services\AutoPlanogram\Validation\ValidationRuleInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Validates that shelves are not unnecessarily empty.
 *
 * Warns if a shelf in a used section has no products placed on it.
 */
final class EmptyShelfRule implements ValidationRuleInterface
{
    public function name(): string
    {
        return 'empty_shelf';
    }

    /**
     * @param  Collection<int, PlacedSegment>  $placedSegments
     * @return array<int, ValidationResult>
     */
    public function evaluate(Collection $placedSegments, PlanogramInput $input, PlacementResult $result): array
    {
        $results = [];

        // Get all used shelf IDs
        $usedShelfIds = $placedSegments->pluck('shelfId')->unique();

        // For each used section, check if all shelves are populated
        $segmentsBySectionShelf = $placedSegments->groupBy(fn (PlacedSegment $s) => "{$s->sectionId}#{$s->shelfId}");

        $sectionIds = $placedSegments->pluck('sectionId')->unique();

        foreach ($sectionIds as $sectionId) {
            // Get all shelves in this section
            $allShelves = DB::connection('tenant')->table('shelves')
                ->where('section_id', $sectionId)
                ->pluck('id');

            foreach ($allShelves as $shelfId) {
                // Check if this shelf has any products
                $hasProducts = $placedSegments
                    ->where('sectionId', $sectionId)
                    ->where('shelfId', $shelfId)
                    ->isNotEmpty();

                if (! $hasProducts) {
                    $results[] = ValidationResult::info(
                        $this->name(),
                        "Prateleira {$shelfId} na seção {$sectionId} está vazia.",
                        [],
                        $shelfId,
                        $sectionId,
                    );
                }
            }
        }

        return $results;
    }
}
