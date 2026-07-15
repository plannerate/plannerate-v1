<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor;

use Callcocam\LaravelRaptorPlannerate\Concerns\ResolvesGondolaStoreId;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Sales\SalesFilters;
use Callcocam\LaravelRaptorPlannerate\Sales\SalesSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSalesController extends Controller
{
    use ResolvesGondolaStoreId;

    public function __construct(private readonly SalesSummaryService $salesSummary) {}

    /**
     * Retorna resumo agregado de vendas do produto.
     *
     * Quando o planograma informa um período (start_date/end_date via query),
     * as vendas são filtradas para considerar apenas o intervalo do planograma.
     * Quando a query informa gondola_id, o resumo e a evolução mensal são
     * restritos à loja do planograma dessa gôndola — top_stores continua sem
     * filtro de loja de propósito, para servir de comparação com as demais lojas.
     *
     * Toda a agregação e as métricas derivadas vêm do SalesSummaryService — fonte
     * única de verdade. Este controller apenas monta o contrato JSON do editor.
     */
    public function summary(Request $request, string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $storeId = null;

        if ($request->filled('gondola_id')) {
            $gondolaModel = Gondola::find($request->query('gondola_id'));

            if ($gondolaModel) {
                $storeId = $this->resolveGondolaStoreId($gondolaModel);
            }
        }

        $filters = SalesFilters::fromPlanogramRequest($request, $storeId);
        $summary = $this->salesSummary->summaryForProduct($product, $filters);

        return response()->json([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_ean' => $product->ean,
            'summary' => [
                'total_sales' => $summary->totalRecords,
                'total_quantity' => $summary->totalQuantity,
                'total_revenue' => $summary->totalValue,
                'total_cost' => $summary->totalAcquisitionCost,
                'total_margin' => $summary->totalMargemContribuicao,
                'avg_price' => $summary->avgPrice(),
                'avg_cost' => $summary->avgCost(),
                'avg_margin' => $summary->avgMargin(),
                'margin_percentage' => $summary->netMarginPct(),
                'gross_profit_unit' => $summary->grossProfitUnit(),
                'gross_profit_total' => $summary->grossProfitTotal(),
                'gross_margin_pct' => $summary->grossMarginPct(),
                'first_sale_date' => $summary->firstSaleDate,
                'last_sale_date' => $summary->lastSaleDate,
            ],
            'by_month' => $this->salesSummary->salesByMonth($product, $filters),
            'top_stores' => $this->salesSummary->topStores($product, SalesFilters::fromPlanogramRequest($request)),
        ]);
    }
}
