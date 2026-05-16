<?php

namespace App\Services\AutoPlanogram\Scoring;

use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Support\Collection;

interface ProductScorerInterface
{
    /**
     * Pontua os produtos e retorna em ordem decrescente de score.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, ScoredProduct>
     */
    public function score(Collection $products, PlacementSettings $settings): Collection;
}
