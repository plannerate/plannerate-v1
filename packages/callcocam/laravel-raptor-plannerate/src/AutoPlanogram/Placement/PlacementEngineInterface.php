<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\OrderedBlock;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementResult;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementSettings;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
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
     * @param  array<string, float>  $reservedWidthPerShelf  Largura já reservada por shelf (reserva de espaço por seção)
     */
    public function place(Collection $orderedBlocks, Collection $sections, PlacementSettings $settings, array $reservedWidthPerShelf = []): PlacementResult;
}
