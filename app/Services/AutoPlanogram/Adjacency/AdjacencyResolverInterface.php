<?php

namespace App\Services\AutoPlanogram\Adjacency;

use App\Services\AutoPlanogram\DTO\OrderedBlock;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ProductBlock;
use Illuminate\Support\Collection;

interface AdjacencyResolverInterface
{
    /**
     * Define a ordem final dos blocos na gôndola aplicando regras de adjacência.
     *
     * @param  Collection<int, ProductBlock>  $blocks
     * @return Collection<int, OrderedBlock>
     */
    public function resolve(Collection $blocks, PlacementSettings $settings): Collection;
}
