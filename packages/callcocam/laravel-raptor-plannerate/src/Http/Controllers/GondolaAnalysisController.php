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
            $service = app(PaperAnalysisService::class)
                ->setGrowthThreshold((float) $request->input('growth_threshold', 0.0));

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
                            'growth_threshold' => $request->input('growth_threshold', 0.0),
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
