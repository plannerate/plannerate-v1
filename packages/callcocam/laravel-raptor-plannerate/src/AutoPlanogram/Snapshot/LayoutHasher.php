<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Snapshot;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedLayer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Illuminate\Support\Collection;

/**
 * Assinatura estável do conteúdo de um layout.
 *
 * Detector de staleness da reotimização: a proposta guarda o hash do layout que existia quando
 * o diff foi calculado. Se, na hora de aprovar, o layout da gôndola tiver outro hash (alguém
 * editou à mão, ou rodou uma geração no meio-tempo), o diff que o usuário revisou não descreve
 * mais a realidade — e a proposta é recusada em vez de sobrescrever trabalho alheio.
 *
 * Normaliza antes de hashear: só o CONTEÚDO importa (onde cada produto está, com quantas
 * frentes), não a ordem em que os segmentos aparecem na coleção nem os IDs de linha.
 */
final class LayoutHasher
{
    /**
     * @param  Collection<int, PlacedSegment>  $segments
     */
    public function hash(Collection $segments): string
    {
        $normalized = $segments
            ->map(fn (PlacedSegment $segment): array => [
                'shelf_id' => $segment->shelfId,
                'ordering' => $segment->ordering,
                'position' => $segment->position,
                'width' => $segment->width,
                'layers' => $segment->layers
                    ->map(fn (PlacedLayer $layer): array => [
                        'product_id' => $layer->productId,
                        'quantity' => $layer->quantity,
                        'height' => $layer->height,
                    ])
                    ->sortBy('product_id')
                    ->values()
                    ->all(),
            ])
            ->sortBy(fn (array $segment): string => $segment['shelf_id'].':'.str_pad((string) $segment['ordering'], 6, '0', STR_PAD_LEFT))
            ->values()
            ->all();

        return hash('sha256', (string) json_encode($normalized));
    }
}
