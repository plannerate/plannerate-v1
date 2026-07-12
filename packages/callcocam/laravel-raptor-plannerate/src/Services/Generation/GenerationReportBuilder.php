<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Generation;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\AutoGenerationResult;
use Callcocam\LaravelRaptorPlannerate\Enums\PlacementFailureReason;

/**
 * Monta o relatório de capacidade de uma execução de geração.
 *
 * Extraído do AutoPlanogramController (onde era inline e só virava flash do Inertia)
 * para poder ser reusado pelo GenerateAutoPlanogramJob, que persiste o resultado no
 * PlanogramGenerationRun. Fonte única: o mesmo relatório que a UI já consumia.
 */
final class GenerationReportBuilder
{
    /**
     * Relatório de capacidade — o que a UI exibe no banner/painel após a geração.
     *
     * @param  string|null  $templateId  Template escolhido pelo usuário (modo template)
     * @return array<string, mixed>
     */
    public function buildCapacityReport(AutoGenerationResult $result, ?string $templateId): array
    {
        $output = $result->output;
        $totalProducts = $result->totalInputProducts;

        // Produtos únicos definitivamente sem espaço: exclui rejeitados de um slot
        // que acabaram colocados em outro slot da mesma categoria.
        $placedProductIds = $output->placedSegments
            ->flatMap(fn ($seg) => $seg->layers->map(fn ($l) => $l->productId))
            ->flip()
            ->all();

        $trulyRejectedNoSpace = $output->rejectedProducts
            ->filter(fn ($r) => $r['reason'] === PlacementFailureReason::NoHorizontalSpace
                && $r['product'] !== null
                && ! isset($placedProductIds[$r['product']->id]))
            ->unique(fn ($r) => $r['product']->id);

        $rejectedSpace = $trulyRejectedNoSpace->count();
        $rejectedHeight = $output->rejectedProducts
            ->filter(fn ($r) => $r['reason'] === PlacementFailureReason::HeightExceedsShelf)
            ->count();
        $rejectedMissingDimensions = $output->rejectedProducts
            ->filter(fn ($r) => $r['reason'] === PlacementFailureReason::MissingDimensions)
            ->unique(fn ($r) => $r['product']?->id)
            ->count();

        $report = [
            'total_produtos' => $totalProducts,
            'posicionados' => $output->totalAllocated(),
            'rejeitados_espaco' => $rejectedSpace,
            'rejeitados_altura' => $rejectedHeight,
            'rejeitados_sem_dimensao' => $rejectedMissingDimensions,
            'mix_excede_gondola' => $rejectedSpace > 0,
            'taxa_cobertura' => round($output->totalAllocated() / max($totalProducts, 1), 3),
            'score_type' => $output->scoreType,
            'has_sales_data' => $output->scoreType !== 'neutral',
            'produtos_rejeitados_espaco' => $trulyRejectedNoSpace
                ->map(fn ($r) => [
                    'id' => $r['product']->id,
                    'name' => $r['product']->name,
                    'category' => $r['product']->category?->name,
                ])
                ->values()
                ->all(),
        ];

        // Modo template (incluindo template sintetizado pelo automático)
        $effectiveTemplateId = $templateId ?? $result->synthTemplateId;

        if ($effectiveTemplateId !== null) {
            $report['suggestions'] = $output->suggestions;
            $report['slot_analysis'] = $output->slotAnalysis;
            $report['has_space'] = collect($output->slotAnalysis)->some(fn ($s) => $s['largura_livre'] > 10);
            $report['has_rejects'] = $rejectedSpace > 0;
            $report['template_id'] = $effectiveTemplateId;
            $report['modules_mismatch'] = $output->modulesMismatch;
            $report['template_modules'] = $output->templateModules;
            $report['gondola_modules'] = $output->gondolaModules;
            $report['subtemplate_id'] = $output->subtemplateId;
            $report['explanation_report'] = $output->explanationReport;
        }

        // Informa o frontend que a gôndola foi promovida de automático para template-mode
        if ($result->synthTemplateId !== null) {
            $report['is_auto_generated'] = true;
            $report['synth_template_id'] = $result->synthTemplateId;
        }

        return $report;
    }

    /**
     * Métricas de ocupação das prateleiras, derivadas do slot_analysis.
     *
     * É a métrica que mede se a gôndola "fechou" com precisão — hoje tipicamente
     * 70-80% (ver docs/gondola-precisao-automatica/). Persistir por execução é o que
     * permite provar a melhoria das fases seguintes do plano.
     *
     * @return array{occupancy_avg: float|null, occupancy_min: float|null, occupancy_max: float|null}
     */
    public function buildOccupancyMetrics(AutoGenerationResult $result): array
    {
        // percentual_uso vem em 0-100; normalizamos para 0-1 (coluna decimal(5,4)).
        $usages = collect($result->output->slotAnalysis)
            ->pluck('percentual_uso')
            ->filter(fn ($v) => $v !== null)
            ->map(fn ($v) => round((float) $v / 100, 4));

        if ($usages->isEmpty()) {
            return [
                'occupancy_avg' => null,
                'occupancy_min' => null,
                'occupancy_max' => null,
            ];
        }

        return [
            'occupancy_avg' => round((float) $usages->avg(), 4),
            'occupancy_min' => (float) $usages->min(),
            'occupancy_max' => (float) $usages->max(),
        ];
    }
}
