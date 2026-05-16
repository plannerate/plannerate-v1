<?php

namespace App\Services\AutoPlanogram\DTO;

use App\Enums\PlacementFailureReason;
use Illuminate\Support\Collection;

final readonly class PlacementResult
{
    public function __construct(
        /** @var Collection<int, PlacedSegment> */
        public Collection $placedSegments,
        /** @var Collection<int, array{product: mixed, reason: PlacementFailureReason}> */
        public Collection $rejectedProducts,
    ) {}
}
