<?php

namespace App\Services\AutoPlanogram\Placement;

use App\Services\AutoPlanogram\DTO\OrderedBlock;
use App\Services\AutoPlanogram\DTO\PlacementResult;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Illuminate\Support\Collection;

interface PlacementEngineInterface
{
    /**
     * @param  Collection<int, Section>  $sections
     */
    public function totalAvailableWidth(Collection $sections): float;

    /**
     * Distribui os blocos ordenados pelas prateleiras e retorna segmentos posicionados.
     *
     * @param  Collection<int, OrderedBlock>  $orderedBlocks
     * @param  Collection<int, Section>  $sections
     * @param  array<string, float>  $reservedWidthPerShelf  Largura já reservada por shelf (ex.: pelo VerticalBlockPlacer)
     */
    public function place(Collection $orderedBlocks, Collection $sections, PlacementSettings $settings, array $reservedWidthPerShelf = []): PlacementResult;
}
