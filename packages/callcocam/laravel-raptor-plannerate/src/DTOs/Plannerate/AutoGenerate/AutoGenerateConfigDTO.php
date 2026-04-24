<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate;

/**
 * DTO de Configuração para Geração Automática de Planogramas
 *
 * Armazena todas as opções escolhidas pelo usuário no modal:
 * - Estratégia de otimização (ABC, Vendas, Margem, Mix)
 * - Configurações de facings (mín/máx)
 * - Opções de agrupamento e filtros
 * - Período de vendas
 */
class AutoGenerateConfigDTO
{
    public function __construct(
        /** Estratégia de otimização: 'abc', 'sales', 'margin', 'mix' */
        public readonly string $strategy,

        /** Usar análise ABC já calculada previamente? */
        public readonly bool $useExistingAnalysis,

        /** Data inicial do período de vendas (ano anterior por causa de sazonalidade) */
        public readonly ?string $startDate,

        /** Data final do período de vendas (ano anterior por causa de sazonalidade) */
        public readonly ?string $endDate,

        /** Número mínimo de facings (produtos lado a lado) - padrão: 1 */
        public readonly int $minFacings = 1,

        /** Número máximo de facings (produtos lado a lado) - padrão: 10 */
        public readonly int $maxFacings = 10,

        /** Agrupar produtos por subcategoria? */
        public readonly bool $groupBySubcategory = true,

        /** Incluir produtos sem vendas no período? */
        public readonly bool $includeProductsWithoutSales = false,

        /** Tipo de tabela de vendas: 'sales' (diária) ou 'monthly_summaries' (mensal) */
        public readonly string $tableType = 'monthly_summaries',

        /** Categoria de produtos (opcional) */
        public readonly ?string $categoryId = null,
    ) {}

    /**
     * Criar DTO a partir de array de request
     */
    public static function fromArray(array $data): self
    {
        return new self(
            strategy: $data['strategy'] ?? 'abc',
            useExistingAnalysis: $data['use_existing_analysis'] ?? true,
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
            minFacings: $data['min_facings'] ?? 1,
            maxFacings: $data['max_facings'] ?? 10,
            groupBySubcategory: $data['group_by_subcategory'] ?? true,
            includeProductsWithoutSales: $data['include_products_without_sales'] ?? false,
            tableType: $data['table_type'] ?? 'monthly_summaries',
            categoryId: $data['category_id'] ?? null,
        );
    }

    /**
     * Converter para array
     */
    public function toArray(): array
    {
        return [
            'strategy' => $this->strategy,
            'use_existing_analysis' => $this->useExistingAnalysis,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'min_facings' => $this->minFacings,
            'max_facings' => $this->maxFacings,
            'group_by_subcategory' => $this->groupBySubcategory,
            'include_products_without_sales' => $this->includeProductsWithoutSales,
            'table_type' => $this->tableType,
            'category_id' => $this->categoryId,
        ];
    }
}
