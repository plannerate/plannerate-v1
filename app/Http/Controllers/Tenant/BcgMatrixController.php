<?php

namespace App\Http\Controllers\Tenant;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use App\Services\Analysis\BcgMatrixService;
use Callcocam\LaravelRaptor\Support\Pages\Index;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BcgMatrixController
{
    protected BcgMatrixService $bcgService;

    public function __construct()
    {
        $this->bcgService = app(BcgMatrixService::class);
    }

    public function getPages(): array
    {
        return [
            // 'index' => Index::route('/analysis/bcg')
            //     ->label('Matriz BCG')
            //     ->name('analysis.bcg.index')
            //     ->icon('Grid2x2')
            //     ->group('Analytics')
            //     ->groupCollapsible(true)
            //     ->order(30)
            //     ->middlewares(['auth', 'verified']),
        ];
    }

    /**
     * Exibe a Matriz BCG — carrega resultados se category_id ou eans estiverem na URL
     */
    public function index(Request $request): Response
    {
        $filters = $this->prepareFilters($request);
        $tableType = $request->input('table_type', 'monthly_summaries');

        $category = null;
        $results = null;

        if ($request->filled('category_id')) {
            $category = Category::findOrFail($request->input('category_id'));
            $results = $this->bcgService->analyzeByCategory($category, $tableType, $filters);
        } elseif ($request->filled('eans')) {
            $results = $this->bcgService->analyzeByEans($request->input('eans'), $tableType, $filters);
        } else {
            $results = $this->bcgService->analyzeAll($tableType, $filters);
        }

        $formattedResults = $this->formatResults($results);

        return Inertia::render('tenant/analysis/bcg/Index', [
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
                'summary' => $this->buildSummary($formattedResults),
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

    private function formatResults(?iterable $results): \Illuminate\Support\Collection
    {
        return collect($results)->map(function ($item) {
            return [
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'ean' => $item['ean'],
                'image_url' => $item['image_url'] ?? null,
                'category_id' => $item['category_id'] ?? null,
                'category_name' => $item['category_name'],
                'quadrant' => $item['quadrant'],
                'market_share' => (float) $item['market_share'],
                'growth_rate' => (float) $item['growth_rate'],
                'total_value_current' => (float) $item['total_value_current'],
                'total_value_previous' => (float) $item['total_value_previous'],
                'share_threshold' => (float) $item['share_threshold'],
            ];
        });
    }

    private function buildSummary(\Illuminate\Support\Collection $results): array
    {
        return [
            'total' => $results->count(),
            'star' => $results->where('quadrant', 'star')->count(),
            'cash_cow' => $results->where('quadrant', 'cash_cow')->count(),
            'question_mark' => $results->where('quadrant', 'question_mark')->count(),
            'dog' => $results->where('quadrant', 'dog')->count(),
        ];
    }
}
