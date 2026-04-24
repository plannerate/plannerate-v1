<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate;

/**
 * DTO de Layout de Prateleira
 *
 * Representa uma prateleira (shelf) com os produtos alocados nela:
 * - Índice da prateleira (0 = mais baixa, N = mais alta)
 * - Lista de produtos ranqueados alocados
 * - Largura e profundidade disponível
 *
 * V2: Agora valida profundidade do produto vs prateleira.
 */
class ShelfLayoutDTO
{
    /** @var RankedProductDTO[] */
    public array $products = [];

    public float $occupiedWidth = 0;

    public function __construct(
        /** ID da prateleira (ULID) */
        public readonly string $id,

        /** Índice da prateleira (0 = mais baixa) */
        public readonly int $shelfIndex,

        /** Altura da prateleira em CM */
        public readonly float $height,

        /** Largura total disponível em CM */
        public readonly float $availableWidth,

        /** Profundidade da prateleira em CM */
        public readonly float $depth = 40.0,
    ) {}

    /**
     * Adicionar produto à prateleira
     *
     * Valida:
     * - Largura disponível
     * - Profundidade do produto vs prateleira
     *
     * @return bool True se coube, false se não há espaço ou não cabe em profundidade
     */
    public function addProduct(RankedProductDTO $product): bool
    {
        $productWidth = ($product->product->width ?? 10) * $product->facings;
        $productDepth = (float) ($product->product->depth ?? 0);

        // Validar profundidade (se produto tiver depth > 0)
        if ($productDepth > 0 && $productDepth > $this->depth) {
            \Log::debug('⚠️  Produto não cabe em profundidade na shelf', [
                'product_id' => $product->product->id,
                'product_name' => $product->product->name,
                'product_depth' => $productDepth,
                'shelf_depth' => $this->depth,
                'shelf_id' => $this->id,
            ]);

            return false;
        }

        // Validar largura
        if ($this->occupiedWidth + $productWidth <= $this->availableWidth) {
            $this->products[] = $product;
            $this->occupiedWidth += $productWidth;

            return true;
        }

        return false;
    }

    /**
     * Verificar se há espaço para um produto
     */
    public function hasSpace(RankedProductDTO $product): bool
    {
        $productWidth = ($product->product->width ?? 10) * $product->facings;

        return ($this->occupiedWidth + $productWidth) <= $this->availableWidth;
    }

    /**
     * Percentual de ocupação da prateleira
     */
    public function getOccupancyPercentage(): float
    {
        if ($this->availableWidth == 0) {
            return 0;
        }

        return ($this->occupiedWidth / $this->availableWidth) * 100;
    }

    /**
     * Converter para array (para debugging)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'shelf_index' => $this->shelfIndex,
            'height' => $this->height,
            'depth' => $this->depth,
            'available_width' => $this->availableWidth,
            'occupied_width' => $this->occupiedWidth,
            'occupancy_percentage' => round($this->getOccupancyPercentage(), 2),
            'products_count' => count($this->products),
            'products' => array_map(fn ($p) => $p->toArray(), $this->products),
        ];
    }
}
