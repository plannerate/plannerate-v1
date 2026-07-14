<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedLayer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Segment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Persiste PlacedSegments no banco de dados do tenant.
 *
 * Responsabilidades:
 * 1. Soft-delete de segments/layers existentes da gôndola
 * 2. Criação de Segments com ordering, position e distributed_width
 * 3. Criação de Layers com product_id, ean, quantity (facing) e height
 */
final class PlanogramWriter implements PlanogramWriterInterface
{
    use UsesPlannerateTenantDatabase;

    /**
     * Limpa segments/layers existentes da gôndola e persiste os novos.
     *
     * @param  Collection<int, Section>  $sections
     * @param  Collection<int, PlacedSegment>  $placedSegments
     */
    public function write(string $gondolaId, Collection $sections, Collection $placedSegments): void
    {
        $shelfIds = $sections
            ->flatMap(fn ($section) => $section->shelves->pluck('id'))
            ->filter()
            ->values()
            ->toArray();

        if (empty($shelfIds)) {
            Log::warning('PlanogramWriter: nenhuma shelf encontrada para a gôndola', ['gondola_id' => $gondolaId]);

            return;
        }

        // Cascata explícita: soft-deletar só os Segments deixaria as Layers filhas com
        // deleted_at NULL apontando para segments deletados — lixo que se acumula a cada
        // geração. Mesma ordem de DeletesGondolaGraph (layers → segments).
        $segmentIds = Segment::whereIn('shelf_id', $shelfIds)->pluck('id');

        $deletedLayers = Layer::whereIn('segment_id', $segmentIds)->delete();
        $deletedSegments = Segment::whereIn('shelf_id', $shelfIds)->delete();

        Log::info('PlanogramWriter: segments removidos', [
            'gondola_id' => $gondolaId,
            'segments_deleted' => $deletedSegments,
            'layers_deleted' => $deletedLayers,
        ]);

        $totalCreated = 0;

        foreach ($placedSegments as $placed) {
            $segment = new Segment;
            $segment->id = (string) Str::ulid();
            $segment->shelf_id = $placed->shelfId;
            $segment->quantity = $placed->layers->sum('height');
            $segment->ordering = $placed->ordering;
            $segment->position = $placed->position;
            $segment->width = $placed->width;
            $segment->distributed_width = $placed->distributedWidth;
            $segment->save();

            foreach ($placed->layers as $layer) {
                $this->createLayer($segment->id, $layer);
                $totalCreated++;
            }
        }

        Log::info('PlanogramWriter: segmentos persistidos', [
            'gondola_id' => $gondolaId,
            'segments_created' => $placedSegments->count(),
            'layers_created' => $totalCreated,
        ]);
    }

    private function createLayer(string $segmentId, PlacedLayer $layer): void
    {
        $record = new Layer;
        $record->id = (string) Str::ulid();
        $record->segment_id = $segmentId;
        $record->product_id = $layer->productId;
        $record->ean = $layer->ean;
        $record->quantity = $layer->quantity;
        $record->height = $layer->height;
        $record->save();
    }
}
