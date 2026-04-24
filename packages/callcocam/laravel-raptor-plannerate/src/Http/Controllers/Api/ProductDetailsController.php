<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Http\Request;

class ProductDetailsController extends Controller
{
    /**
     * Retorna detalhes completos de um produto incluindo:
     * - Informações básicas do produto
     * - Posição na gôndola (módulo, prateleira, frentes, capacidade)
     * - Dados de vendas (se disponíveis)
     */
    public function show(Request $request, string $ean)
    {
        $gondolaId = $request->input('gondola_id');

        // Busca informações do produto
        $product = Product::where('ean', $ean)->first();

        $response = [
            'product' => $product ? [
                'ean' => $product->ean,
                'name' => $product->name,
                'code' => $product->code,
                'image_url' => $product->image_url,
                // Dimensões agora estão diretamente no produto (tabela dimensions foi removida)
                'width' => $product->width ?? null,
                'height' => $product->height ?? null,
                'depth' => $product->depth ?? null,
                'weight' => $product->weight ?? null,
                'unit' => $product->unit ?? 'cm',
            ] : [
                'ean' => $ean,
                'name' => null,
                'code' => null,
                'image_url' => null,
                'width' => null,
                'height' => null,
                'depth' => null,
                'weight' => null,
                'unit' => null,
            ],
            'position' => null,
            'sales_data' => null,
        ];

        // Se tiver gondolaId, busca posição do produto na gôndola
        if ($gondolaId && $product) {
            $position = $this->getProductPosition($gondolaId, $ean);
            $response['position'] = $position;
        }

        // Busca dados de vendas do produto (se existirem)
        if ($product && $gondolaId) {
            $salesData = $this->getProductSalesData($gondolaId, $ean);
            $response['sales_data'] = $salesData;
        }

        return response()->json($response);
    }

    /**
     * Retorna a posição do produto na gôndola
     */
    private function getProductPosition(string $gondolaId, string $ean)
    {
        $gondola = Gondola::with([
            'sections.shelves.segments.layer.product',
        ])->find($gondolaId);

        if (! $gondola) {
            return null;
        }

        foreach ($gondola->sections as $section) {
            foreach ($section->shelves as $shelf) {
                foreach ($shelf->segments as $segment) {
                    if ($segment->layer && $segment->layer->product && $segment->layer->product->ean === $ean) {
                        // Calcula capacidade total (frentes x altura x profundidade)
                        $segmentQuantity = $segment->quantity ?? 1;
                        $layerQuantity = $segment->layer->quantity ?? 1;
                        $productDepth = $segment->layer->product->depth ?? 10;
                        $shelfDepth = $shelf->shelf_depth ?? 40;
                        $itemsInDepth = floor($shelfDepth / $productDepth);
                        $totalCapacity = $segmentQuantity * $layerQuantity * $itemsInDepth;

                        // Calcula número da prateleira (de baixo para cima)
                        $sortedShelves = $section->shelves->sortByDesc('shelf_position');
                        $shelfNumber = $sortedShelves->search(fn ($s) => $s->id === $shelf->id) + 1;

                        return [
                            'section_ordering' => $section->ordering,
                            'section_name' => $section->name,
                            'shelf_number' => $shelfNumber,
                            'shelf_position' => $shelf->shelf_position,
                            'facings' => $segmentQuantity,
                            'height' => $layerQuantity,
                            'depth_items' => $itemsInDepth,
                            'total_capacity' => $totalCapacity,
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Retorna dados de vendas do produto
     * TODO: Implementar integração com sistema de vendas real
     */
    private function getProductSalesData(string $gondolaId, string $ean)
    {
        // Por enquanto retorna null
        // Futuramente conectar com tabela de vendas/sales
        return null;
    }
}
