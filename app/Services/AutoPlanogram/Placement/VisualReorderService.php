<?php

namespace App\Services\AutoPlanogram\Placement;

use App\Models\PlanogramTemplateSlot;
use App\Services\AutoPlanogram\ProductOrderingService;
use App\Services\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Segment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reordena segmentos já posicionados numa prateleira replicando o motor de critérios visuais.
 * Invariante: mesmos produtos e mesmas frentes antes e depois — só ordering e position mudam.
 */
final class VisualReorderService
{
    public function __construct(
        private readonly ProductOrderingService $orderingService,
        private readonly ProductWidthResolver $widthResolver,
    ) {}

    /**
     * Reaplica a ordenação visual do slot sobre os segmentos existentes na prateleira correspondente.
     *
     * @param  array<string, string>  $abcClassMap  [product_id => 'A'|'B'|'C']
     * @param  array<string, array{giro: float, margem: float}>  $zoneMetricsMap
     * @return int Número de segmentos reordenados.
     */
    public function reorder(
        Gondola $gondola,
        PlanogramTemplateSlot $slot,
        array $abcClassMap = [],
        array $zoneMetricsMap = [],
    ): int {
        $shelf = $this->resolveShelf($gondola, $slot);

        if ($shelf === null) {
            Log::warning('VisualReorderService: prateleira não encontrada', [
                'gondola_id' => $gondola->getKey(),
                'slot_id' => $slot->getKey(),
                'module_number' => $slot->module_number,
                'shelf_order' => $slot->shelf_order,
            ]);

            return 0;
        }

        $segments = Segment::where('shelf_id', $shelf->getKey())
            ->with(['layer.product'])
            ->orderBy('ordering')
            ->get();

        if ($segments->isEmpty()) {
            return 0;
        }

        // Pares (segmento, produto) — ignora segmentos sem layer ou sem produto
        $items = $segments
            ->map(fn ($seg) => [
                'segment' => $seg,
                'product' => $seg->layer?->product,
            ])
            ->filter(fn ($item) => $item['product'] !== null)
            ->values();

        if ($items->isEmpty()) {
            return 0;
        }

        $products = $items->map(fn ($item) => $item['product']);

        // Reaplica critérios visuais do slot
        $sortedProducts = $this->orderingService->orderBySlot($products, $slot, $abcClassMap, $zoneMetricsMap);

        // Mapeia produtos ordenados → segmentos
        $productIndex = [];
        foreach ($items as $item) {
            $productIndex[$item['product']->id] = $item['segment'];
        }

        $orderedSegments = $sortedProducts
            ->map(fn ($p) => $productIndex[$p->id] ?? null)
            ->filter()
            ->values();

        DB::transaction(function () use ($orderedSegments): void {
            $position = 0;

            foreach ($orderedSegments as $ordering => $segment) {
                $width = (int) ($segment->width ?? 0);
                $segment->ordering = $ordering;
                $segment->position = $position;
                $segment->save();
                $position += $width;
            }
        });

        Log::info('VisualReorderService: segmentos reordenados', [
            'gondola_id' => $gondola->getKey(),
            'slot_id' => $slot->getKey(),
            'shelf_id' => $shelf->getKey(),
            'count' => $orderedSegments->count(),
        ]);

        return $orderedSegments->count();
    }

    /**
     * Resolve a prateleira física do gondola correspondente à posição lógica do slot.
     * shelf_order 1 = chão; índice físico = num_shelves - shelf_order.
     */
    private function resolveShelf(Gondola $gondola, PlanogramTemplateSlot $slot): mixed
    {
        $gondola->loadMissing(['sections.shelves']);

        $sections = $gondola->sections->sortBy('ordering')->values();
        $section = $sections->get($slot->module_number - 1);

        if ($section === null) {
            return null;
        }

        $shelves = $section->shelves->sortBy('shelf_position')->values();
        $numShelves = $shelves->count();
        $index = $numShelves - $slot->shelf_order;

        return $shelves[$index] ?? null;
    }
}
