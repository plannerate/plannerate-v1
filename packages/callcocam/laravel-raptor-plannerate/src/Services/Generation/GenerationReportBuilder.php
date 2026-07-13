<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Generation;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\AutoGenerationResult;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;
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
    public function __construct(
        private readonly ProductWidthResolver $widthResolver,
    ) {}

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

        // Produtos que entraram com largura CHUTADA (cadastro sem width ou implausível).
        // É a causa silenciosa nº1 de gôndola que "não fecha": o motor empacota com 10cm
        // de palpite e o resultado não bate com a prateleira real. Antes isso só existia
        // no log; agora o usuário vê exatamente quais produtos corrigir no cadastro.
        $report['produtos_sem_dimensao_confiavel'] = $this->widthResolver->fallbackProducts();

        // Alvo de ocupação: até então era campo morto (declarado e nunca lido).
        $occupancy = $this->buildOccupancyMetrics($result);
        $target = $this->targetOccupancyRate();
        $shelfAnalysis = $result->output->shelfAnalysis;

        $report['ocupacao_alvo'] = $target;
        $report['ocupacao_media'] = $occupancy['occupancy_avg'];

        // Contadas nas PRATELEIRAS FÍSICAS (não nos slots): uma prateleira que ficou vazia é
        // exatamente o defeito que este número existe para denunciar — e o slot_analysis, por
        // ser anterior ao overflow, não a enxerga direito. Ver buildOccupancyMetrics.
        $report['prateleiras_abaixo_do_alvo'] = collect($shelfAnalysis !== [] ? $shelfAnalysis : $result->output->slotAnalysis)
            ->filter(fn (array $item): bool => ($item['percentual_uso'] ?? 0) / 100 < $target)
            ->count();

        if ($shelfAnalysis !== []) {
            $report['shelf_analysis'] = $shelfAnalysis;
            $report['prateleiras_vazias'] = collect($shelfAnalysis)->where('segmentos', 0)->count();
        }

        return $report;
    }

    /**
     * Meta de ocupação das prateleiras (0-1).
     *
     * Fecha a lacuna do `PlacementSettings::targetOccupancyRate`, que existia mas nunca
     * era consumido por lógica nenhuma. Nesta fase ele MEDE (quantas prateleiras ficaram
     * abaixo do alvo); agir para fechar o vão é o objetivo das Fases 2 e 3 do plano.
     */
    public function targetOccupancyRate(): float
    {
        return (float) config('plannerate.auto_planogram.placement.target_occupancy_rate', 0.90);
    }

    /**
     * Métricas de ocupação — medidas nas PRATELEIRAS FÍSICAS, sobre os segmentos finais.
     *
     * É a métrica que mede se a gôndola "fechou" com precisão, e a que precisa bater com o
     * que o usuário vê na tela.
     *
     * Antes ela era derivada do `slot_analysis`, e isso a tornava cega: o slot_analysis é
     * montado dentro do laço de slots, ANTES do overflow pass — nada do que o overflow coloca
     * entra na conta. Medido numa gôndola real: ela subiu de 83,3% para 87,0% de ocupação e a
     * métrica ficou cravada em 76,8% nas duas, porque os produtos acrescentados vieram
     * justamente do overflow. O usuário via a gôndola mudar e o número não mexer.
     *
     * O `shelf_analysis` (calculado no fim de tudo, contra a largura real de cada prateleira)
     * é a verdade física. O slot_analysis segue como fallback para o motor guloso, que não
     * trabalha com slots.
     *
     * @return array{occupancy_avg: float|null, occupancy_min: float|null, occupancy_max: float|null}
     */
    public function buildOccupancyMetrics(AutoGenerationResult $result): array
    {
        $source = $result->output->shelfAnalysis !== []
            ? $result->output->shelfAnalysis
            : $result->output->slotAnalysis;

        // percentual_uso vem em 0-100; normalizamos para 0-1 (coluna decimal(5,4)).
        $usages = collect($source)
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
