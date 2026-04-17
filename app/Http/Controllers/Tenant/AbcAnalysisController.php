<?php

namespace App\Http\Controllers\Tenant;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AbcAnalysisService;
use Callcocam\LaravelRaptor\Support\Pages\Index;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AbcAnalysisController
{
    protected AbcAnalysisService $abcService;

    public function __construct()
    {
        $this->abcService = app(AbcAnalysisService::class);
    }

    public function getPages(): array
    {
        return [
            // 'index' => Index::route('/analysis/abc')
            //     ->label('Análise ABC')
            //     ->name('analysis.abc.index')
            //     ->icon('ChartBar')
            //     ->group('Analytics')
            //     ->groupCollapsible(true)
            //     ->order(10)
            //     ->middlewares(['auth', 'verified']),
        ];
    }

    /**
     * Exibe a análise ABC — sempre carrega resultados se category_id ou eans estiverem na URL
     */
    public function index(Request $request): Response
    {
        $weights = $this->getWeights($request);
        $cuts = $this->getCuts($request);
        $filters = $this->prepareFilters($request);
        $tableType = $request->input('table_type', 'monthly_summaries');

        $category = null;

        $this->abcService
            ->setWeights($weights['peso_qtde'], $weights['peso_valor'], $weights['peso_margem'])
            ->setCuts($cuts['corte_a'], $cuts['corte_b']);

        if ($request->filled('category_id')) {
            $category = Category::findOrFail($request->input('category_id'));
            $results = $this->abcService->analyzeByCategory($category, $tableType, $filters);
        } elseif ($request->filled('eans')) {
            $results = $this->abcService->analyzeByEans($request->input('eans'), $tableType, $filters);
        } else {
            $results = $this->abcService->analyzeAll($tableType, $filters);
        }

        $formattedResults = $this->formatResults($results);

        return Inertia::render('tenant/analysis/abc/Index', [
            'initialData' => [
                'results' => $formattedResults->values()->all(),
                'category' => $category ? ['id' => $category->id, 'name' => $category->name] : null,
                'category_id' => $request->input('category_id'),
                'eans' => $request->input('eans', []),
                'filters' => [
                    'table_type' => $tableType,
                    'client_id' => $filters['client_id'] ?? null,
                    'store_id' => $filters['store_id'] ?? null,
                    'date_from' => $filters['date_from'] ?? null,
                    'date_to' => $filters['date_to'] ?? null,
                    'month_from' => $filters['month_from'] ?? null,
                    'month_to' => $filters['month_to'] ?? null,
                ],
                'weights' => $weights,
                'cuts' => $cuts,
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

    private function formatResults(?iterable $results): \Illuminate\Support\Collection
    {
        return collect($results)->map(function ($item) {
            return [
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'ean' => $item['ean'],
                'image_url' => $item['image_url'] ?? null,
                'category_id' => $item['category_id'],
                'category_name' => $item['category_name'],
                'qtde' => (float) $item['qtde'],
                'valor' => (float) $item['valor'],
                'margem' => (float) $item['margem'],
                'media_ponderada' => (float) $item['media_ponderada'],
                'percentual_individual' => (float) $item['percentual_individual'],
                'percentual_acumulado' => (float) $item['percentual_acumulado'],
                'classificacao' => $item['classificacao'],
                'ranking' => (int) $item['ranking'],
                'class_rank' => $item['class_rank'],
                'retirar_do_mix' => (bool) $item['retirar_do_mix'],
                'status' => $item['status'],
            ];
        });
    }
}
