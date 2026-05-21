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
        /** @var list<array<string, mixed>> Per-slot space analysis (template mode only) */
        public array $slotAnalysis = [],
        public bool $modulesMismatch = false,
        public int $templateModules = 0,
        public int $gondolaModules = 0,
        public ?string $subtemplateId = null,
    ) {}
}
