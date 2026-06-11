<?php

namespace App\Services\AutoPlanogram\Placement;

use App\Services\AutoPlanogram\ProductOrderingService;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Callcocam\LaravelRaptorPlannerate\Models\Segment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Redistribui segmentos já posicionados ao mudar tipo de exposição (brand/flavor).
 * Invariante: mesmo conjunto {produto: frentes} antes e depois — não recomputa scoring nem rejeitados.
 */
final class ExposureRedistributeService
{
    public function __construct(
        private readonly ProductOrderingService $orderingService,
    ) {}

    /**
     * Reagrupa segmentos da prateleira do slot conforme a nova exposição (brand/flavor).
     * Recalcula ordering e position sem alterar produtos ou frentes.
     *
     * @return int Número de segmentos redistribuídos.
     */
    public function redistribute(Gondola $gondola, PlanogramTemplateSlot $slot): int
    {
        $shelf = $this->resolveShelf($gondola, $slot);

        if ($shelf === null) {
            Log::warning('ExposureRedistributeService: prateleira não encontrada', [
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

        // Pares (segmento, produto) — ignora segmentos sem produto
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

        // Reagrupa por exposição (brand_exposure=vertical → agrupa por marca)
        $groupedProducts = $this->orderingService->applyExposureGrouping($products, $slot);

        $productIndex = [];
        foreach ($items as $item) {
            $productIndex[$item['product']->id] = $item['segment'];
        }

        $orderedSegments = $groupedProducts
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

        Log::info('ExposureRedistributeService: segmentos redistribuídos', [
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
