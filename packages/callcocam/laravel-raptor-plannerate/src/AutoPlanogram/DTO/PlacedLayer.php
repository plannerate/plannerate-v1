<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO;

/**
 * Camada de produto dentro de um segmento.
 *
 * Representa o facing (quantidade lado a lado) e o empilhamento.
 */
final readonly class PlacedLayer
{
    public function __construct(
        public string $productId,
        public string $ean,
        /** Número de facings (produtos lado a lado) */
        public int $quantity,
        /** Empilhamento vertical (unidades em altura) */
        public int $height,
    ) {}
}
