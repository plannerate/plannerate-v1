<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate\SectionGenerate;

use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\SectionGenerate\SectionAllocationResultDTO;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Segment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Limpa e persiste a alocação de uma section (Segment + Layer).
 *
 * V2: Agora calcula position (position_x) respeitando flow da góndola.
 */
class SectionPersistenceService
{
    /**
     * Remove todos os segments (e layers em cascade) das prateleiras desta section.
     */
    public function clearSection(Section $section): void
    {
        $section->loadMissing('shelves');
        $shelfIds = $section->shelves->pluck('id')->all();

        if ($shelfIds === []) {
            return;
        }

        $deleted = Segment::whereIn('shelf_id', $shelfIds)->delete();

        Log::debug('Section limpa', [
            'section_id' => $section->id,
            'segments_deleted' => $deleted,
        ]);
    }

    /**
     * Grava a alocação no banco: um Segment + Layer por item (shelf_id, product_id, facings).
     *
     * V2: Calcula position (position_x) acumulando largura dos produtos.
     * Se flow da góndola for right_to_left, inverte ordering no final.
     */
    public function saveAllocation(Section $section, SectionAllocationResultDTO $result): int
    {
        if ($result->allocation === []) {
            return 0;
        }

        // Carregar flow da góndola
        $section->loadMissing('gondola');
        $gondolaFlow = $section->gondola?->flow ?? 'left_to_right';

        $created = 0;
        $orderingByShelf = [];
        $positionByShelf = [];
        $segmentsByShelf = [];

        DB::transaction(function () use ($result, &$created, &$orderingByShelf, &$positionByShelf, &$segmentsByShelf) {
            foreach ($result->allocation as $item) {
                $shelfId = $item->shelfId;
                $order = $orderingByShelf[$shelfId] ?? 0;
                $currentX = $positionByShelf[$shelfId] ?? 0;

                $orderingByShelf[$shelfId] = $order + 1;

                // Largura total do produto (largura unitária * facings)
                $productWidth = $item->productWidth * $item->facings;

                $segment = Segment::create([
                    'id' => (string) Str::ulid(),
                    'shelf_id' => $shelfId,
                    'quantity' => 1,
                    'ordering' => $order,
                    'position' => (int) $currentX,  // position_x em cm
                ]);

                // Guardar segment para eventual inversão de ordering
                if (! isset($segmentsByShelf[$shelfId])) {
                    $segmentsByShelf[$shelfId] = [];
                }
                $segmentsByShelf[$shelfId][] = $segment;

                Layer::create([
                    'id' => (string) Str::ulid(),
                    'segment_id' => $segment->id,
                    'product_id' => $item->productId,
                    'quantity' => $item->facings,
                ]);

                // Atualizar position_x para próximo produto (acumula largura)
                $positionByShelf[$shelfId] = $currentX + $productWidth;

                $created++;
            }
        });

        // Se flow for right_to_left, inverter ordering de cada shelf
        if ($gondolaFlow === 'right_to_left') {
            $this->reverseShelfOrdering($segmentsByShelf);
        }

        Log::info('Section alocação persistida', [
            'section_id' => $section->id,
            'segments_created' => $created,
            'gondola_flow' => $gondolaFlow,
            'flow_reversed' => $gondolaFlow === 'right_to_left',
        ]);

        return $created;
    }

    /**
     * Inverte ordering dos segments de cada shelf (para flow right_to_left).
     *
     * @param  array<string, Segment[]>  $segmentsByShelf
     */
    protected function reverseShelfOrdering(array $segmentsByShelf): void
    {
        foreach ($segmentsByShelf as $shelfId => $segments) {
            $maxOrder = count($segments) - 1;

            foreach ($segments as $index => $segment) {
                $segment->update(['ordering' => $maxOrder - $index]);
            }
        }

        Log::debug('Ordering invertido para flow right_to_left', [
            'shelves_count' => count($segmentsByShelf),
            'total_segments_reversed' => array_sum(array_map('count', $segmentsByShelf)),
        ]);
    }
}
