<?php

namespace App\Services\AutoPlanogram\Scoring;

use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Implementação de scoring baseada no algoritmo ABC puro (comportamento original).
 *
 * Preserva exatamente a lógica de ProductSelectionService do package:
 * - Busca dados de vendas no tenant
 * - Busca análises ABC pré-calculadas (se configurado)
 * - Calcula score ponderado por estratégia (abc, sales, margin, mix)
 */
final class AbcOnlyScorer implements ProductScorerInterface
{
    use UsesPlannerateTenantDatabase;

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, ScoredProduct>
     */
    public function score(Collection $products, PlacementSettings $settings): Collection
    {
        if ($products->isEmpty()) {
            return collect();
        }

        $salesData = $this->getSalesData($products, $settings);
        $abcAnalyses = $this->getAbcAnalyses($products, $settings);

        $salesTotals = array_column($salesData, 'total');
        $marginTotals = array_column($salesData, 'margin');

        $maxSales = ! empty($salesTotals) ? max($salesTotals) : 1.0;
        $maxMargin = ! empty($marginTotals) ? max($marginTotals) : 1.0;

        $scored = $products->map(function ($product) use ($salesData, $abcAnalyses, $settings, $maxSales, $maxMargin) {
            $productId = $product->id;

            $salesTotal = $salesData[$productId]['total'] ?? 0;
            $margin = $salesData[$productId]['margin'] ?? 0;

            $analysisData = $abcAnalyses[$productId] ?? null;
            $abcClass = $analysisData['abc'] ?? null;
            $targetStock = $analysisData['target_stock'] ?? null;
            $safetyStock = $analysisData['safety_stock'] ?? null;

            $score = $this->calculateScore($salesTotal, $margin, $abcClass, $settings->strategy, $maxSales, $maxMargin);

            return new ScoredProduct(
                productId: $productId,
                ean: (string) ($product->ean ?? $product->codigo_erp ?? ''),
                score: $score,
                product: $product,
                metadata: [
                    'abc_class' => $abcClass,
                    'sales_total' => $salesTotal,
                    'margin' => $margin,
                    'target_stock' => $targetStock,
                    'safety_stock' => $safetyStock,
                ],
            );
        });

        if (! $settings->includeProductsWithoutSales) {
            $scored = $scored->filter(fn (ScoredProduct $p) => ($p->metadata['sales_total'] ?? 0) > 0);
        }

        return $scored->sortByDesc('score')->values();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<string, array{total: float, margin: float}>
     */
    private function getSalesData(Collection $products, PlacementSettings $settings): array
    {
        $productIds = $products->pluck('id')->toArray();

        $startDate = $settings->startDate
            ? Carbon::parse($settings->startDate)
            : now()->subYear()->startOfMonth();

        $endDate = $settings->endDate
            ? Carbon::parse($settings->endDate)
            : now()->subYear()->endOfMonth();

        $connection = $this->plannerateTenantDatabase();
        $tableType = $settings->tableType;

        Log::debug('AbcOnlyScorer: buscando dados de vendas', [
            'table_type' => $tableType,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'products_count' => count($productIds),
        ]);

        if ($tableType === 'monthly_summaries') {
            $monthFrom = $startDate->copy()->startOfMonth()->toDateString();
            $monthTo = $endDate->copy()->endOfMonth()->toDateString();

            $rows = $connection->table('monthly_sales_summaries')
                ->join('products', 'products.codigo_erp', '=', 'monthly_sales_summaries.codigo_erp')
                ->whereIn('products.id', $productIds)
                ->whereBetween('monthly_sales_summaries.sale_month', [$monthFrom, $monthTo])
                ->select([
                    'products.id as product_id',
                    DB::raw('SUM(monthly_sales_summaries.total_sale_value) as total_sales'),
                    DB::raw('SUM(monthly_sales_summaries.margem_contribuicao) as total_margin'),
                ])
                ->groupBy('products.id')
                ->get();
        } else {
            $rows = $connection->table('sales')
                ->join('products', 'products.codigo_erp', '=', 'sales.codigo_erp')
                ->whereIn('products.id', $productIds)
                ->whereBetween('sales.sale_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->select([
                    'products.id as product_id',
                    DB::raw('SUM(sales.total_sale_value) as total_sales'),
                    DB::raw('SUM(sales.margem_contribuicao) as total_margin'),
                ])
                ->groupBy('products.id')
                ->get();
        }

        $salesData = [];
        foreach ($rows as $row) {
            $salesData[$row->product_id] = [
                'total' => (float) $row->total_sales,
                'margin' => (float) $row->total_margin,
            ];
        }

        return $salesData;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<string, array{abc: string|null, target_stock: float, safety_stock: float}>
     */
    private function getAbcAnalyses(Collection $products, PlacementSettings $settings): array
    {
        if (! $settings->useExistingAnalysis) {
            return [];
        }

        $productIds = $products->pluck('id')->toArray();

        $analyses = $this->plannerateTenantTable('product_analyses')
            ->whereIn('product_id', $productIds)
            ->select(['product_id', 'abc_classification', 'target_stock', 'safety_stock'])
            ->get();

        $abcData = [];
        foreach ($analyses as $analysis) {
            $abcData[$analysis->product_id] = [
                'abc' => $analysis->abc_classification,
                'target_stock' => (float) ($analysis->target_stock ?? 0),
                'safety_stock' => (float) ($analysis->safety_stock ?? 0),
            ];
        }

        return $abcData;
    }

    private function calculateScore(
        float $salesTotal,
        float $margin,
        ?string $abcClass,
        string $strategy,
        float $maxSales = 1.0,
        float $maxMargin = 1.0
    ): float {
        $abcScore = match ($abcClass) {
            'A' => 100,
            'B' => 50,
            'C' => 25,
            default => 0,
        };

        $salesScore = $maxSales > 0 ? ($salesTotal / $maxSales) * 100 : 0;
        $marginScore = $maxMargin > 0 ? ($margin / $maxMargin) * 100 : 0;

        return match ($strategy) {
            'abc' => $abcScore,
            'sales' => $salesScore,
            'margin' => $marginScore,
            'mix' => ($abcScore * 0.4) + ($salesScore * 0.4) + ($marginScore * 0.2),
            default => $salesScore,
        };
    }
}
