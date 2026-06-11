<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Illuminate\Support\Collection;

interface PlanogramWriterInterface
{
    /**
     * @param  Collection<int, Section>  $sections
     * @param  Collection<int, PlacedSegment>  $placedSegments
     */
    public function write(string $gondolaId, Collection $sections, Collection $placedSegments): void;
}
