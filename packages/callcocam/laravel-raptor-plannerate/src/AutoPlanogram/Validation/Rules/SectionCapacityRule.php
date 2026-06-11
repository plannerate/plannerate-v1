<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\Rules;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementResult;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlanogramInput;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\ValidationResult;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\ValidationRuleInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Validates section capacity utilization.
 *
 * Warns if:
 * - Section is under-utilized (< 70% capacity)
 * - Section is over-packed (> 95% capacity)
 * - Section is optimally used (70-95%)
 */
final class SectionCapacityRule implements ValidationRuleInterface
{
    private const MIN_CAPACITY = 70;

    private const MAX_CAPACITY = 95;

    public function name(): string
    {
        return 'section_capacity';
    }

    /**
     * @param  Collection<int, PlacedSegment>  $placedSegments
     * @return array<int, ValidationResult>
     */
    public function evaluate(Collection $placedSegments, PlanogramInput $input, PlacementResult $result): array
    {
        $results = [];

        // Group segments by section
        $segmentsBySection = $placedSegments->groupBy('sectionId');

        foreach ($segmentsBySection as $sectionId => $segments) {
            $utilizationPercentage = $this->calculateUtilization($sectionId, $segments);

            if ($utilizationPercentage < self::MIN_CAPACITY) {
                $productIds = $segments
                    ->flatMap(fn ($s) => $s->layers->pluck('productId'))
                    ->unique()
                    ->values()
                    ->all();

                $results[] = ValidationResult::warning(
                    $this->name(),
                    "Seção {$sectionId} está subutilizada ({$utilizationPercentage}% de capacidade). Considere consolidar produtos.",
                    $productIds,
                    $sectionId,
                );
            } elseif ($utilizationPercentage > self::MAX_CAPACITY) {
                $productIds = $segments
                    ->flatMap(fn ($s) => $s->layers->pluck('productId'))
                    ->unique()
                    ->values()
                    ->all();

                $results[] = ValidationResult::warning(
                    $this->name(),
                    "Seção {$sectionId} está muito apertada ({$utilizationPercentage}% de capacidade). Pode comprometer a visibilidade.",
                    $productIds,
                    $sectionId,
                );
            }
        }

        return $results;
    }

    /**
     * Calculate section utilization percentage.
     *
     * Utilization = (Total segment widths / Section width) * 100
     */
    private function calculateUtilization(string $sectionId, Collection $segments): int
    {
        $totalSegmentWidth = $segments->sum('width');
        $sectionWidth = $this->getSectionWidth($sectionId);

        if ($sectionWidth <= 0) {
            return 0;
        }

        return (int) round(($totalSegmentWidth / $sectionWidth) * 100);
    }

    /**
     * Get the total width of a section.
     */
    private function getSectionWidth(string $sectionId): float
    {
        $section = DB::connection('tenant')->table('sections')
            ->where('id', $sectionId)
            ->select('width')
            ->first();

        return $section ? (float) $section->width : 100;
    }
}
