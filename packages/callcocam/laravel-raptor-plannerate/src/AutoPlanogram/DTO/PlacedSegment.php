<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO;

use Callcocam\LaravelRaptorPlannerate\Enums\ShelfLevel;
use Illuminate\Support\Collection;

/**
 * Segmento posicionado em uma prateleira, pronto para persistência.
 *
 * @phpstan-type SegmentArray array{section_id: string, shelf_id: string, ordering: int, position: int, width: int, distributed_width: int, layers_count: int, shelf_level: ?string}
 */
final readonly class PlacedSegment
{
    public function __construct(
        public string $sectionId,
        public string $shelfId,
        /** Posição horizontal no shelf (índice 0-based) */
        public int $ordering,
        /** Posição X em cm (acumulativo) */
        public int $position,
        /** Largura em cm */
        public int $width,
        /** Largura distribuída em cm (pode diferir de width após ajustes) */
        public int $distributedWidth,
        /** @var Collection<int, PlacedLayer> */
        public Collection $layers,
        public ?ShelfLevel $shelfLevel = null,
    ) {}

    /**
     * @return SegmentArray
     */
    public function toArray(): array
    {
        return [
            'section_id' => $this->sectionId,
            'shelf_id' => $this->shelfId,
            'ordering' => $this->ordering,
            'position' => $this->position,
            'width' => $this->width,
            'distributed_width' => $this->distributedWidth,
            'layers_count' => $this->layers->count(),
            'shelf_level' => $this->shelfLevel?->value,
        ];
    }
}
