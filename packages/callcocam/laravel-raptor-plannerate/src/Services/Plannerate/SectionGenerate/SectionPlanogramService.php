<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate\SectionGenerate;

use Callcocam\LaravelRaptorPlannerate\Concerns\BelongsToConnection;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\SectionGenerate\SectionGenerateResultDTO;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Client;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate\ProductSelectionService;
use Illuminate\Support\Facades\Log;

/**
 * Orquestra a geração do planograma por Section (módulo).
 *
 * Para cada section: aloca produtos (regras ou, no futuro, IA) e persiste.
 * Não calcula a gôndola inteira de uma vez.
 */
class SectionPlanogramService
{
    use BelongsToConnection;

    public function __construct(
        protected ProductSelectionService $productSelection,
        protected SectionRulesAllocator $sectionAllocator,
        protected SectionPersistenceService $persistence,
        protected ?SectionAIAllocator $aiAllocator = null,
    ) {}

    /**
     * Gerar planograma section a section.
     *
     * @param  bool  $useAi  Se true, usa Laravel AI SDK por section; senão usa regras de merchandising.
     *
     * @throws \Exception
     */
    public function generateBySections(string $gondolaId, AutoGenerateConfigDTO $config, bool $useAi = false): SectionGenerateResultDTO
    {

        $this->setupClientConnectionFromConfig();

        $gondola = Gondola::with(['sections.shelves'])->find($gondolaId);
        if (! $gondola) {
            throw new \Exception("Gôndola não encontrada: {$gondolaId}");
        }

        $planogram = Planogram::with('category')->find($gondola->planogram_id);
        if (! $planogram) {
            throw new \Exception("Planograma não encontrado: {$gondola->planogram_id}");
        }

        $rankedProducts = $this->productSelection->selectAndRankProducts($planogram, $config);
        if ($rankedProducts->isEmpty()) {
            Log::warning('Nenhum produto ranqueado para a categoria do planograma.');
            throw new \RuntimeException(
                'Nenhum produto encontrado para a categoria do planograma. '
                .'Verifique se há produtos nessa categoria no banco do cliente e se a análise de vendas está disponível.'
            );
        }

        $totalAllocated = 0;
        $totalUnallocated = 0;
        $sectionsProcessed = 0;
        $remainingProducts = $rankedProducts;
        $totalCandidates = $rankedProducts->count();
        $sectionAllocatedCounts = [];
        $sectionQuotaCounts = [];
        $rejectionReasonAttemptCounts = [];
        $productLastRejectionReason = [];
        $sectionDiagnostics = [];
        $splitCandidatesTotal = 0;
        $splitResolvedTotal = 0;
        $splitFailedTotal = 0;
        $disableAiForRemainingSections = false;

        $orderedSections = $gondola->sections
            ->sortByDesc(fn ($section) => $this->estimateSectionCapacity($section))
            ->values();

        $sectionCapacities = $orderedSections
            ->mapWithKeys(fn ($section) => [$section->id => $this->estimateSectionCapacity($section)]);
        $remainingCapacity = max(1.0, (float) $sectionCapacities->sum());

        Log::info('📊 Sections reordenadas por capacidade', [
            'gondola_id' => $gondola->id,
            'original_count' => $gondola->sections->count(),
            'reordered_count' => $orderedSections->count(),
            'capacities' => $orderedSections->map(fn ($s) => [
                'id' => $s->id,
                'capacity' => $this->estimateSectionCapacity($s),
            ])->all(),
        ]);

        foreach ($orderedSections as $section) {
            $sectionsLeft = $orderedSections->count() - $sectionsProcessed;
            $productsLeft = $remainingProducts->count();

            if ($productsLeft === 0) {
                break;
            }

            $sectionCapacity = (float) ($sectionCapacities[$section->id] ?? 1.0);
            $quota = $this->calculateSectionQuota(
                productsLeft: $productsLeft,
                sectionsLeft: $sectionsLeft,
                sectionCapacity: $sectionCapacity,
                remainingCapacity: $remainingCapacity,
            );

            $productsForSection = $remainingProducts->take($quota)->values();
            $sectionQuotaCounts[(string) $section->id] = $quota;

            $useAiAllocator = $useAi && $this->aiAllocator !== null && ! $disableAiForRemainingSections;

            if ($useAiAllocator) {
                $aiResult = $this->aiAllocator->allocate($section, $productsForSection);

                $aiReturnedEmptyAllocation = count($aiResult->allocation) === 0
                    && count($aiResult->unallocated) === $productsForSection->count();

                $aiUnavailable = str_starts_with($aiResult->reasoning, 'IA indisponível:');

                if ($aiReturnedEmptyAllocation) {
                    Log::warning('⚠️ IA por section sem alocação. Aplicando fallback por regras.', [
                        'section_id' => $section->id,
                        'ai_reasoning' => $aiResult->reasoning,
                        'products_for_section' => $productsForSection->count(),
                    ]);

                    $result = $this->sectionAllocator->allocate($section, $productsForSection, $config);

                    if ($aiUnavailable) {
                        $disableAiForRemainingSections = true;

                        Log::warning('⚠️ IA por section desativada para sections restantes nesta execução.', [
                            'section_id' => $section->id,
                            'reason' => $aiResult->reasoning,
                        ]);
                    }
                } else {
                    $result = $aiResult;
                }
            } else {
                $result = $this->sectionAllocator->allocate($section, $productsForSection, $config);
            }

            $this->persistence->clearSection($section);
            $created = $this->persistence->saveAllocation($section, $result);

            $totalAllocated += $created;
            $allocatedIds = array_map(fn ($item) => $item->productId, $result->allocation);
            $remainingProducts = $remainingProducts->filter(
                fn ($rp) => ! in_array($rp->product->id, $allocatedIds, true)
            );

            $remainingCapacity = max(1.0, $remainingCapacity - $sectionCapacity);
            $sectionAllocatedCounts[(string) $section->id] = $created;

            foreach ($result->unallocatedByReason as $reason => $count) {
                $reasonKey = (string) $reason;
                $rejectionReasonAttemptCounts[$reasonKey] = ($rejectionReasonAttemptCounts[$reasonKey] ?? 0) + (int) $count;
            }

            foreach ($result->unallocatedReasonByProduct as $productId => $reason) {
                $productLastRejectionReason[(string) $productId] = (string) $reason;
            }

            $splitCandidates = (int) ($result->allocationDiagnostics['split_candidates'] ?? 0);
            $splitResolved = (int) ($result->allocationDiagnostics['split_resolved'] ?? 0);
            $splitFailed = (int) ($result->allocationDiagnostics['split_failed'] ?? 0);

            $splitCandidatesTotal += $splitCandidates;
            $splitResolvedTotal += $splitResolved;
            $splitFailedTotal += $splitFailed;

            $sectionDiagnostics[] = [
                'section_id' => (string) $section->id,
                'quota' => $quota,
                'allocated' => $created,
                'unallocated' => count($result->unallocated),
                'quota_utilization_rate' => $quota > 0 ? round(($created / $quota) * 100, 2) : 0.0,
                'unallocated_by_reason_attempts' => $result->unallocatedByReason,
                'split_candidates' => $splitCandidates,
                'split_resolved' => $splitResolved,
                'split_failed' => $splitFailed,
                'split_resolution_rate' => $splitCandidates > 0
                    ? round(($splitResolved / $splitCandidates) * 100, 2)
                    : 0.0,
            ];

            Log::debug('Cota dinâmica por section aplicada', [
                'section_id' => $section->id,
                'sections_left' => $sectionsLeft,
                'products_left_before' => $productsLeft,
                'quota' => $quota,
                'allocated' => $created,
                'remaining_products_after' => $remainingProducts->count(),
            ]);

            $sectionsProcessed++;
        }

        // Evita contagem duplicada por section: não alocados finais = produtos restantes após todas as sections.
        $totalUnallocated = $remainingProducts->count();
        $finalUnallocatedIds = $remainingProducts->pluck('product.id')->map(fn ($id) => (string) $id)->all();
        $rejectionReasonUniqueCounts = $this->buildUniqueRejectionCounts(
            finalUnallocatedIds: $finalUnallocatedIds,
            productLastRejectionReason: $productLastRejectionReason,
        );

        $qualityMetrics = $this->buildQualityMetrics(
            totalCandidates: $totalCandidates,
            totalAllocated: $totalAllocated,
            totalUnallocated: $totalUnallocated,
            sectionsProcessed: $sectionsProcessed,
            totalSections: $orderedSections->count(),
            sectionAllocatedCounts: $sectionAllocatedCounts,
            sectionQuotaCounts: $sectionQuotaCounts,
            rejectionReasonAttemptCounts: $rejectionReasonAttemptCounts,
            rejectionReasonUniqueCounts: $rejectionReasonUniqueCounts,
            splitCandidatesTotal: $splitCandidatesTotal,
            splitResolvedTotal: $splitResolvedTotal,
            splitFailedTotal: $splitFailedTotal,
        );

        Log::info('✅ Geração por sections concluída', [
            'sections_processed' => $sectionsProcessed,
            'total_allocated' => $totalAllocated,
            'total_unallocated' => $totalUnallocated,
            'quality_metrics' => $qualityMetrics,
        ]);

        return new SectionGenerateResultDTO(
            sectionsProcessed: $sectionsProcessed,
            totalAllocated: $totalAllocated,
            totalUnallocated: $totalUnallocated,
            generatedAt: now()->toIso8601String(),
            qualityMetrics: $qualityMetrics,
            sectionDiagnostics: $sectionDiagnostics,
        );
    }

    /**
     * @param  array<string, int>  $sectionAllocatedCounts
     * @param  array<string, int>  $sectionQuotaCounts
     * @param  array<string, int>  $rejectionReasonAttemptCounts
     * @param  array<string, int>  $rejectionReasonUniqueCounts
     * @return array<string, float|int>
     */
    protected function buildQualityMetrics(
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
        $fillRate = $totalCandidates > 0
            ? round(($totalAllocated / $totalCandidates) * 100, 2)
            : 0.0;

        $unallocatedRate = $totalCandidates > 0
            ? round(($totalUnallocated / $totalCandidates) * 100, 2)
            : 0.0;

        $processedRate = $totalSections > 0
            ? round(($sectionsProcessed / $totalSections) * 100, 2)
            : 0.0;

        $maxSectionAllocated = ! empty($sectionAllocatedCounts)
            ? max($sectionAllocatedCounts)
            : 0;

        $allocationConcentrationRate = $totalAllocated > 0
            ? round(($maxSectionAllocated / $totalAllocated) * 100, 2)
            : 0.0;

        $quotaUtilizationRates = [];
        foreach ($sectionQuotaCounts as $sectionId => $quota) {
            if ($quota <= 0) {
                continue;
            }

            $allocated = (int) ($sectionAllocatedCounts[$sectionId] ?? 0);
            $quotaUtilizationRates[] = ($allocated / $quota) * 100;
        }

        $averageQuotaUtilizationRate = count($quotaUtilizationRates) > 0
            ? round(array_sum($quotaUtilizationRates) / count($quotaUtilizationRates), 2)
            : 0.0;

        $splitResolutionRate = $splitCandidatesTotal > 0
            ? round(($splitResolvedTotal / $splitCandidatesTotal) * 100, 2)
            : 0.0;

        return [
            'total_candidates' => $totalCandidates,
            'fill_rate' => $fillRate,
            'unallocated_rate' => $unallocatedRate,
            'sections_processed_rate' => $processedRate,
            'allocation_concentration_rate' => $allocationConcentrationRate,
            'average_quota_utilization_rate' => $averageQuotaUtilizationRate,
            // Compat: mantém chave antiga como attempts
            'unallocated_by_reason' => $rejectionReasonAttemptCounts,
            'unallocated_by_reason_attempts' => $rejectionReasonAttemptCounts,
            'unallocated_by_reason_unique' => $rejectionReasonUniqueCounts,
            'split_candidates' => $splitCandidatesTotal,
            'split_resolved' => $splitResolvedTotal,
            'split_failed' => $splitFailedTotal,
            'split_resolution_rate' => $splitResolutionRate,
        ];
    }

    /**
     * @param  array<int, string>  $finalUnallocatedIds
     * @param  array<string, string>  $productLastRejectionReason
     * @return array<string, int>
     */
    protected function buildUniqueRejectionCounts(array $finalUnallocatedIds, array $productLastRejectionReason): array
    {
        $counts = [];

        foreach ($finalUnallocatedIds as $productId) {
            $reason = (string) ($productLastRejectionReason[$productId] ?? 'unknown');
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
        }

        return $counts;
    }

    protected function estimateSectionCapacity(object $section): float
    {
        $width = (float) ($section->width ?? $section->section_width ?? 100);
        $height = (float) ($section->height ?? 200);

        return max(1.0, $width * $height);
    }

    protected function calculateSectionQuota(
        int $productsLeft,
        int $sectionsLeft,
        float $sectionCapacity,
        float $remainingCapacity,
    ): int {
        if ($productsLeft <= 0) {
            return 0;
        }

        if ($sectionsLeft <= 1) {
            return $productsLeft;
        }

        if ($remainingCapacity <= 0) {
            return max(1, (int) ceil($productsLeft / $sectionsLeft));
        }

        $proportional = (int) ceil($productsLeft * ($sectionCapacity / $remainingCapacity));
        $fairFloor = max(1, (int) floor($productsLeft / $sectionsLeft));
        $reservedForOthers = max(0, $sectionsLeft - 1);
        $maxForCurrent = max(1, $productsLeft - $reservedForOthers);

        return min($maxForCurrent, max($fairFloor, $proportional));
    }

    protected function setupClientConnectionFromConfig(): void
    {
        $clientId = config('app.current_client_id');
        if (! $clientId) {
            return;
        }
        $client = Client::find($clientId);
        if ($client) {
            $this->setupClientConnection($client);
        }
    }
}
