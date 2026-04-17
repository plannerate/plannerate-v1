<?php

namespace App\Services\Analysis;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\MonthlySalesSummary;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Sale;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service para análise de Matriz BCG de produtos
 *
 * Classifica produtos em 4 quadrantes baseado em:
 * - Eixo X (Market Share Relativo): % do produto no total de vendas da categoria
 * - Eixo Y (Taxa de Crescimento): variação % entre primeira e segunda metade do período
 *
 * Quadrantes:
 * - Star: alto share + crescimento positivo
 * - Cash Cow: alto share + crescimento negativo/zero
 * - Question Mark: baixo share + crescimento positivo
 * - Dog: baixo share + crescimento negativo/zero
 */
class BcgMatrixService
{
    /**
     * Executa análise BCG por categoria (incluindo hierarquia)
     */
    public function analyzeByCategory(
        Category $category,
        string $tableType = 'monthly_summaries',
        array $filters = []
    ): Collection {
        $productIds = $this->getProductIdsByCategory($category);

        if (empty($productIds)) {
            Log::warning('BCG Analysis - Nenhum produto encontrado para a categoria', [
                'category_id' => $category->id,
            ]);

            return collect();
        }

        $codigosErp = Product::query()
            ->whereIn('id', $productIds)
            ->whereNotNull('codigo_erp')
            ->pluck('codigo_erp')
            ->toArray();

        if (empty($codigosErp)) {
            return collect();
        }

        return $this->performAnalysis($codigosErp, $productIds, $tableType, $filters);
    }

    /**
     * Executa análise BCG por lista de EANs
     */
    public function analyzeByEans(
        array $eans,
        string $tableType = 'monthly_summaries',
        array $filters = []
    ): Collection {
        if (empty($eans)) {
            return collect();
        }

        $products = Product::whereIn('ean', $eans)->get();

        if ($products->isEmpty()) {
            return collect();
        }

        $productIds = $products->pluck('id')->toArray();
        $codigosErp = Product::query()
            ->whereIn('id', $productIds)
            ->whereNotNull('codigo_erp')
            ->pluck('codigo_erp')
            ->toArray();

        if (empty($codigosErp)) {
            return collect();
        }

        return $this->performAnalysis($codigosErp, $productIds, $tableType, $filters);
    }

    /**
     * Executa análise BCG para todos os produtos do tenant (sem filtro de categoria ou EANs)
     *
     * @param  string  $tableType  'sales' ou 'monthly_summaries'
     * @param  array  $filters  Filtros adicionais (datas, store_id)
     */
    public function analyzeAll(string $tableType = 'monthly_summaries', array $filters = []): Collection
    {
        $products = Product::query()
            ->whereNotNull('codigo_erp')
            ->select('id', 'codigo_erp')
            ->get();

        if ($products->isEmpty()) {
            Log::warning('BCG Analysis - Nenhum produto com codigo_erp encontrado no tenant');

            return collect();
        }

        $codigosErp = $products->pluck('codigo_erp')->unique()->toArray();
        $productIds = $products->pluck('id')->toArray();

        Log::info('BCG Analysis - analyzeAll', [
            'table_type' => $tableType,
            'products_count' => count($productIds),
        ]);

        return $this->performAnalysis($codigosErp, $productIds, $tableType, $filters);
    }

    // =========================================================================
    // MÉTODOS PRIVADOS
    // =========================================================================

    /**
     * Executa o cálculo BCG para um conjunto de produtos
     */
    private function performAnalysis(
        array $codigosErp,
        array $productIds,
        string $tableType,
        array $filters
    ): Collection {
        $dateRange = $this->calculateDateRange($filters, $tableType);

        $salesData = $this->getSalesBothPeriods($codigosErp, $tableType, $filters, $dateRange['midpoint']);

        if ($salesData->isEmpty()) {
            Log::warning('BCG Analysis - Nenhuma venda encontrada', [
                'table_type' => $tableType,
                'codigos_erp_count' => count($codigosErp),
            ]);

            return collect();
        }

        Log::info('BCG Analysis - performAnalysis', [
            'table_type' => $tableType,
            'codigos_erp_count' => count($codigosErp),
            'sales_rows' => $salesData->count(),
            'midpoint' => $dateRange['midpoint']->format('Y-m-d'),
        ]);

        $products = Product::with(['category'])->whereIn('id', $productIds)->get()->keyBy('id');

        return $this->classifyQuadrants($salesData, $products);
    }

    /**
     * Classifica cada produto em um quadrante BCG
     *
     * Threshold de market share: mediana dos shares do conjunto
     * Threshold de crescimento: 0% (crescimento positivo = high growth)
     */
    private function classifyQuadrants(Collection $salesData, Collection $products): Collection
    {
        $totalValueCurrent = $salesData->sum('value_current');

        $withMetrics = $salesData->map(function ($item) use ($products, $totalValueCurrent) {
            $product = $products->get($item->product_id);
            $valueCurrent = (float) $item->value_current;
            $valuePrevious = (float) $item->value_previous;

            $marketShare = $totalValueCurrent > 0
                ? ($valueCurrent / $totalValueCurrent) * 100
                : 0.0;

            $growthRate = $valuePrevious > 0
                ? (($valueCurrent - $valuePrevious) / $valuePrevious) * 100
                : ($valueCurrent > 0 ? 100.0 : 0.0);

            return [
                'product_id' => $item->product_id,
                'product_name' => $product?->name ?? 'N/A',
                'ean' => $product?->ean ?? '',
                'image_url' => $product?->image_url ?? null,
                'category_id' => $product?->category_id ?? null,
                'category_name' => $product?->category?->name ?? '',
                'market_share' => round($marketShare, 4),
                'growth_rate' => round($growthRate, 2),
                'total_value_current' => $valueCurrent,
                'total_value_previous' => $valuePrevious,
                'quadrant' => null,
                'share_threshold' => 0.0,
            ];
        });

        $medianShare = $this->calculateMedian($withMetrics->pluck('market_share'));

        return $withMetrics->map(function (array $item) use ($medianShare) {
            $highShare = $item['market_share'] >= $medianShare;
            $highGrowth = $item['growth_rate'] > 0;

            $item['quadrant'] = match (true) {
                $highShare && $highGrowth => 'star',
                $highShare && ! $highGrowth => 'cash_cow',
                ! $highShare && $highGrowth => 'question_mark',
                default => 'dog',
            };

            $item['share_threshold'] = round($medianShare, 4);

            return $item;
        })->values();
    }

    /**
     * Calcula o intervalo de datas e o ponto médio para divisão dos períodos
     */
    private function calculateDateRange(array $filters, string $tableType): array
    {
        if ($tableType === 'monthly_summaries') {
            $from = isset($filters['month_from'])
                ? Carbon::parse($filters['month_from'])->startOfMonth()
                : now()->subDays(120)->startOfMonth();

            $to = isset($filters['month_to'])
                ? Carbon::parse($filters['month_to'])->endOfMonth()
                : now()->endOfMonth();
        } else {
            $from = isset($filters['date_from'])
                ? Carbon::parse($filters['date_from'])
                : now()->subDays(120);

            $to = isset($filters['date_to'])
                ? Carbon::parse($filters['date_to'])
                : now();
        }

        $midpoint = $from->copy()->addDays((int) ($from->diffInDays($to) / 2));

        return [
            'from' => $from,
            'to' => $to,
            'midpoint' => $midpoint,
        ];
    }

    /**
     * Busca vendas de ambos os períodos (anterior e atual) numa única query
     *
     * Usa agregação condicional para dividir o período no ponto médio
     */
    private function getSalesBothPeriods(
        array $codigosErp,
        string $tableType,
        array $filters,
        Carbon $midpoint
    ): Collection {
        return match ($tableType) {
            'monthly_summaries' => $this->getMonthlySummariesBothPeriods($codigosErp, $filters, $midpoint),
            default => $this->getSalesBothPeriodsQuery($codigosErp, $filters, $midpoint),
        };
    }

    /**
     * Query para Sales com agregação condicional por período
     */
    private function getSalesBothPeriodsQuery(
        array $codigosErp,
        array $filters,
        Carbon $midpoint
    ): Collection {
        $mid = $midpoint->format('Y-m-d');

        $query = Sale::query()
            ->withoutGlobalScopes()
            ->join('products', 'products.codigo_erp', '=', 'sales.codigo_erp')
            ->select([
                'products.id as product_id',
                DB::raw("SUM(CASE WHEN sales.sale_date > '{$mid}' THEN sales.total_sale_value ELSE 0 END) as value_current"),
                DB::raw("SUM(CASE WHEN sales.sale_date <= '{$mid}' THEN sales.total_sale_value ELSE 0 END) as value_previous"),
            ])
            ->whereIn('sales.codigo_erp', $codigosErp)
            ->groupBy('products.id');

        if (isset($filters['store_id'])) {
            $query->where('sales.store_id', $filters['store_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('sales.sale_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('sales.sale_date', '<=', $filters['date_to']);
        }

        return $query->get();
    }

    /**
     * Query para MonthlySalesSummaries com agregação condicional por período
     */
    private function getMonthlySummariesBothPeriods(
        array $codigosErp,
        array $filters,
        Carbon $midpoint
    ): Collection {
        $mid = $midpoint->format('Y-m-d');

        $query = MonthlySalesSummary::query()
            ->withoutGlobalScopes()
            ->join('products', 'products.codigo_erp', '=', 'monthly_sales_summaries.codigo_erp')
            ->select([
                'products.id as product_id',
                DB::raw("SUM(CASE WHEN monthly_sales_summaries.sale_month > '{$mid}' THEN monthly_sales_summaries.total_sale_value ELSE 0 END) as value_current"),
                DB::raw("SUM(CASE WHEN monthly_sales_summaries.sale_month <= '{$mid}' THEN monthly_sales_summaries.total_sale_value ELSE 0 END) as value_previous"),
            ])
            ->whereIn('monthly_sales_summaries.codigo_erp', $codigosErp)
            ->groupBy('products.id');

        if (isset($filters['store_id'])) {
            $query->where('monthly_sales_summaries.store_id', $filters['store_id']);
        }

        if (isset($filters['month_from'])) {
            $query->where('monthly_sales_summaries.sale_month', '>=', $filters['month_from']);
        }

        if (isset($filters['month_to'])) {
            $query->where('monthly_sales_summaries.sale_month', '<=', $filters['month_to']);
        }

        return $query->get();
    }

    /**
     * Obtém IDs de produtos pela hierarquia de categoria (até 5 níveis)
     */
    private function getProductIdsByCategory(Category $category): array
    {
        $fullHierarchy = $category->getFullHierarchy();
        $limitedHierarchy = $fullHierarchy->take(5);
        $categoryToUse = $limitedHierarchy->last() ?? $category;

        $allCategoryIds = array_unique(array_merge(
            $limitedHierarchy->pluck('id')->toArray(),
            $categoryToUse->getAllDescendantIds()
        ));

        return Product::whereIn('category_id', $allCategoryIds)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Calcula a mediana de uma coleção de valores
     */
    private function calculateMedian(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $count = $sorted->count();

        if ($count === 0) {
            return 0.0;
        }

        if ($count % 2 === 0) {
            return ($sorted[$count / 2 - 1] + $sorted[$count / 2]) / 2;
        }

        return (float) $sorted[(int) ($count / 2)];
    }
}
