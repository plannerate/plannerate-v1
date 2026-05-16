<?php

namespace App\Services\AutoPlanogram;

use App\Enums\PlacementFailureReason;
use App\Services\AutoPlanogram\Adjacency\AdjacencyResolverInterface;
use App\Services\AutoPlanogram\DTO\PlacementResult;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\DTO\PlanogramOutput;
use App\Services\AutoPlanogram\Grouping\BlockGrouperInterface;
use App\Services\AutoPlanogram\Placement\PlacementEngineInterface;
use App\Services\AutoPlanogram\Placement\PlanogramWriterInterface;
use App\Services\AutoPlanogram\Scoring\ProductScorerInterface;
use App\Services\AutoPlanogram\Validation\PlanogramValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquestrador do pipeline de geração automática de planogramas.
 *
 * Pipeline:
 * 1. Score — pontua produtos por importância
 * 2. Group — agrupa em blocos coesos
 * 3. Adjacency — ordena blocos respeitando regras de adjacência
 * 4. Placement — distribui blocos pelas prateleiras
 * 5. Validation — verifica integridade do resultado
 * 6. Write — persiste no banco em transação
 */
final class AutoPlanogramService
{
    public function __construct(
        private readonly ProductScorerInterface $scorer,
        private readonly FacingCalculatorService $facingCalculator,
        private readonly BlockGrouperInterface $grouper,
        private readonly AdjacencyResolverInterface $adjacency,
        private readonly PlacementEngineInterface $placement,
        private readonly PlanogramValidator $validator,
        private readonly PlanogramWriterInterface $writer,
    ) {}

    public function generate(PlanogramInput $input): PlanogramOutput
    {
        Log::info('AutoPlanogramService: iniciando geração', [
            'gondola_id' => $input->gondolaId,
            'planogram_id' => $input->planogramId,
            'products_count' => $input->products->count(),
            'strategy' => $input->settings->strategy,
        ]);

        $this->logWidthQuality($input);

        $scored = $this->scorer->score($input->products, $input->settings);

        Log::info('AutoPlanogramService: produtos pontuados', ['count' => $scored->count()]);

        $withFacings = $this->facingCalculator->calculateIdealFacings($scored, $input->settings);

        $blocks = $this->grouper->group($withFacings, $input->settings);
        $ordered = $this->adjacency->resolve($blocks, $input->settings);
        $placementResult = $this->placement->place($ordered, $input->sections, $input->settings);
        $placed = $placementResult->placedSegments;

        Log::info('AutoPlanogramService: segmentos posicionados', ['count' => $placed->count()]);

        $report = $this->validator->validate($placed, $input, $placementResult);

        $this->logCapacityAnalysis($input, $placementResult);

        DB::transaction(function () use ($input, $placed) {
            $this->writer->write($input->gondolaId, $input->sections, $placed);
        });

        Log::info('AutoPlanogramService: geração concluída', [
            'gondola_id' => $input->gondolaId,
            'segments_placed' => $placed->count(),
            'validation_passed' => $report->passed,
        ]);

        return new PlanogramOutput($input->gondolaId, $placed, $placementResult->rejectedProducts, $report);
    }

    private function logWidthQuality(PlanogramInput $input): void
    {
        $widths = $input->products->map(fn ($p) => (float) ($p->width ?? 0));

        Log::info('AutoPlanogramService: qualidade de dados de width', [
            'total_produtos' => $widths->count(),
            'sem_width' => $input->products->whereNull('width')->count(),
            'width_zero' => $widths->filter(fn ($w) => $w <= 0)->count(),
            'width_suspeito' => $widths->filter(fn ($w) => $w > 60)->count(),
            'width_valido' => $widths->filter(fn ($w) => $w > 0 && $w <= 60)->count(),
            'largura_media_cm' => round($widths->filter(fn ($w) => $w > 0 && $w <= 60)->avg() ?? 0, 2),
        ]);
    }

    private function logCapacityAnalysis(PlanogramInput $input, PlacementResult $result): void
    {
        $totalProducts = $input->products->count();
        $placed = $result->placedSegments->count();
        $rejectedSpace = $result->rejectedProducts
            ->filter(fn ($r) => $r['reason'] === PlacementFailureReason::NoHorizontalSpace)
            ->count();
        $rejectedHeight = $result->rejectedProducts
            ->filter(fn ($r) => $r['reason'] === PlacementFailureReason::HeightExceedsShelf)
            ->count();

        $mixExceedsGondola = $rejectedSpace > 0;

        Log::info('AutoPlanogramService: análise de capacidade', [
            'produtos_com_venda' => $totalProducts,
            'posicionados' => $placed,
            'rejeitados_sem_espaco' => $rejectedSpace,
            'rejeitados_altura' => $rejectedHeight,
            'mix_excede_gondola' => $mixExceedsGondola,
            'taxa_cobertura' => round($placed / max($totalProducts, 1), 3),
            'recomendacao' => $mixExceedsGondola
                ? "Mix excede capacidade da gôndola. Considere ampliar a gôndola ou reduzir o mix em {$rejectedSpace} produto(s)."
                : 'Mix dentro da capacidade.',
        ]);
    }
}
