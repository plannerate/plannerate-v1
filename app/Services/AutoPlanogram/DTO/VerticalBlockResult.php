<?php

namespace App\Services\AutoPlanogram\DTO;

use Illuminate\Support\Collection;

final readonly class VerticalBlockResult
{
    public function __construct(
        /** @var Collection<int, PlacedSegment> */
        public Collection $verticalSegments,
        /** @var Collection<int, OrderedBlock> */
        public Collection $remainingBlocks,
        /** @var array<string, float> shelf_id => reserved width in cm */
        public array $reservedWidthPerShelf,
    ) {}
}
