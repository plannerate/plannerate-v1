<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers;

use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\GondolaAnalysis;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\AbcAnalysisService;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\BcgAnalysisService;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\GondolaSpaceService;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\PaperAnalysisService;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\TargetStockService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class GondolaAnalysisController extends Controller
{
    public function calculateAbcApi(Request $request, string $gondola)
    {
        $gondolaModel = Gondola::find($gondola);

        if (! $gondolaModel) {
            return redirect()->back()->withErrors(['error' => 'Gôndola não encontrada.']);
        }

        $tableType = $request->input('table_type', 'monthly_summaries');
        $filters = $this->buildFilters($request, $gondolaModel);

        try {
            $service = app(AbcAnalysisService::class)
                ->setWeights(
                    (float) $request->input('peso_qtde', 0.3),
                    (float) $request->input('peso_valor', 0.3),
                    (float) $request->input('peso_margem', 0.4),
                )
                ->setCuts(
                    (float) $request->input('corte_a', 0.80),
                    (float) $request->input('corte_b', 0.85),
                );

            // Usa apenas os produtos fisicamente alocados na gôndola (via layers)
            // ignora a categoria do planograma, que pode não refletir o estado atual do planograma
            $productIds = $service->getProductIdsByGondola($gondola);
            $results = $service->analyzeByProductIds($productIds, $tableType, $filters);

            $summary = $this->buildAbcSummary($results->toArray());

            GondolaAnalysis::updateOrCreate(
                ['gondola_id' => $gondolaModel->id, 'type' => 'abc'],
                [
                    'data' => [
                        'results' => $results->toArray(),
                        'filters' => $filters,
                        'weights' => [
                            'peso_qtde' => $request->input('peso_qtde', 0.3),
                            'peso_valor' => $request->input('peso_valor', 0.3),
                            'peso_margem' => $request->input('peso_margem', 0.4),
                        ],
                        'cuts' => [
                            'corte_a' => $request->input('corte_a', 0.80),
                            'corte_b' => $request->input('corte_b', 0.85),
                        ],
                    ],
                    'summary' => $summary,
                    'analyzed_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('ABC Analysis failed', ['gondola' => $gondola, 'error' => $e->getMessage()]);

            return redirect()->back()->with('flash', ['error' => 'Erro ao calcular análise ABC: '.$e->getMessage()]);
        }

        return redirect()->back();
    }

    public function calculateTargetStockApi(Request $request, string $gondola)
    {
        $gondolaModel = Gondola::find($gondola);

        if (! $gondolaModel) {
            return redirect()->back()->withErrors(['error' => 'Gôndola não encontrada.']);
        }

        $abcAnalysis = GondolaAnalysis::getLatestAbcAnalysis($gondolaModel->id);

        if (! $abcAnalysis) {
            return redirect()->back()->with('flash', ['error' => 'Execute a análise ABC antes do estoque alvo.']);
        }

        $tableType = $request->input('table_type', 'monthly_summaries');
        $filters = $this->buildFilters($request, $gondolaModel);

        try {
            $service = app(TargetStockService::class)
                ->setServiceLevels(
                    (float) $request->input('nivel_servico_a', 0.7),
                    (float) $request->input('nivel_servico_b', 0.8),
                    (float) $request->input('nivel_servico_c', 0.9),
                )
                ->setCoverageDays(
                    (int) $request->input('cobertura_dias_a', 2),
                    (int) $request->input('cobertura_dias_b', 5),
                    (int) $request->input('cobertura_dias_c', 7),
                );

            $abcResults = $abcAnalysis->data['results'] ?? [];
            $results = $service->calculateByAbcResults($abcResults, $tableType, $filters);

            $summary = $this->buildStockSummary($results->toArray());

            GondolaAnalysis::updateOrCreate(
                ['gondola_id' => $gondolaModel->id, 'type' => 'stock'],
                [
                    'data' => [
                        'results' => $results->toArray(),
                        'filters' => $filters,
                        'parameters' => [
                            'nivel_servico_a' => $request->input('nivel_servico_a', 0.7),
                            'nivel_servico_b' => $request->input('nivel_servico_b', 0.8),
                            'nivel_servico_c' => $request->input('nivel_servico_c', 0.9),
                            'cobertura_dias_a' => $request->input('cobertura_dias_a', 2),
                            'cobertura_dias_b' => $request->input('cobertura_dias_b', 5),
                            'cobertura_dias_c' => $request->input('cobertura_dias_c', 7),
                        ],
                    ],
                    'summary' => $summary,
                    'analyzed_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Target Stock Analysis failed', ['gondola' => $gondola, 'error' => $e->getMessage()]);

            return redirect()->back()->with('flash', ['error' => 'Erro ao calcular estoque alvo: '.$e->getMessage()]);
        }

        return redirect()->back();
    }

    public function calculatePaperApi(Request $request, string $gondola)
    {
        $gondolaModel = Gondola::find($gondola);

        if (! $gondolaModel) {
            return redirect()->back()->withErrors(['error' => 'Gôndola não encontrada.']);
        }

        $tableType = $request->input('table_type', 'monthly_summaries');
        $currentFilters = $this->buildFilters($request, $gondolaModel);
        $previousFilters = $this->buildPreviousFilters($request);

        try {
            $service = app(PaperAnalysisService::class);

            // Limiar fixo só quando enviado explicitamente; sem o parâmetro, o
            // service usa a mediana de crescimento por categoria (comportamento padrão)
            if ($request->filled('growth_threshold')) {
                $service->setGrowthThreshold((float) $request->input('growth_threshold'));
            }

            $productIds = $service->getProductIdsByGondola($gondola);
            $results = $service->analyzeByProductIds($productIds, $tableType, $currentFilters, $previousFilters);

            $summary = $this->buildPaperSummary($results->toArray());

            GondolaAnalysis::updateOrCreate(
                ['gondola_id' => $gondolaModel->id, 'type' => 'paper'],
                [
                    'data' => [
                        'results' => $results->toArray(),
                        'filters' => $currentFilters,
                        'previous_filters' => $previousFilters,
                        'parameters' => [
                            'table_type' => $tableType,
                            // null = mediana de crescimento por categoria
                            'growth_threshold' => $request->filled('growth_threshold')
                                ? (float) $request->input('growth_threshold')
                                : null,
                        ],
                    ],
                    'summary' => $summary,
                    'analyzed_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('PaperAnalysis failed', ['gondola' => $gondola, 'error' => $e->getMessage()]);

            return redirect()->back()->with('flash', ['error' => 'Erro ao calcular Análise de Papel: '.$e->getMessage()]);
        }

        return redirect()->back();
    }

    /**
     * Análise BCG: matriz de quadrantes com eixos configuráveis, período único.
     *
     * Ao contrário da Análise de Papel, não busca período anterior (não há eixo de
     * crescimento), e cruza o resultado com o espaço ocupado na gôndola — é o
     * cruzamento que produz a ação de planograma.
     */
    public function calculateBcgApi(Request $request, string $gondola)
    {
        $gondolaModel = Gondola::find($gondola);

        if (! $gondolaModel) {
            return redirect()->back()->withErrors(['error' => 'Gôndola não encontrada.']);
        }

        $tableType = $request->input('table_type', 'monthly_summaries');
        $filters = $this->buildFilters($request, $gondolaModel);

        $xAxis = $request->input('x_axis', 'quantidade');
        $yAxis = $request->input('y_axis', 'margem');
        $thresholdMethod = $request->input('threshold_method', BcgAnalysisService::THRESHOLD_MEDIAN);
        $classifyBy = $request->input('classify_by', 'categoria');

        try {
            // Os setters lançam InvalidArgumentException em entrada inválida (eixos
            // iguais, métrica ou nível desconhecido) — cai no catch abaixo e vira flash
            // de erro, já que o projeto não usa FormRequest aqui.
            $service = app(BcgAnalysisService::class)
                ->setAxes($xAxis, $yAxis)
                ->setThresholdMethod($thresholdMethod)
                ->setClassifyBy($classifyBy);

            $productIds = $service->getProductIdsByGondola($gondola);
            $results = $service->analyzeByProductIds($productIds, $tableType, $filters);

            $space = app(GondolaSpaceService::class)->spaceByProduct($gondola);
            $results = $service->withSpace($results, $space);

            $summary = $this->buildBcgSummary($results->toArray());

            GondolaAnalysis::updateOrCreate(
                ['gondola_id' => $gondolaModel->id, 'type' => 'bcg'],
                [
                    'data' => [
                        'results' => $results->toArray(),
                        'filters' => $filters,
                        'parameters' => [
                            'table_type' => $tableType,
                            // A UI depende dos eixos para compor os rótulos dos
                            // quadrantes — as chaves do backend são agnósticas.
                            'x_axis' => $xAxis,
                            'y_axis' => $yAxis,
                            'threshold_method' => $thresholdMethod,
                            'classify_by' => $classifyBy,
                        ],
                    ],
                    'summary' => $summary,
                    'analyzed_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('BcgAnalysis failed', ['gondola' => $gondola, 'error' => $e->getMessage()]);

            return redirect()->back()->with('flash', ['error' => 'Erro ao calcular Análise BCG: '.$e->getMessage()]);
        }

        return redirect()->back();
    }

    public function clearAnalysisApi(Request $request, string $gondola)
    {
        GondolaAnalysis::where('gondola_id', $gondola)->delete();

        return redirect()->back();
    }

    private function buildFilters(Request $request, Gondola $gondola): array
    {
        $filters = [
            'tenant_id' => $gondola->tenant_id,
            'gondola_id' => $gondola->id,
        ];

        if ($request->filled('date_from')) {
            $filters['date_from'] = $request->input('date_from');
        }

        if ($request->filled('date_to')) {
            $filters['date_to'] = $request->input('date_to');
        }

        if ($request->filled('start_month')) {
            $filters['start_month'] = $request->input('start_month');
        }

        if ($request->filled('end_month')) {
            $filters['end_month'] = $request->input('end_month');
        }

        return $filters;
    }

    private function buildAbcSummary(array $results): array
    {
        $classA = count(array_filter($results, fn ($r) => ($r['classificacao'] ?? '') === 'A'));
        $classB = count(array_filter($results, fn ($r) => ($r['classificacao'] ?? '') === 'B'));
        $classC = count(array_filter($results, fn ($r) => ($r['classificacao'] ?? '') === 'C'));

        return [
            'total_products' => count($results),
            'class_a_count' => $classA,
            'class_b_count' => $classB,
            'class_c_count' => $classC,
        ];
    }

    /**
     * Extrai filtros de período anterior da request (prefixo prev_)
     */
    private function buildPreviousFilters(Request $request): array
    {
        $previous = [];

        if ($request->filled('prev_date_from')) {
            $previous['date_from'] = $request->input('prev_date_from');
        }

        if ($request->filled('prev_date_to')) {
            $previous['date_to'] = $request->input('prev_date_to');
        }

        if ($request->filled('prev_start_month')) {
            $previous['start_month'] = $request->input('prev_start_month');
        }

        if ($request->filled('prev_end_month')) {
            $previous['end_month'] = $request->input('prev_end_month');
        }

        return $previous;
    }

    /**
     * Agrega os resultados da Análise de Papel por papel estratégico.
     */
    private function buildPaperSummary(array $results): array
    {
        $leader = count(array_filter($results, fn ($r) => ($r['role'] ?? '') === 'leader'));
        $anchor = count(array_filter($results, fn ($r) => ($r['role'] ?? '') === 'anchor'));
        $rising = count(array_filter($results, fn ($r) => ($r['role'] ?? '') === 'rising'));
        $lagging = count(array_filter($results, fn ($r) => ($r['role'] ?? '') === 'lagging'));

        return [
            'total' => count($results),
            'leader' => $leader,
            'anchor' => $anchor,
            'rising' => $rising,
            'lagging' => $lagging,
        ];
    }

    /**
     * Agrega os resultados da Análise BCG por quadrante.
     *
     * `espaco_mal_alocado` é a contagem que interessa ao merchandiser: produtos cujo
     * espaço na gôndola está desalinhado do valor que entregam (para mais ou para menos).
     */
    private function buildBcgSummary(array $results): array
    {
        $countByQuadrant = fn (string $quadrant) => count(
            array_filter($results, fn ($r) => ($r['quadrant'] ?? '') === $quadrant)
        );

        return [
            'total' => count($results),
            'alto_alto' => $countByQuadrant('alto_alto'),
            'forte_x' => $countByQuadrant('forte_x'),
            'forte_y' => $countByQuadrant('forte_y'),
            'baixo_baixo' => $countByQuadrant('baixo_baixo'),
            'sem_venda' => count(array_filter($results, fn ($r) => ($r['sem_venda'] ?? false) === true)),
            'borderline' => count(array_filter($results, fn ($r) => ($r['is_borderline'] ?? false) === true)),
            'espaco_mal_alocado' => count(array_filter(
                $results,
                fn ($r) => in_array($r['acao_espaco'] ?? null, ['aumentar', 'reduzir'], true)
            )),
        ];
    }

    private function buildStockSummary(array $results): array
    {
        $totalTarget = array_sum(array_column($results, 'estoque_alvo'));
        $totalCurrent = array_sum(array_column($results, 'estoque_atual'));

        $above = count(array_filter($results, fn ($r) => ($r['estoque_atual'] ?? 0) >= ($r['estoque_alvo'] ?? 0)));
        $below = count($results) - $above;

        return [
            'total_products' => count($results),
            'total_target_stock' => $totalTarget,
            'total_current_stock' => $totalCurrent,
            'products_above_target' => $above,
            'products_below_target' => $below,
        ];
    }
}
