<?php

namespace Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\SectionGenerate;

/**
 * Um item de alocação: produto X em prateleira Y com Z facings.
 *
 * Formato esperado da resposta do PlanogramSectionAllocator (campo allocation[]).
 *
 * V2: Agora inclui dimensões do produto para cálculo de position_x.
 */
readonly class SectionAllocationItemDTO
{
    public function __construct(
        public string $shelfId,
        public string $productId,
        public int $facings,
        public float $productWidth = 0,
        public float $productDepth = 0,
        public float $productHeight = 0,
    ) {}

    /**
     * Criar a partir de um item do array retornado pelo Agent.
     *
     * @param  array{shelf_id: string, product_id: string, facings: int, product_width?: float, product_depth?: float, product_height?: float}  $item
     */
    public static function fromArray(array $item): self
    {
        return new self(
            shelfId: $item['shelf_id'] ?? $item['shelfId'] ?? '',
            productId: $item['product_id'] ?? $item['productId'] ?? '',
            facings: (int) ($item['facings'] ?? 1),
            productWidth: (float) ($item['product_width'] ?? $item['productWidth'] ?? 0),
            productDepth: (float) ($item['product_depth'] ?? $item['productDepth'] ?? 0),
            productHeight: (float) ($item['product_height'] ?? $item['productHeight'] ?? 0),
        );
    }
}
