<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Services\AutoPlanogram\DTO;

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

        /** Tipo de expansão de frentes aplicado a todos os slots sintetizados */
        public readonly ?string $facingExpansion = null,

        /** Usar estoque alvo para expandir frentes nos slots sintetizados */
        public readonly bool $useTargetStock = false,

        /** Comportamento por falta de espaço aplicado a todos os slots sintetizados */
        public readonly ?string $spaceFallback = null,

        /** Limite de participação por SKU (%) — null = sem limite */
        public readonly ?int $maxSharePerSku = null,

        /** Limite de participação por marca (%) — null = sem limite */
        public readonly ?int $maxSharePerBrand = null,

        /** Limite de participação por subcategoria (%) — null = sem limite */
        public readonly ?int $maxSharePerSubcategory = null,

        /**
         * Corte acumulado para classificação A (padrão 80%).
         * Produtos que representam até $abcCutoffA do volume total são classe A.
         */
        public readonly float $abcCutoffA = 0.80,

        /**
         * Corte acumulado para classificação B (padrão 90%).
         * Produtos entre $abcCutoffA e $abcCutoffB são classe B; acima disso, classe C.
         *
         * Padrão aumentado de 0.85 → 0.90: com a faixa B em 10% (vs. 5% anterior),
         * mais produtos recebem classificação B, evitando distorções na distribuição
         * quando poucos itens dominam >80% do volume (caso típico de mercearia seca).
         */
        public readonly float $abcCutoffB = 0.90,

        /** Prioridade de zona quente (valor de ZonePriority) — null = maior_margem */
        public readonly ?string $hotZonePriority = 'maior_margem',

        /** Prioridade de zona fria (valor de ZonePriority) — null = complementar_fria */
        public readonly ?string $coldZonePriority = 'complementar_fria',

        /** Sentido de leitura (valor de FlowDirection) — null = LeftToRight */
        public readonly ?string $flowDirection = null,

        /**
         * Critérios visuais secundários adicionados após score_abc.
         *
         * @var list<array{key: string, direction: string}>
         */
        public readonly array $secondaryCriteria = [],
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
            facingExpansion: $data['facing_expansion'] ?? null,
            useTargetStock: (bool) ($data['use_target_stock'] ?? false),
            spaceFallback: $data['space_fallback'] ?? null,
            maxSharePerSku: isset($data['max_share_per_sku']) ? (int) $data['max_share_per_sku'] : null,
            maxSharePerBrand: isset($data['max_share_per_brand']) ? (int) $data['max_share_per_brand'] : null,
            maxSharePerSubcategory: isset($data['max_share_per_subcategory']) ? (int) $data['max_share_per_subcategory'] : null,
            abcCutoffA: (float) ($data['abc_cutoff_a'] ?? 0.80),
            abcCutoffB: (float) ($data['abc_cutoff_b'] ?? 0.90),
            hotZonePriority: $data['hot_zone_priority'] ?? 'maior_margem',
            coldZonePriority: $data['cold_zone_priority'] ?? 'complementar_fria',
            flowDirection: $data['flow_direction'] ?? null,
            secondaryCriteria: $data['secondary_criteria'] ?? [],
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
            'facing_expansion' => $this->facingExpansion,
            'use_target_stock' => $this->useTargetStock,
            'space_fallback' => $this->spaceFallback,
            'max_share_per_sku' => $this->maxSharePerSku,
            'max_share_per_brand' => $this->maxSharePerBrand,
            'max_share_per_subcategory' => $this->maxSharePerSubcategory,
            'abc_cutoff_a' => $this->abcCutoffA,
            'abc_cutoff_b' => $this->abcCutoffB,
            'hot_zone_priority' => $this->hotZonePriority,
            'cold_zone_priority' => $this->coldZonePriority,
            'flow_direction' => $this->flowDirection,
            'secondary_criteria' => $this->secondaryCriteria,
        ];
    }
}
