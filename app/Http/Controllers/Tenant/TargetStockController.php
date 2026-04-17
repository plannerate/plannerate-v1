<?php

namespace App\Http\Controllers\Tenant;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AbcAnalysisService;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\TargetStockService;
use Callcocam\LaravelRaptor\Support\Pages\Index;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TargetStockController
{
    protected AbcAnalysisService $abcService;

    protected TargetStockService $targetStockService;

    public function __construct()
    {
        $this->abcService = app(AbcAnalysisService::class);
        $this->targetStockService = app(TargetStockService::class);
    }

    public function getPages(): array
    {
        return [
            // 'index' => Index::route('/analysis/target-stock')
            //     ->label('Estoque Alvo')
            //     ->name('analysis.target-stock.index')
            //     ->icon('PackageSearch')
            //     ->group('Analytics')
            //     ->groupCollapsible(true)
            //     ->order(20)
            //     ->middlewares(['auth', 'verified']),
        ];
    }

    /**
     * Exibe a análise de estoque alvo — carrega resultados se category_id ou eans estiverem na URL
     */
    public function index(Request $request): Response
    {
        $weights = $this->getWeights($request);
        $cuts = $this->getCuts($request);
        $filters = $this->prepareFilters($request);
        $parameters = $this->getTargetStockParameters($request);
        $tableType = $request->input('table_type', 'monthly_summaries');

        $category = null;

        $this->abcService
            ->setWeights($weights['peso_qtde'], $weights['peso_valor'], $weights['peso_margem'])
            ->setCuts($cuts['corte_a'], $cuts['corte_b']);

        if ($request->filled('category_id')) {
            $category = Category::findOrFail($request->input('category_id'));
            $abcResults = $this->abcService->analyzeByCategory($category, $tableType, $filters);
        } elseif ($request->filled('eans')) {
            $abcResults = $this->abcService->analyzeByEans($request->input('eans'), $tableType, $filters);
        } else {
            $abcResults = $this->abcService->analyzeAll($tableType, $filters);
        }

        $filtersPayload = $this->buildFiltersPayload($filters, $tableType);

        if ($abcResults->isEmpty()) {
            return Inertia::render('tenant/analysis/target-stock/Index', [
                'initialData' => [
                    'results' => [],
                    'abcSummary' => null,
                    'category' => $category ? ['id' => $category->id, 'name' => $category->name] : null,
                    'category_id' => $request->input('category_id'),
                    'eans' => $request->input('eans', []),
                    'filters' => $filtersPayload,
                    'weights' => $weights,
                    'cuts' => $cuts,
                    'parameters' => $parameters,
                ],
            ]);
        }

        $this->targetStockService
            ->setPeriodType($parameters['period_type'])
            ->setServiceLevels(
                $parameters['nivel_servico_a'],
                $parameters['nivel_servico_b'],
                $parameters['nivel_servico_c']
            )
            ->setCoverageDays(
                $parameters['cobertura_dias_a'],
                $parameters['cobertura_dias_b'],
                $parameters['cobertura_dias_c']
            );

        $targetStockResults = $this->targetStockService->calculateByAbcResults(
            $abcResults->toArray(),
            $tableType,
            $filters,
            []
        );

        return Inertia::render('tenant/analysis/target-stock/Index', [
            'initialData' => [
                'results' => $this->formatTargetStockResults($targetStockResults)->values()->all(),
                'abcSummary' => $this->formatAbcSummary($abcResults),
                'category' => $category ? ['id' => $category->id, 'name' => $category->name] : null,
                'category_id' => $request->input('category_id'),
                'eans' => $request->input('eans', []),
                'filters' => $filtersPayload,
                'weights' => $weights,
                'cuts' => $cuts,
                'parameters' => $parameters,
            ],
        ]);
    }

    // =========================================================================
    // MÉTODOS PRIVADOS
    // =========================================================================

    /**
     * Prepara os filtros com defaults de 3 meses
     */
    private function prepareFilters(Request $request): array
    {
        $filters = [];

        if ($clientId = config('app.current_client_id')) {
            $filters['client_id'] = $clientId;
        } elseif ($request->filled('client_id')) {
            $filters['client_id'] = $request->input('client_id');
        }

        if ($request->filled('store_id')) {
            $filters['store_id'] = $request->input('store_id');
        }

        $tableType = $request->input('table_type', 'monthly_summaries');

        if ($tableType === 'monthly_summaries') {
            $filters['month_from'] = $request->input(
                'month_from',
                Carbon::now()->subMonths(3)->startOfMonth()->format('Y-m-d')
            );
            $filters['month_to'] = $request->input(
                'month_to',
                Carbon::now()->endOfMonth()->format('Y-m-d')
            );
        } else {
            $filters['date_from'] = $request->input(
                'date_from',
                Carbon::now()->subMonths(3)->format('Y-m-d')
            );
            $filters['date_to'] = $request->input(
                'date_to',
                Carbon::now()->format('Y-m-d')
            );
        }

        return $filters;
    }

    private function buildFiltersPayload(array $filters, string $tableType): array
    {
        return [
            'table_type' => $tableType,
            'client_id' => $filters['client_id'] ?? null,
            'store_id' => $filters['store_id'] ?? null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'month_from' => $filters['month_from'] ?? null,
            'month_to' => $filters['month_to'] ?? null,
        ];
    }

    private function getWeights(Request $request): array
    {
        return [
            'peso_qtde' => (float) $request->input('peso_qtde', 0.3),
            'peso_valor' => (float) $request->input('peso_valor', 0.3),
            'peso_margem' => (float) $request->input('peso_margem', 0.4),
        ];
    }

    private function getCuts(Request $request): array
    {
        return [
            'corte_a' => (float) $request->input('corte_a', 0.80),
            'corte_b' => (float) $request->input('corte_b', 0.85),
        ];
    }

    private function getTargetStockParameters(Request $request): array
    {
        return [
            'period_type' => $request->input('period_type', 'daily'),
            'nivel_servico_a' => (float) $request->input('nivel_servico_a', 0.7),
            'nivel_servico_b' => (float) $request->input('nivel_servico_b', 0.8),
            'nivel_servico_c' => (float) $request->input('nivel_servico_c', 0.9),
            'cobertura_dias_a' => (int) $request->input('cobertura_dias_a', 2),
            'cobertura_dias_b' => (int) $request->input('cobertura_dias_b', 5),
            'cobertura_dias_c' => (int) $request->input('cobertura_dias_c', 7),
        ];
    }

    private function formatTargetStockResults(?iterable $results): \Illuminate\Support\Collection
    {
        return collect($results)->map(function ($item) {
            return [
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'ean' => $item['ean'],
                'classificacao' => $item['classificacao'],
                'demanda_media' => (float) $item['demanda_media'],
                'desvio_padrao' => (float) $item['desvio_padrao'],
                'variabilidade' => (float) $item['variabilidade'],
                'cobertura_dias' => (int) $item['cobertura_dias'],
                'nivel_servico' => (float) $item['nivel_servico'],
                'z_score' => (float) $item['z_score'],
                'estoque_seguranca' => (int) $item['estoque_seguranca'],
                'estoque_minimo' => (int) $item['estoque_minimo'],
                'estoque_alvo' => (int) $item['estoque_alvo'],
                'estoque_atual' => (int) $item['estoque_atual'],
                'permite_frentes' => $item['permite_frentes'],
                'alerta_variabilidade' => (bool) $item['alerta_variabilidade'],
            ];
        });
    }

    private function formatAbcSummary(\Illuminate\Support\Collection $abcResults): array
    {
        return [
            'total' => $abcResults->count(),
            'class_a' => $abcResults->where('classificacao', 'A')->count(),
            'class_b' => $abcResults->where('classificacao', 'B')->count(),
            'class_c' => $abcResults->where('classificacao', 'C')->count(),
        ];
    }
}
