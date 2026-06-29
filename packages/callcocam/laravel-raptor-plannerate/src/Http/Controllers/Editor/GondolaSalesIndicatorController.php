<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Sales\SalesFilters;
use Callcocam\LaravelRaptorPlannerate\Sales\SalesSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Indicadores de vendas (preço/margem médios) de TODOS os produtos de uma
 * gôndola, em lote — alimenta os selos exibidos na frente de cada produto no
 * editor sem precisar de uma requisição por produto.
 */
class GondolaSalesIndicatorController extends Controller
{
    public function __construct(private readonly SalesSummaryService $salesSummary) {}

    /**
     * Retorna, para cada produto da gôndola, os indicadores derivados de vendas
     * keyed por EAN. Quando o planograma informa um período (start_date/end_date
     * via query), as vendas são restritas a esse intervalo — mesma semântica do
     * resumo por produto.
     */
    public function index(Request $request, string $gondola): JsonResponse
    {
        $gondolaModel = Gondola::find($gondola);

        if (! $gondolaModel) {
            return response()->json(['message' => 'Gôndola não encontrada.'], 404);
        }

        $filters = SalesFilters::fromPlanogramRequest($request);

        $results = $this->salesSummary->indicatorsForGondola($gondolaModel->id, $filters);

        return response()->json([
            'gondola_id' => $gondolaModel->id,
            'results' => $results,
        ]);
    }
}
