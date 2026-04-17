<?php

use App\Services\Plannerate\AutoGenerate\ProductSelectionService;
use App\Services\Plannerate\SectionGenerate\SectionPersistenceService;
use App\Services\Plannerate\SectionGenerate\SectionPlanogramService;
use App\Services\Plannerate\SectionGenerate\SectionRulesAllocator;

use function Pest\Laravel\mock;

function makeQuotaService(): SectionPlanogramService
{
    $productSelection = mock(ProductSelectionService::class);
    $allocator = mock(SectionRulesAllocator::class);
    $persistence = mock(SectionPersistenceService::class);

    return new class($productSelection, $allocator, $persistence) extends SectionPlanogramService
    {
        public function exposedCalculateSectionQuota(
            int $productsLeft,
            int $sectionsLeft,
            float $sectionCapacity,
            float $remainingCapacity,
        ): int {
            return $this->calculateSectionQuota($productsLeft, $sectionsLeft, $sectionCapacity, $remainingCapacity);
        }

        /**
         * @param  array<string, int>  $sectionAllocatedCounts
         * @param  array<string, int>  $sectionQuotaCounts
         * @return array<string, float|int>
         */
        public function exposedBuildQualityMetrics(
            int $totalCandidates,
            int $totalAllocated,
            int $totalUnallocated,
            int $sectionsProcessed,
            int $totalSections,
            array $sectionAllocatedCounts,
            array $sectionQuotaCounts,
            array $rejectionReasonAttemptCounts = [],
            array $rejectionReasonUniqueCounts = [],
            int $splitCandidatesTotal = 0,
            int $splitResolvedTotal = 0,
            int $splitFailedTotal = 0,
        ): array {
            return $this->buildQualityMetrics(
                totalCandidates: $totalCandidates,
                totalAllocated: $totalAllocated,
                totalUnallocated: $totalUnallocated,
                sectionsProcessed: $sectionsProcessed,
                totalSections: $totalSections,
                sectionAllocatedCounts: $sectionAllocatedCounts,
                sectionQuotaCounts: $sectionQuotaCounts,
                rejectionReasonAttemptCounts: $rejectionReasonAttemptCounts,
                rejectionReasonUniqueCounts: $rejectionReasonUniqueCounts,
                splitCandidatesTotal: $splitCandidatesTotal,
                splitResolvedTotal: $splitResolvedTotal,
                splitFailedTotal: $splitFailedTotal,
            );
        }
    };
}

describe('SectionPlanogramService dynamic quota', function () {
    it('reserves products for remaining sections', function () {
        $service = makeQuotaService();

        $quota = $service->exposedCalculateSectionQuota(
            productsLeft: 10,
            sectionsLeft: 3,
            sectionCapacity: 900,
            remainingCapacity: 1000,
        );

        expect($quota)->toBeLessThan(10)
            ->and($quota)->toBe(8);
    });

    it('distributes quota proportionally by capacity', function () {
        $service = makeQuotaService();

        $quotaA = $service->exposedCalculateSectionQuota(
            productsLeft: 12,
            sectionsLeft: 3,
            sectionCapacity: 600,
            remainingCapacity: 1200,
        );

        $quotaB = $service->exposedCalculateSectionQuota(
            productsLeft: 12,
            sectionsLeft: 3,
            sectionCapacity: 200,
            remainingCapacity: 1200,
        );

        expect($quotaA)->toBeGreaterThan($quotaB)
            ->and($quotaA)->toBe(6)
            ->and($quotaB)->toBe(4);
    });

    it('returns all remaining products for the last section', function () {
        $service = makeQuotaService();

        $quota = $service->exposedCalculateSectionQuota(
            productsLeft: 5,
            sectionsLeft: 1,
            sectionCapacity: 300,
            remainingCapacity: 300,
        );

        expect($quota)->toBe(5);
    });

    it('calculates quality metrics from allocation results', function () {
        $service = makeQuotaService();

        $metrics = $service->exposedBuildQualityMetrics(
            totalCandidates: 10,
            totalAllocated: 7,
            totalUnallocated: 3,
            sectionsProcessed: 2,
            totalSections: 3,
            sectionAllocatedCounts: [
                'section-a' => 5,
                'section-b' => 2,
            ],
            sectionQuotaCounts: [
                'section-a' => 6,
                'section-b' => 4,
            ],
            rejectionReasonAttemptCounts: [
                'width_exceeded' => 3,
                'height_exceeded' => 1,
            ],
            rejectionReasonUniqueCounts: [
                'width_exceeded' => 2,
                'height_exceeded' => 1,
            ],
            splitCandidatesTotal: 5,
            splitResolvedTotal: 3,
            splitFailedTotal: 2,
        );

        expect($metrics['total_candidates'])->toBe(10)
            ->and($metrics['fill_rate'])->toBe(70.0)
            ->and($metrics['unallocated_rate'])->toBe(30.0)
            ->and($metrics['sections_processed_rate'])->toBe(66.67)
            ->and($metrics['allocation_concentration_rate'])->toBe(71.43)
            ->and($metrics['average_quota_utilization_rate'])->toBe(66.67)
            ->and($metrics['unallocated_by_reason'])->toBe([
                'width_exceeded' => 3,
                'height_exceeded' => 1,
            ])
            ->and($metrics['unallocated_by_reason_attempts'])->toBe([
                'width_exceeded' => 3,
                'height_exceeded' => 1,
            ])
            ->and($metrics['unallocated_by_reason_unique'])->toBe([
                'width_exceeded' => 2,
                'height_exceeded' => 1,
            ])
            ->and($metrics['split_candidates'])->toBe(5)
            ->and($metrics['split_resolved'])->toBe(3)
            ->and($metrics['split_failed'])->toBe(2)
            ->and($metrics['split_resolution_rate'])->toBe(60.0);
    });
});
