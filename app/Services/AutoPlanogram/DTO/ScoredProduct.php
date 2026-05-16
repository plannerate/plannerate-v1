<?php

namespace App\Services\AutoPlanogram\DTO;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;

/**
 * Produto com score calculado pelo scorer, pronto para agrupamento.
 *
 * @phpstan-type Metadata array<string, mixed>
 */
final readonly class ScoredProduct
{
    public function __construct(
        public string $productId,
        public string $ean,
        public float $score,
        /** Produto original (carregado para acesso a dimensões, categoria, etc.) */
        public Product $product,
        /** Dados de debug: giro, margem, abc — vazio nesta fase */
        public array $metadata = [],
    ) {}
}
