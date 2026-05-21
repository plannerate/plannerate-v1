<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Services\AutoPlanogram\DTO;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;

/**
 * DTO de Produto para Geração Automática
 *
 * Armazena produto + metadados de análise ABC.
 * O campo `score` é prioridade de fetch (A=3, B=2, C=1, 0=sem ABC) e NÃO determina
 * o placement — quem faz isso é o CompositeScorer em AutoPlanogramService.
 */
class RankedProductDTO
{
    public function __construct(
        /** Instância do modelo Product */
        public readonly Product $product,

        /** Classificação ABC: 'A', 'B', 'C' ou null */
        public readonly ?string $abcClass,

        /** Prioridade de fetch: A=3, B=2, C=1, 0=sem ABC. Não afeta o placement. */
        public readonly float $score,

        /** Total de vendas no período */
        public readonly float $salesTotal,

        /** Margem de contribuição */
        public readonly float $margin,

        /** ID da subcategoria (para agrupamento) */
        public readonly ?string $subcategoryId = null,

        /** Target stock da análise ABC (estoque ideal) */
        public readonly ?float $targetStock = null,

        /** Safety stock da análise ABC (estoque de segurança) */
        public readonly ?float $safetyStock = null,

        /** Número de facings calculado (1-20) */
        public int $facings = 1,
    ) {}

    /**
     * Atualizar número de facings
     */
    public function setFacings(int $facings): void
    {
        $this->facings = max(1, min(20, $facings));
    }

    /**
     * Converter para array (para debugging)
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'abc_class' => $this->abcClass,
            'score' => $this->score,
            'sales_total' => $this->salesTotal,
            'margin' => $this->margin,
            'facings' => $this->facings,
            'subcategory_id' => $this->subcategoryId,
            'target_stock' => $this->targetStock,
            'safety_stock' => $this->safetyStock,
        ];
    }
}
