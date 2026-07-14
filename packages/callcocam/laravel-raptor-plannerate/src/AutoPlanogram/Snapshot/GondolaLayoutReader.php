<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Snapshot;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedLayer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Illuminate\Support\Collection;

/**
 * Lê o layout ATUAL de uma gôndola do banco no mesmo DTO que o motor de placement produz.
 *
 * Ter os dois lados (o que está na gôndola e o que a geração propõe) no mesmo formato é o
 * que permite compará-los: é a base do diff da reotimização e do hash de staleness.
 */
final class GondolaLayoutReader
{
    /**
     * @param  bool  $excludeLockedShelves  Ignora prateleiras travadas.
     *
     * A reotimização usa `true`: prateleiras travadas não participam da geração, então não fazem
     * parte da proposta. Incluí-las no baseline faria o diff anunciar que os produtos travados
     * "saem da gôndola" — exatamente o contrário do que o lock garante.
     *
     * Como o hash de staleness também é calculado sobre este recorte, travar ou destravar uma
     * prateleira depois da análise muda o hash e invalida a proposta. É o comportamento certo:
     * destravar expõe uma prateleira que a proposta nunca considerou, e travar faria a aprovação
     * duplicar produtos.
     * @return Collection<int, PlacedSegment>
     */
    public function read(Gondola $gondola, bool $excludeLockedShelves = false): Collection
    {
        $gondola->loadMissing('sections.shelves.segments.layers');

        return $gondola->sections
            ->sortBy('ordering')
            ->flatMap(fn ($section) => $section->shelves
                ->when($excludeLockedShelves, fn ($shelves) => $shelves->reject(fn ($shelf) => (bool) $shelf->is_locked))
                ->sortBy('ordering')
                ->flatMap(fn ($shelf) => $shelf->segments
                    ->sortBy('ordering')
                    ->map(fn ($segment): PlacedSegment => new PlacedSegment(
                        sectionId: (string) $section->id,
                        shelfId: (string) $shelf->id,
                        ordering: (int) ($segment->ordering ?? 0),
                        position: (int) ($segment->position ?? 0),
                        width: (int) ($segment->width ?? 0),
                        distributedWidth: (int) ($segment->distributed_width ?? 0),
                        layers: $segment->layers
                            ->map(fn ($layer): PlacedLayer => new PlacedLayer(
                                productId: (string) $layer->product_id,
                                ean: (string) ($layer->ean ?? ''),
                                quantity: (int) ($layer->quantity ?? 1),
                                height: (int) ($layer->height ?? 1),
                            ))
                            ->values(),
                    ))
                )
            )
            ->values();
    }
}
