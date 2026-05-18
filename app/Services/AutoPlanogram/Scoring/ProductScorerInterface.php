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
     * Usado no modo automático — score é crítico para o ranking.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, ScoredProduct>
     */
    public function score(Collection $products, PlacementSettings $settings): Collection;

    /**
     * Pontua os produtos se houver dados de venda; caso contrário atribui score neutro 0.5 para todos.
     * Usado no modo template — layout definido pelo template, score apenas refina ordenação interna.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, ScoredProduct> — nunca vazia, nunca com score zero por falta de dados
     */
    public function scoreOrNeutral(Collection $products, PlacementSettings $settings): Collection;
}
