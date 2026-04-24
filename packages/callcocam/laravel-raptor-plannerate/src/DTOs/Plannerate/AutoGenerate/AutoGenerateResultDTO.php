<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate;

/**
 * DTO de Resultado da Geração Automática
 *
 * Contém o layout completo gerado:
 * - Prateleiras com produtos alocados
 * - Produtos que não couberam
 * - Estatísticas e metadados
 */
class AutoGenerateResultDTO
{
    public function __construct(
        /** @var ShelfLayoutDTO[] Prateleiras com produtos alocados */
        public readonly array $shelves,

        /** @var RankedProductDTO[] Produtos que não couberam no planograma */
        public readonly array $unallocatedProducts,

        /** Total de produtos alocados */
        public readonly int $totalAllocated,

        /** Total de produtos não alocados */
        public readonly int $totalUnallocated,

        /** Configuração utilizada */
        public readonly AutoGenerateConfigDTO $config,

        /** Timestamp da geração */
        public readonly string $generatedAt,
    ) {}

    /**
     * Criar resultado vazio
     */
    public static function empty(AutoGenerateConfigDTO $config): self
    {
        return new self(
            shelves: [],
            unallocatedProducts: [],
            totalAllocated: 0,
            totalUnallocated: 0,
            config: $config,
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * Converter para formato de changes do editor
     *
     * Este é o formato que o frontend espera para aplicar as mudanças
     */
    public function toEditorChanges(string $gondolaId): array
    {
        $changes = [];

        foreach ($this->shelves as $shelfLayout) {
            foreach ($shelfLayout->products as $rankedProduct) {
                // Criar change para adicionar produto
                // Estrutura: criar Segment + Layer com o produto
                $changes[] = [
                    'action' => 'create',
                    'entityType' => 'segment',
                    'shelfIndex' => $shelfLayout->shelfIndex,
                    'gondolaId' => $gondolaId,
                    'data' => [
                        'product_id' => $rankedProduct->product->id,
                        'quantity' => 1, // Sem empilhamento (v1)
                        'facings' => $rankedProduct->facings,
                    ],
                ];
            }
        }

        return $changes;
    }

    /**
     * Converter para array (para response)
     */
    public function toArray(): array
    {
        return [
            'shelves' => array_map(fn ($s) => $s->toArray(), $this->shelves),
            'unallocated_products' => array_map(fn ($p) => $p->toArray(), $this->unallocatedProducts),
            'statistics' => [
                'total_allocated' => $this->totalAllocated,
                'total_unallocated' => $this->totalUnallocated,
                'shelves_count' => count($this->shelves),
            ],
            'config' => $this->config->toArray(),
            'generated_at' => $this->generatedAt,
        ];
    }
}
