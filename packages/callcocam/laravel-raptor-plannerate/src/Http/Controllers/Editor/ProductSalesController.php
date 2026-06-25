<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSalesController extends Controller
{
    /**
     * Retorna resumo agregado de vendas do produto.
     *
     * Quando o planograma informa um período (start_date/end_date via query),
     * as vendas são filtradas para considerar apenas o intervalo do planograma.
     */
    public function summary(Request $request, string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        /**
         * Casa as vendas do produto pela mesma regra da listagem (ProductController@sales):
         * product_id OU ean OU codigo_erp. Muitas vendas chegam da integração sem product_id
         * preenchido, vinculadas apenas pelo codigo_erp/ean — por isso o filtro só por
         * product_id subcontava o resumo.
         */
        $matchProduct = function (Builder $query) use ($product): Builder {
            return $query->where(function (Builder $q) use ($product): void {
                $q->where('product_id', $product->id);

                if (! empty($product->ean)) {
                    $q->orWhere('ean', $product->ean);
                }

                if (! empty($product->codigo_erp)) {
                    $q->orWhere('codigo_erp', $product->codigo_erp);
                }
            });
        };

        /**
         * Aplica o filtro de período do planograma (quando informado) à query de vendas.
         */
        $applyPeriod = function (Builder $query) use ($startDate, $endDate): Builder {
            if ($startDate) {
                $query->whereDate('sale_date', '>=', $startDate);
            }

            if ($endDate) {
                $query->whereDate('sale_date', '<=', $endDate);
            }

            return $query;
        };

        /**
         * Query base de vendas do produto já com período aplicado.
         */
        $baseQuery = fn (): Builder => $applyPeriod($matchProduct(Sale::query()));

        // Busca vendas agregadas do produto
        $salesSummary = $baseQuery()
            ->selectRaw('
                COUNT(*) as total_sales,
                SUM(total_sale_quantity) as total_quantity,
                SUM(total_sale_value) as total_revenue,
                SUM(total_sale_value) / NULLIF(SUM(total_sale_quantity), 0) as avg_price,
                SUM(acquisition_cost) / NULLIF(SUM(total_sale_quantity), 0) as avg_cost,
                SUM(margem_contribuicao) / NULLIF(SUM(total_sale_quantity), 0) as avg_margin,
                MIN(sale_date) as first_sale_date,
                MAX(sale_date) as last_sale_date
            ')
            ->first();

        // Vendas por mês (últimos 12 meses)
        $driver = Sale::getModel()->getConnection()->getDriverName();
        $monthExpr = $driver === 'pgsql'
            ? "TO_CHAR(sale_date, 'YYYY-MM')"
            : "DATE_FORMAT(sale_date, '%Y-%m')";

        // Com período do planograma: respeita o intervalo; sem período: últimos 12 meses.
        $salesByMonthQuery = $matchProduct(Sale::query());

        if ($startDate || $endDate) {
            $applyPeriod($salesByMonthQuery);
        } else {
            $salesByMonthQuery->where('sale_date', '>=', now()->subMonths(12));
        }

        $salesByMonth = $salesByMonthQuery
            ->selectRaw("{$monthExpr} as month, COUNT(*) as sales_count, SUM(total_sale_quantity) as quantity, SUM(total_sale_value) as revenue")
            ->groupByRaw($monthExpr)
            ->orderByRaw($monthExpr)
            ->get();

        // Top 5 lojas com mais vendas
        $topStores = $baseQuery()
            // Note: ->with('store:id,name') removed - use custom getter instead
            ->selectRaw('
                store_id,
                COUNT(*) as sales_count,
                SUM(total_sale_quantity) as quantity,
                SUM(total_sale_value) as revenue
            ')
            ->groupBy('store_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(function ($sale) {
                return [
                    'store_id' => $sale->store_id,
                    'store_name' => $sale->store?->name ?? 'Loja não encontrada',
                    'sales_count' => $sale->sales_count,
                    'quantity' => $sale->quantity,
                    'revenue' => $sale->revenue,
                ];
            });

        return response()->json([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_ean' => $product->ean,
            'summary' => [
                'total_sales' => (int) ($salesSummary->total_sales ?? 0),
                'total_quantity' => (int) ($salesSummary->total_quantity ?? 0),
                'total_revenue' => (float) ($salesSummary->total_revenue ?? 0),
                'avg_price' => (float) ($salesSummary->avg_price ?? 0),
                'avg_cost' => (float) ($salesSummary->avg_cost ?? 0),
                'avg_margin' => (float) ($salesSummary->avg_margin ?? 0),
                'first_sale_date' => $salesSummary->first_sale_date ?? null,
                'last_sale_date' => $salesSummary->last_sale_date ?? null,
            ],
            'by_month' => $salesByMonth->map(function ($item) {
                return [
                    'month' => $item->month,
                    'sales_count' => (int) $item->sales_count,
                    'quantity' => (int) $item->quantity,
                    'revenue' => (float) $item->revenue,
                ];
            }),
            'top_stores' => $topStores,
        ]);
    }
}
