<?php

namespace App\Services\AutoPlanogram\Placement;

use App\Services\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Illuminate\Support\Collection;

interface PlanogramWriterInterface
{
    /**
     * @param  Collection<int, Section>  $sections
     * @param  Collection<int, PlacedSegment>  $placedSegments
     */
    public function write(string $gondolaId, Collection $sections, Collection $placedSegments): void;
}
