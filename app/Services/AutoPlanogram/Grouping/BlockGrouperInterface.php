<?php

namespace App\Services\AutoPlanogram\Grouping;

use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ProductBlock;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use Illuminate\Support\Collection;

interface BlockGrouperInterface
{
    /**
     * Agrupa produtos pontuados em blocos para posicionamento conjunto.
     *
     * @param  Collection<int, ScoredProduct>  $scoredProducts
     * @return Collection<int, ProductBlock>
     */
    public function group(Collection $scoredProducts, PlacementSettings $settings): Collection;
}
