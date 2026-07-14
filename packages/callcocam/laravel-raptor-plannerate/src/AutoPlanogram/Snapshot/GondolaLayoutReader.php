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
     * @return Collection<int, PlacedSegment>
     */
    public function read(Gondola $gondola): Collection
    {
        $gondola->loadMissing('sections.shelves.segments.layers');

        return $gondola->sections
            ->sortBy('ordering')
            ->flatMap(fn ($section) => $section->shelves
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
