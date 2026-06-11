<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram;

use Callcocam\LaravelRaptorPlannerate\Enums\BrandExposure;
use Callcocam\LaravelRaptorPlannerate\Enums\FlavorExposure;
use Callcocam\LaravelRaptorPlannerate\Enums\PriceOrder;
use Callcocam\LaravelRaptorPlannerate\Enums\SizeOrder;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Illuminate\Support\Collection;

/**
 * Aplica critérios de ordenação visual a uma coleção de produtos.
 * Serviço compartilhado entre TemplatePlacementEngine, VisualReorderService e ExposureRedistributeService.
 */
final class ProductOrderingService
{
    public function __construct(
        private readonly ProductSizeResolver $sizeResolver,
    ) {}

    /**
     * Ordena produtos conforme o slot: usa visual_criteria quando definido,
     * senão aplica a ordenação legada (size → price → brand).
     *
     * @param  array<string, string>  $abcClassMap  [product_id => 'A'|'B'|'C']
     * @param  array<string, array{giro: float, margem: float}>  $zoneMetricsMap
     */
    public function orderBySlot(
        Collection $products,
        PlanogramTemplateSlot $slot,
        array $abcClassMap = [],
        array $zoneMetricsMap = [],
    ): Collection {
        if ($slot->visual_criteria !== null) {
            return $this->applyCriteriaCascade($products, $slot->visual_criteria, $abcClassMap, $zoneMetricsMap);
        }

        return $this->applyLegacyOrdering($products, $slot);
    }

    /**
     * Aplica ordenação estável em cascata pela lista de critérios.
     * Aplica do menos prioritário ao mais prioritário (reverso), para que o 1º domine.
     *
     * @param  list<array{key: string, direction: string, packaging_order?: list<string>}>  $criteria
     * @param  array<string, string>  $abcClassMap
     * @param  array<string, array{giro: float, margem: float}>  $zoneMetricsMap
     */
    public function applyCriteriaCascade(
        Collection $products,
        array $criteria,
        array $abcClassMap = [],
        array $zoneMetricsMap = [],
    ): Collection {
        $sorted = $products;

        foreach (array_reverse($criteria) as $item) {
            $key = $item['key'] ?? '';
            $direction = $item['direction'] ?? 'none';
            $sorted = $this->applySingleCriterion($sorted, $key, $direction, $abcClassMap, $zoneMetricsMap, $item['packaging_order'] ?? []);
        }

        return $sorted;
    }

    /**
     * Comportamento legado: size → price → brand (compatibilidade quando visual_criteria = null).
     */
    public function applyLegacyOrdering(Collection $products, PlanogramTemplateSlot $slot): Collection
    {
        $sorted = $products;

        if ($slot->size_order !== SizeOrder::None) {
            $sorted = $sorted->sortBy(
                fn ($p) => $this->sizeResolver->resolve($p),
                SORT_NUMERIC,
                $slot->size_order === SizeOrder::Desc,
            );
        }

        if ($slot->price_order !== PriceOrder::None && $products->first()?->price !== null) {
            $sorted = $sorted->sortBy(
                fn ($p) => (float) ($p->price ?? 0),
                SORT_NUMERIC,
                $slot->price_order === PriceOrder::Desc,
            );
        }

        if ($slot->brand_exposure === BrandExposure::Vertical) {
            $sorted = $sorted->groupBy(fn ($p) => $p->brand ?? 'SEM MARCA')->flatten(1);
        }

        return $sorted;
    }

    /**
     * Agrupa produtos por marca ou sabor (para visual do tipo vertical/horizontal).
     * Usado pelo ExposureRedistributeService.
     */
    public function applyExposureGrouping(Collection $products, PlanogramTemplateSlot $slot): Collection
    {
        $sorted = $products;

        if ($slot->brand_exposure === BrandExposure::Vertical) {
            $sorted = $sorted->groupBy(fn ($p) => $p->brand ?? 'SEM MARCA')->flatten(1);
        }

        if ($slot->flavor_exposure === FlavorExposure::Vertical) {
            $sorted = $sorted->groupBy(fn ($p) => $p->flavor ?? 'SEM SABOR')->flatten(1);
        }

        return $sorted;
    }

    /**
     * Aplica um único critério de ordenação (stable sort).
     *
     * @param  string  $key  marca|preco|tamanho|score_abc|margem|tipo|embalagem|sabor|atributo
     * @param  string  $direction  asc|desc|none
     * @param  array<string, string>  $abcClassMap
     * @param  array<string, array{giro: float, margem: float}>  $zoneMetricsMap
     * @param  list<string>  $packagingOrder  Ordem customizada de packaging_type (critério embalagem)
     */
    public function applySingleCriterion(
        Collection $products,
        string $key,
        string $direction,
        array $abcClassMap = [],
        array $zoneMetricsMap = [],
        array $packagingOrder = [],
    ): Collection {
        $desc = $direction === 'desc';

        return match ($key) {
            'marca' => $products->sortBy(
                fn ($p) => strtolower((string) ($p->brand ?? 'zzz')),
                SORT_STRING,
                $desc,
            ),
            'preco' => $products->sortBy(
                fn ($p) => (float) ($p->price ?? 0),
                SORT_NUMERIC,
                $desc,
            ),
            'tamanho' => $products->sortBy(
                fn ($p) => $this->sizeResolver->resolve($p),
                SORT_NUMERIC,
                $desc,
            ),
            'score_abc' => $products->sortBy(
                fn ($p) => match ($abcClassMap[$p->id] ?? 'B') {
                    'A' => 0,
                    'B' => 1,
                    'C' => 2,
                    default => 1,
                },
                SORT_NUMERIC,
                $desc,
            ),
            'margem' => $products->sortBy(
                fn ($p) => (float) ($zoneMetricsMap[$p->id]['margem'] ?? 0),
                SORT_NUMERIC,
                $desc,
            ),
            'tipo' => $products->sortBy(
                fn ($p) => strtolower((string) ($p->type ?? 'zzz')),
                SORT_STRING,
                $desc,
            ),
            'embalagem' => $this->applyPackagingOrder($products, $packagingOrder),
            'sabor' => $products->sortBy(
                fn ($p) => strtolower((string) ($p->flavor ?? 'zzz')),
                SORT_STRING,
                $desc,
            ),
            'atributo' => $products->sortBy(
                fn ($p) => strtolower((string) ($p->sortiment_attribute ?? 'zzz')),
                SORT_STRING,
                $desc,
            ),
            default => $products,
        };
    }

    /**
     * Ordena produtos pela posição do packaging_type na lista configurada (prompt 41).
     * Produtos com tipo não listado (ou sem tipo) vão para o fim.
     * Ordem vazia → mantém a ordem original (sem fallback alfabético).
     *
     * @param  list<string>  $order  Lista de packaging_type em ordem de prioridade
     */
    private function applyPackagingOrder(Collection $products, array $order): Collection
    {
        if (empty($order)) {
            return $products;
        }

        $indexMap = array_flip($order);

        return $products->sortBy(
            fn ($p) => $indexMap[$p->packaging_type ?? ''] ?? PHP_INT_MAX,
            SORT_NUMERIC,
        );
    }
}
