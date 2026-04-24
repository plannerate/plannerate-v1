<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Sale;
use Illuminate\Http\JsonResponse;

class ProductSalesController extends Controller
{
    /**
     * Retorna resumo agregado de vendas do produto
     */
    public function summary(string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        // Busca vendas agregadas do produto
        $salesSummary = Sale::where('product_id', $product->id)
            ->selectRaw('
                COUNT(*) as total_sales,
                SUM(total_sale_quantity) as total_quantity,
                SUM(total_sale_value) as total_revenue,
                AVG(sale_price) as avg_price,
                AVG(acquisition_cost) as avg_cost,
                AVG(margem_contribuicao) as avg_margin,
                MIN(sale_date) as first_sale_date,
                MAX(sale_date) as last_sale_date
            ')
            ->first();

        // Vendas por mês (últimos 12 meses)
        $salesByMonth = Sale::where('product_id', $product->id)
            ->where('sale_date', '>=', now()->subMonths(12))
            ->selectRaw("
                TO_CHAR(sale_date, 'YYYY-MM') as month,
                COUNT(*) as sales_count,
                SUM(total_sale_quantity) as quantity,
                SUM(total_sale_value) as revenue
            ")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Top 5 lojas com mais vendas
        $topStores = Sale::where('product_id', $product->id)
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
