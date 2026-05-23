<?php

namespace App\Services\AutoPlanogram\DTO;

use Illuminate\Support\Collection;

/**
 * Agregado de métricas de uma subcategoria, construído a partir de ScoredProducts.
 * Usado pelo CategoryRoleInferrer para inferir o papel quando categories.role é null.
 */
final readonly class CategoryAbcSummary
{
    public function __construct(
        public string $categoryId,
        /** Soma do giro bruto (raw_quantity) de todos os SKUs da subcategoria. */
        public float $totalQuantity,
        /** Soma da margem bruta (raw_margem) de todos os SKUs da subcategoria. */
        public float $totalMargem,
        /** Número de SKUs distintos na subcategoria. */
        public int $skuCount,
        /** Classe ABC dominante ('A', 'B', 'C' ou null quando não calculada). */
        public ?string $dominantAbcClass,
    ) {}

    /**
     * Monta um CategoryAbcSummary a partir de uma coleção de ScoredProducts
     * cujos products pertencem à mesma subcategoria.
     *
     * O $abcClassMap (product_id → 'A'|'B'|'C') é preferido sobre metadata['abc_class']:
     * o metadata só é gravado dentro do TemplatePlacementEngine (tarde demais para a síntese).
     *
     * @param  Collection<int, ScoredProduct>  $products
     * @param  array<string, string>  $abcClassMap  Mapa product_id → classe ABC
     */
    public static function fromScoredProducts(string $categoryId, Collection $products, array $abcClassMap = []): self
    {
        $totalQuantity = 0.0;
        $totalMargem = 0.0;
        $abcCounts = [];

        foreach ($products as $sp) {
            $totalQuantity += (float) ($sp->metadata['raw_quantity'] ?? 0);
            $totalMargem += (float) ($sp->metadata['raw_margem'] ?? 0);

            $abc = $abcClassMap[$sp->product->id] ?? $sp->metadata['abc_class'] ?? null;
            if ($abc !== null) {
                $abcCounts[$abc] = ($abcCounts[$abc] ?? 0) + 1;
            }
        }

        $dominantAbc = null;
        if ($abcCounts !== []) {
            arsort($abcCounts);
            $dominantAbc = array_key_first($abcCounts);
        }

        return new self(
            categoryId: $categoryId,
            totalQuantity: $totalQuantity,
            totalMargem: $totalMargem,
            skuCount: $products->count(),
            dominantAbcClass: $dominantAbc,
        );
    }

    /**
     * Retorna cópia com totalQuantity e totalMargem substituídos pelos valores normalizados (0–1).
     * Passar ao CategoryRoleInferrer, que espera participação relativa — não somas brutas.
     * O DTO original (com somas brutas) deve ser passado ao SlotPlanBuilder para desempate por giro.
     */
    public function withParticipation(float $normalizedQuantity, float $normalizedMargem): self
    {
        return new self(
            categoryId: $this->categoryId,
            totalQuantity: $normalizedQuantity,
            totalMargem: $normalizedMargem,
            skuCount: $this->skuCount,
            dominantAbcClass: $this->dominantAbcClass,
        );
    }
}
