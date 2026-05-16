<?php

namespace App\Services\AutoPlanogram\Placement;

use App\Services\AutoPlanogram\DTO\OrderedBlock;
use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Illuminate\Support\Collection;

interface PlacementEngineInterface
{
    /**
     * Distribui os blocos ordenados pelas prateleiras e retorna segmentos posicionados.
     *
     * @param  Collection<int, OrderedBlock>  $orderedBlocks
     * @param  Collection<int, Section>  $sections
     * @return Collection<int, PlacedSegment>
     */
    public function place(Collection $orderedBlocks, Collection $sections, PlacementSettings $settings): Collection;
}
