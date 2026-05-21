<?php

namespace App\Services\AutoPlanogram;

use App\Enums\PlacementFailureReason;
use App\Services\AutoPlanogram\Adjacency\AdjacencyResolverInterface;
use App\Services\AutoPlanogram\DTO\PlacementResult;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\DTO\PlanogramOutput;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\Grouping\BlockGrouperInterface;
use App\Services\AutoPlanogram\Placement\PlacementEngineInterface;
use App\Services\AutoPlanogram\Placement\PlanogramWriterInterface;
use App\Services\AutoPlanogram\Placement\RejectedProductsWriter;
use App\Services\AutoPlanogram\Placement\TemplatePlacementEngine;
use App\Services\AutoPlanogram\Placement\VerticalBlockPlacer;
use App\Services\AutoPlanogram\Scoring\ProductScorerInterface;
use App\Services\AutoPlanogram\Template\SlotSuggestionGenerator;
use App\Services\AutoPlanogram\Validation\PlanogramValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquestrador do pipeline de geração de planogramas.
 *
 * Modo template (quando settings->templateId !== null):
 * 1. Score (scoreOrNeutral) — ordena pool por relevância; neutro se sem dados de venda
 * 2. TemplatePlacementEngine — distribui produtos pelos slots do subtemplate
 * 3. Validation — verifica integridade do resultado
 * 4. Write — persiste no banco em transação
 *
 * Modo automático (fallback, sem template):
 * 1. Score — pontua produtos por importância
 * 2. FacingCalculator — calcula facings ideais
 * 3. VerticalBlockPlacer — reserva colunas verticais para top N% de score
 * 4. Group — agrupa o restante em blocos coesos por categoria
 * 5. Adjacency — ordena blocos respeitando regras de adjacência
 * 6. GreedyShelfPlacer — distribui blocos pelo espaço restante
 * 7. Validation — verifica integridade do resultado
 * 8. Write — persiste no banco em transação
 */
final class AutoPlanogramService
{
    public function __construct(
        private readonly ProductScorerInterface $scorer,
        private readonly FacingCalculatorService $facingCalculator,
        private readonly BlockGrouperInterface $grouper,
        private readonly AdjacencyResolverInterface $adjacency,
        private readonly PlacementEngineInterface $placement,
        private readonly TemplatePlacementEngine $templatePlacement,
        private readonly VerticalBlockPlacer $verticalPlacer,
        private readonly PlanogramValidator $validator,
        private readonly PlanogramWriterInterface $writer,
        private readonly SlotSuggestionGenerator $suggestionGenerator,
        private readonly RejectedProductsWriter $rejectedProductsWriter,
    ) {}

    public function generate(PlanogramInput $input): PlanogramOutput
    {
        Log::info('AutoPlanogramService: iniciando geração', [
            'gondola_id' => $input->gondolaId,
            'planogram_id' => $input->planogramId,
            'products_count' => $input->products->count(),
            'strategy' => $input->settings->strategy,
            'mode' => $input->settings->usesTemplate() ? 'template' : 'automatic',
            'template_id' => $input->settings->templateId,
            'score_mode' => $input->settings->usesTemplate() ? 'optional' : 'required',
        ]);

        if ($input->settings->usesTemplate()) {
            $scored = $this->scorer->scoreOrNeutral($input->products, $input->settings);
        } else {
            $this->logWidthQuality($input);
            $scored = $this->scorer->score($input->products, $input->settings);
        }

        $scoreType = $scored->first()?->metadata['score_type'] ?? 'composite';
        Log::info('AutoPlanogramService: scoring concluído', [
            'count' => $scored->count(),
            'score_type' => $scoreType,
            'has_sales' => $scoreType !== 'neutral',
        ]);

        if ($input->settings->usesTemplate()) {
            return $this->generateWithTemplate($input, $scored, $scoreType);
        }

        Log::info('AutoPlanogramService: produtos pontuados', ['count' => $scored->count()]);

        $withFacings = $this->facingCalculator->calculateIdealFacings($scored, $input->settings);

        // Marcar top N% por score como candidatos a bloco vertical
        $verticalCandidates = $this->identifyVerticalBlockCandidates(
            $withFacings,
            $input->settings->verticalBlockThreshold,
        );
        $withFacings = $this->markVerticalCandidates($withFacings, $verticalCandidates);

        $blocks = $this->grouper->group($withFacings, $input->settings);
        $ordered = $this->adjacency->resolve($blocks, $input->settings);

        // Bloco vertical primeiro — reserva posições X nas shelves
        $verticalResult = $this->verticalPlacer->place(
            $ordered,
            $input->sections,
            $input->settings,
            $input->settings->verticalBlockMinShelves,
        );

        // GreedyShelfPlacer processa o restante, respeitando o espaço reservado
        $greedyResult = $this->placement->place(
            $verticalResult->remainingBlocks,
            $input->sections,
            $input->settings,
            $verticalResult->reservedWidthPerShelf,
        );

        // Mesclar e renumerar orderings por shelf
        $allSegments = $verticalResult->verticalSegments->merge($greedyResult->placedSegments);
        $allSegments = $this->renumberOrderings($allSegments);
        $allRejected = $greedyResult->rejectedProducts;

        $fullResult = new PlacementResult($allSegments, $allRejected);

        Log::info('AutoPlanogramService: segmentos posicionados', [
            'verticais' => $verticalResult->verticalSegments->count(),
            'greedy' => $greedyResult->placedSegments->count(),
            'total' => $allSegments->count(),
        ]);

        $report = $this->validator->validate($allSegments, $input, $fullResult);

        $this->logCapacityAnalysis($input, $fullResult);

        $output = new PlanogramOutput($input->gondolaId, $allSegments, $allRejected, $report, $scoreType);

        DB::transaction(function () use ($input, $allSegments, $output): void {
            $this->writer->write($input->gondolaId, $input->sections, $allSegments);
            $this->rejectedProductsWriter->write($input->planogramId, $input->gondolaId, $input->tenantId, $output);
        });

        Log::info('AutoPlanogramService: geração concluída', [
            'gondola_id' => $input->gondolaId,
            'segments_placed' => $allSegments->count(),
            'validation_passed' => $report->passed,
        ]);

        return $output;
    }

    private function generateWithTemplate(PlanogramInput $input, Collection $scored, string $scoreType): PlanogramOutput
    {
        // Re-ordenar pool por score para que produtos mais relevantes ocupem slots primeiro
        $scoreOrder = $scored->pluck('product.id')->flip();
        $sortedProducts = $input->settings->products
            ->sortBy(fn ($p) => $scoreOrder->get($p->id, PHP_INT_MAX))
            ->values();

        // Extrair métricas brutas do scored para priorização por zona térmica
        $zoneMetricsMap = $scored->mapWithKeys(fn ($sp) => [
            $sp->product->id => [
                'giro' => (float) ($sp->metadata['raw_quantity'] ?? 0),
                'margem' => (float) ($sp->metadata['raw_margem'] ?? 0.0),
            ],
        ])->all();

        $settings = $input->settings
            ->withExtras($input->settings->tenantId, $input->settings->weights)
            ->withProducts($sortedProducts)
            ->withZoneMetrics($zoneMetricsMap);

        $result = $this->templatePlacement->place(
            collect(),
            $input->sections,
            $settings,
        );

        $allSegments = $this->renumberOrderings($result->placedSegments);
        $fullResult = new PlacementResult($allSegments, $result->rejectedProducts);

        $report = $this->validator->validate($allSegments, $input, $fullResult);

        $this->logCapacityAnalysis($input, $fullResult);

        $suggestions = $this->suggestionGenerator->generate($result->slotAnalysis);

        $templateOutput = new PlanogramOutput(
            gondolaId: $input->gondolaId,
            placedSegments: $allSegments,
            rejectedProducts: $result->rejectedProducts,
            validationReport: $report,
            scoreType: $scoreType,
            slotAnalysis: $result->slotAnalysis,
            suggestions: $suggestions,
            modulesMismatch: $result->modulesMismatch,
            templateModules: $result->templateModules,
            gondolaModules: $result->gondolaModules,
            subtemplateId: $result->subtemplateId,
        );

        DB::transaction(function () use ($input, $allSegments, $templateOutput): void {
            $this->writer->write($input->gondolaId, $input->sections, $allSegments);
            $this->rejectedProductsWriter->write($input->planogramId, $input->gondolaId, $input->tenantId, $templateOutput);
        });

        Log::info('AutoPlanogramService: geração com template concluída', [
            'gondola_id' => $input->gondolaId,
            'template_id' => $settings->templateId,
            'segments_placed' => $allSegments->count(),
            'validation_passed' => $report->passed,
            'score_type' => $scoreType,
            'sugestoes' => count($suggestions),
        ]);

        return $templateOutput;
    }

    /**
     * Retorna um set de product_id dos top N% de score.
     *
     * @param  Collection<int, ScoredProduct>  $scoredProducts
     * @return Collection<string, true> product_id => true (para lookup O(1))
     */
    private function identifyVerticalBlockCandidates(Collection $scoredProducts, float $threshold): Collection
    {
        $total = $scoredProducts->count();

        if ($total === 0 || $threshold <= 0.0) {
            return collect();
        }

        $topN = (int) ceil($total * $threshold);

        $candidates = $scoredProducts
            ->sortByDesc('score')
            ->take($topN)
            ->pluck('product.id')
            ->flip();

        Log::info('AutoPlanogramService: candidatos a bloco vertical', [
            'total_produtos' => $total,
            'threshold' => $threshold,
            'candidatos' => $candidates->count(),
        ]);

        return $candidates;
    }

    /**
     * @param  Collection<int, ScoredProduct>  $scoredProducts
     * @param  Collection<string, true>  $candidates
     * @return Collection<int, ScoredProduct>
     */
    private function markVerticalCandidates(Collection $scoredProducts, Collection $candidates): Collection
    {
        return $scoredProducts->map(function (ScoredProduct $sp) use ($candidates): ScoredProduct {
            return new ScoredProduct(
                productId: $sp->productId,
                ean: $sp->ean,
                score: $sp->score,
                product: $sp->product,
                metadata: array_merge($sp->metadata, [
                    'is_vertical_block' => $candidates->has($sp->product->id),
                ]),
            );
        })->values();
    }

    /**
     * Renumera orderings dentro de cada shelf, ordenando por position.
     *
     * @param  Collection<int, PlacedSegment>  $segments
     * @return Collection<int, PlacedSegment>
     */
    private function renumberOrderings(Collection $segments): Collection
    {
        return $segments
            ->groupBy('shelfId')
            ->flatMap(function (Collection $shelfSegments): Collection {
                return $shelfSegments
                    ->sortBy('position')
                    ->values()
                    ->map(function (DTO\PlacedSegment $seg, int $i): DTO\PlacedSegment {
                        return new DTO\PlacedSegment(
                            sectionId: $seg->sectionId,
                            shelfId: $seg->shelfId,
                            ordering: $i,
                            position: $seg->position,
                            width: $seg->width,
                            distributedWidth: $seg->distributedWidth,
                            layers: $seg->layers,
                            isVerticalBlock: $seg->isVerticalBlock,
                        );
                    });
            })
            ->values();
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
