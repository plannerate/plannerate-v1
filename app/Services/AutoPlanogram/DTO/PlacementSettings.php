<?php

namespace App\Services\AutoPlanogram\DTO;

use App\Services\AutoPlanogram\Scoring\ScoringWeightsValue;
use Illuminate\Support\Collection;

/**
 * Configurações para o processo de geração do planograma.
 *
 * @phpstan-type MetaArray array{strategy: string, use_existing_analysis: bool, start_date: ?string, end_date: ?string, min_facings: int, max_facings: int, group_by_subcategory: bool, include_products_without_sales: bool, table_type: string, category_id: ?string, tenant_id: ?string, store_id: ?string, block_hierarchy_level: int, adjacency_hierarchy_level: int, target_occupancy_rate: float}
 */
final readonly class PlacementSettings
{
    public function __construct(
        /** Estratégia: 'abc', 'sales', 'margin', 'mix' */
        public string $strategy,

        /** Usar análise ABC pré-calculada */
        public bool $useExistingAnalysis,

        /** Data inicial do período de vendas */
        public ?string $startDate,

        /** Data final do período de vendas */
        public ?string $endDate,

        /** Número mínimo de facings */
        public int $minFacings = 1,

        /** Número máximo de facings */
        public int $maxFacings = 10,

        /** Agrupar produtos por subcategoria */
        public bool $groupBySubcategory = true,

        /** Incluir produtos sem vendas */
        public bool $includeProductsWithoutSales = false,

        /** Tipo de tabela: 'sales' ou 'monthly_summaries' */
        public string $tableType = 'monthly_summaries',

        /** Filtro de categoria (opcional) */
        public ?string $categoryId = null,

        /** ID do tenant (para scoring multi-fator) */
        public ?string $tenantId = null,

        /** ID da loja (filtro opcional de vendas) */
        public ?string $storeId = null,

        /** Pesos para CompositeScorer (null = usar padrão/banco) */
        public ?ScoringWeightsValue $weights = null,

        /** Nível hierárquico usado para formar blocos */
        public ?int $blockHierarchyLevel = null,

        /** Nível hierárquico usado para aplicar adjacência */
        public ?int $adjacencyHierarchyLevel = null,

        /** Meta de ocupação da gôndola para escala de facing */
        public float $targetOccupancyRate = 0.90,

        /** Top % de score que recebe bloco vertical (0.20 = top 20%) */
        public float $verticalBlockThreshold = 0.20,

        /** Mínimo de prateleiras para aplicar bloco vertical */
        public int $verticalBlockMinShelves = 2,

        /** ID do template de planograma (null = modo automático) */
        public ?string $templateId = null,

        /** Número de sections (módulos) da gôndola */
        public int $numModules = 0,

        /** ID do planograma sendo gerado (para registrar subtemplate_id) */
        public ?string $planogramId = null,

        /** Produtos do mix carregados para busca no modo template */
        public Collection $products = new Collection,

        /** Mapa ABC por produto [product_id => 'A'|'B'|'C'] para expansion e ReduceC fallback */
        public array $abcClassMap = [],

        /** Mapa de estoque alvo por produto [product_id => float] vindo da análise ABC */
        public array $targetStockMap = [],

        /**
         * Métricas de zona por produto [product_id => ['giro' => float, 'margem' => float]]
         * Usadas pelo TemplatePlacementEngine para priorizar produtos por zona térmica.
         *
         * @var array<string, array{giro: float, margem: float}>
         */
        public array $zoneMetricsMap = [],

        /**
         * Produtos obrigatórios [product_id => true] — entram mesmo sem histórico de vendas.
         *
         * @var array<string, true>
         */
        public array $mandatoryProductIds = [],

        /**
         * Produtos bloqueados [product_id => true] — nunca entram no planograma.
         *
         * @var array<string, true>
         */
        public array $blockedProductIds = [],

        /**
         * Marcas bloqueadas [brand => true] — todos os produtos da marca são excluídos.
         *
         * @var array<string, true>
         */
        public array $blockedBrands = [],

        /**
         * Subcategorias bloqueadas [category_id => true] — todos os produtos da subcat são excluídos.
         *
         * @var array<string, true>
         */
        public array $blockedSubcategoryIds = [],

        /** Tipo de expansão de frentes (defaults globais para slots sintetizados) */
        public ?string $facingExpansion = null,

        /** Usar estoque alvo para expandir frentes */
        public bool $useTargetStock = false,

        /** Comportamento por falta de espaço */
        public ?string $spaceFallback = null,

        /** Limite de participação por SKU (%) */
        public ?int $maxSharePerSku = null,

        /** Limite de participação por marca (%) */
        public ?int $maxSharePerBrand = null,

        /** Limite de participação por subcategoria (%) */
        public ?int $maxSharePerSubcategory = null,

        /**
         * Overrides de configuração de geração por categoria desta gôndola específica.
         * Indexado por category_id; apenas campos não-nulos sobrepõem o valor do template slot.
         *
         * @var array<string, array<string, mixed>>
         */
        public array $gondolaSlotOverrides = [],

        /** Prioridade de zona quente para o subtemplate sintetizado (valor de ZonePriority) */
        public ?string $hotZonePriority = null,

        /** Prioridade de zona fria para o subtemplate sintetizado (valor de ZonePriority) */
        public ?string $coldZonePriority = null,

        /** Sentido de leitura para o subtemplate sintetizado (valor de FlowDirection) */
        public ?string $flowDirection = null,

        /**
         * Critérios visuais secundários adicionados após score_abc no subtemplate sintetizado.
         *
         * @var list<array{key: string, direction: string}>
         */
        public array $secondaryCriteria = [],

        /**
         * Mapa de quadrante BCG por produto [product_id => 'star'|'cash_cow'|'question_mark'|'dog'].
         * Vazio quando BCG não pôde ser calculado (sem dados de dois períodos).
         *
         * @var array<string, string>
         */
        public array $bcgMap = [],
    ) {}

    public function usesTemplate(): bool
    {
        return $this->templateId !== null;
    }

    public function withTemplate(string $templateId, int $numModules, ?string $planogramId, Collection $products): self
    {
        return new self(
            strategy: $this->strategy,
            useExistingAnalysis: $this->useExistingAnalysis,
            startDate: $this->startDate,
            endDate: $this->endDate,
            minFacings: $this->minFacings,
            maxFacings: $this->maxFacings,
            groupBySubcategory: $this->groupBySubcategory,
            includeProductsWithoutSales: $this->includeProductsWithoutSales,
            tableType: $this->tableType,
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
            storeId: $this->storeId,
            weights: $this->weights,
            blockHierarchyLevel: $this->blockHierarchyLevel,
            adjacencyHierarchyLevel: $this->adjacencyHierarchyLevel,
            targetOccupancyRate: $this->targetOccupancyRate,
            verticalBlockThreshold: $this->verticalBlockThreshold,
            verticalBlockMinShelves: $this->verticalBlockMinShelves,
            templateId: $templateId,
            numModules: $numModules,
            planogramId: $planogramId,
            products: $products,
            abcClassMap: $this->abcClassMap,
            targetStockMap: $this->targetStockMap,
            zoneMetricsMap: $this->zoneMetricsMap,
            mandatoryProductIds: $this->mandatoryProductIds,
            blockedProductIds: $this->blockedProductIds,
            blockedBrands: $this->blockedBrands,
            blockedSubcategoryIds: $this->blockedSubcategoryIds,
            facingExpansion: $this->facingExpansion,
            useTargetStock: $this->useTargetStock,
            spaceFallback: $this->spaceFallback,
            maxSharePerSku: $this->maxSharePerSku,
            maxSharePerBrand: $this->maxSharePerBrand,
            maxSharePerSubcategory: $this->maxSharePerSubcategory,
            gondolaSlotOverrides: $this->gondolaSlotOverrides,
            hotZonePriority: $this->hotZonePriority,
            coldZonePriority: $this->coldZonePriority,
            flowDirection: $this->flowDirection,
            secondaryCriteria: $this->secondaryCriteria,
            bcgMap: $this->bcgMap,
        );
    }

    public function withAbcMap(array $abcClassMap): self
    {
        return new self(
            strategy: $this->strategy,
            useExistingAnalysis: $this->useExistingAnalysis,
            startDate: $this->startDate,
            endDate: $this->endDate,
            minFacings: $this->minFacings,
            maxFacings: $this->maxFacings,
            groupBySubcategory: $this->groupBySubcategory,
            includeProductsWithoutSales: $this->includeProductsWithoutSales,
            tableType: $this->tableType,
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
            storeId: $this->storeId,
            weights: $this->weights,
            blockHierarchyLevel: $this->blockHierarchyLevel,
            adjacencyHierarchyLevel: $this->adjacencyHierarchyLevel,
            targetOccupancyRate: $this->targetOccupancyRate,
            verticalBlockThreshold: $this->verticalBlockThreshold,
            verticalBlockMinShelves: $this->verticalBlockMinShelves,
            templateId: $this->templateId,
            numModules: $this->numModules,
            planogramId: $this->planogramId,
            products: $this->products,
            abcClassMap: $abcClassMap,
            targetStockMap: $this->targetStockMap,
            zoneMetricsMap: $this->zoneMetricsMap,
            mandatoryProductIds: $this->mandatoryProductIds,
            blockedProductIds: $this->blockedProductIds,
            blockedBrands: $this->blockedBrands,
            blockedSubcategoryIds: $this->blockedSubcategoryIds,
            facingExpansion: $this->facingExpansion,
            useTargetStock: $this->useTargetStock,
            spaceFallback: $this->spaceFallback,
            maxSharePerSku: $this->maxSharePerSku,
            maxSharePerBrand: $this->maxSharePerBrand,
            maxSharePerSubcategory: $this->maxSharePerSubcategory,
            gondolaSlotOverrides: $this->gondolaSlotOverrides,
            hotZonePriority: $this->hotZonePriority,
            coldZonePriority: $this->coldZonePriority,
            flowDirection: $this->flowDirection,
            secondaryCriteria: $this->secondaryCriteria,
        );
    }

    /** @param array<string, float> $targetStockMap */
    public function withTargetStockMap(array $targetStockMap): self
    {
        return new self(
            strategy: $this->strategy,
            useExistingAnalysis: $this->useExistingAnalysis,
            startDate: $this->startDate,
            endDate: $this->endDate,
            minFacings: $this->minFacings,
            maxFacings: $this->maxFacings,
            groupBySubcategory: $this->groupBySubcategory,
            includeProductsWithoutSales: $this->includeProductsWithoutSales,
            tableType: $this->tableType,
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
            storeId: $this->storeId,
            weights: $this->weights,
            blockHierarchyLevel: $this->blockHierarchyLevel,
            adjacencyHierarchyLevel: $this->adjacencyHierarchyLevel,
            targetOccupancyRate: $this->targetOccupancyRate,
            verticalBlockThreshold: $this->verticalBlockThreshold,
            verticalBlockMinShelves: $this->verticalBlockMinShelves,
            templateId: $this->templateId,
            numModules: $this->numModules,
            planogramId: $this->planogramId,
            products: $this->products,
            abcClassMap: $this->abcClassMap,
            targetStockMap: $targetStockMap,
            zoneMetricsMap: $this->zoneMetricsMap,
            mandatoryProductIds: $this->mandatoryProductIds,
            blockedProductIds: $this->blockedProductIds,
            blockedBrands: $this->blockedBrands,
            blockedSubcategoryIds: $this->blockedSubcategoryIds,
            facingExpansion: $this->facingExpansion,
            useTargetStock: $this->useTargetStock,
            spaceFallback: $this->spaceFallback,
            maxSharePerSku: $this->maxSharePerSku,
            maxSharePerBrand: $this->maxSharePerBrand,
            maxSharePerSubcategory: $this->maxSharePerSubcategory,
            gondolaSlotOverrides: $this->gondolaSlotOverrides,
            hotZonePriority: $this->hotZonePriority,
            coldZonePriority: $this->coldZonePriority,
            flowDirection: $this->flowDirection,
            secondaryCriteria: $this->secondaryCriteria,
        );
    }

    public function withExtras(?string $tenantId, ?ScoringWeightsValue $weights): self
    {
        return new self(
            strategy: $this->strategy,
            useExistingAnalysis: $this->useExistingAnalysis,
            startDate: $this->startDate,
            endDate: $this->endDate,
            minFacings: $this->minFacings,
            maxFacings: $this->maxFacings,
            groupBySubcategory: $this->groupBySubcategory,
            includeProductsWithoutSales: $this->includeProductsWithoutSales,
            tableType: $this->tableType,
            categoryId: $this->categoryId,
            tenantId: $tenantId ?? $this->tenantId,
            storeId: $this->storeId,
            weights: $weights ?? $this->weights,
            blockHierarchyLevel: $this->blockHierarchyLevel,
            adjacencyHierarchyLevel: $this->adjacencyHierarchyLevel,
            targetOccupancyRate: $this->targetOccupancyRate,
            verticalBlockThreshold: $weights?->verticalBlockThreshold ?? $this->verticalBlockThreshold,
            verticalBlockMinShelves: $weights?->verticalBlockMinShelves ?? $this->verticalBlockMinShelves,
            templateId: $this->templateId,
            numModules: $this->numModules,
            planogramId: $this->planogramId,
            products: $this->products,
            abcClassMap: $this->abcClassMap,
            targetStockMap: $this->targetStockMap,
            zoneMetricsMap: $this->zoneMetricsMap,
            mandatoryProductIds: $this->mandatoryProductIds,
            blockedProductIds: $this->blockedProductIds,
            blockedBrands: $this->blockedBrands,
            blockedSubcategoryIds: $this->blockedSubcategoryIds,
            facingExpansion: $this->facingExpansion,
            useTargetStock: $this->useTargetStock,
            spaceFallback: $this->spaceFallback,
            maxSharePerSku: $this->maxSharePerSku,
            maxSharePerBrand: $this->maxSharePerBrand,
            maxSharePerSubcategory: $this->maxSharePerSubcategory,
            gondolaSlotOverrides: $this->gondolaSlotOverrides,
            hotZonePriority: $this->hotZonePriority,
            coldZonePriority: $this->coldZonePriority,
            flowDirection: $this->flowDirection,
            secondaryCriteria: $this->secondaryCriteria,
        );
    }

    public function withProducts(Collection $products): self
    {
        return new self(
            strategy: $this->strategy,
            useExistingAnalysis: $this->useExistingAnalysis,
            startDate: $this->startDate,
            endDate: $this->endDate,
            minFacings: $this->minFacings,
            maxFacings: $this->maxFacings,
            groupBySubcategory: $this->groupBySubcategory,
            includeProductsWithoutSales: $this->includeProductsWithoutSales,
            tableType: $this->tableType,
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
            storeId: $this->storeId,
            weights: $this->weights,
            blockHierarchyLevel: $this->blockHierarchyLevel,
            adjacencyHierarchyLevel: $this->adjacencyHierarchyLevel,
            targetOccupancyRate: $this->targetOccupancyRate,
            verticalBlockThreshold: $this->verticalBlockThreshold,
            verticalBlockMinShelves: $this->verticalBlockMinShelves,
            templateId: $this->templateId,
            numModules: $this->numModules,
            planogramId: $this->planogramId,
            products: $products,
            abcClassMap: $this->abcClassMap,
            targetStockMap: $this->targetStockMap,
            zoneMetricsMap: $this->zoneMetricsMap,
            mandatoryProductIds: $this->mandatoryProductIds,
            blockedProductIds: $this->blockedProductIds,
            blockedBrands: $this->blockedBrands,
            blockedSubcategoryIds: $this->blockedSubcategoryIds,
            facingExpansion: $this->facingExpansion,
            useTargetStock: $this->useTargetStock,
            spaceFallback: $this->spaceFallback,
            maxSharePerSku: $this->maxSharePerSku,
            maxSharePerBrand: $this->maxSharePerBrand,
            maxSharePerSubcategory: $this->maxSharePerSubcategory,
            gondolaSlotOverrides: $this->gondolaSlotOverrides,
            hotZonePriority: $this->hotZonePriority,
            coldZonePriority: $this->coldZonePriority,
            flowDirection: $this->flowDirection,
            secondaryCriteria: $this->secondaryCriteria,
        );
    }

    /**
     * @param  array<string, array{giro: float, margem: float}>  $zoneMetricsMap
     */
    public function withZoneMetrics(array $zoneMetricsMap): self
    {
        return new self(
            strategy: $this->strategy,
            useExistingAnalysis: $this->useExistingAnalysis,
            startDate: $this->startDate,
            endDate: $this->endDate,
            minFacings: $this->minFacings,
            maxFacings: $this->maxFacings,
            groupBySubcategory: $this->groupBySubcategory,
            includeProductsWithoutSales: $this->includeProductsWithoutSales,
            tableType: $this->tableType,
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
            storeId: $this->storeId,
            weights: $this->weights,
            blockHierarchyLevel: $this->blockHierarchyLevel,
            adjacencyHierarchyLevel: $this->adjacencyHierarchyLevel,
            targetOccupancyRate: $this->targetOccupancyRate,
            verticalBlockThreshold: $this->verticalBlockThreshold,
            verticalBlockMinShelves: $this->verticalBlockMinShelves,
            templateId: $this->templateId,
            numModules: $this->numModules,
            planogramId: $this->planogramId,
            products: $this->products,
            abcClassMap: $this->abcClassMap,
            targetStockMap: $this->targetStockMap,
            zoneMetricsMap: $zoneMetricsMap,
            mandatoryProductIds: $this->mandatoryProductIds,
            blockedProductIds: $this->blockedProductIds,
            blockedBrands: $this->blockedBrands,
            blockedSubcategoryIds: $this->blockedSubcategoryIds,
            facingExpansion: $this->facingExpansion,
            useTargetStock: $this->useTargetStock,
            spaceFallback: $this->spaceFallback,
            maxSharePerSku: $this->maxSharePerSku,
            maxSharePerBrand: $this->maxSharePerBrand,
            maxSharePerSubcategory: $this->maxSharePerSubcategory,
            gondolaSlotOverrides: $this->gondolaSlotOverrides,
            hotZonePriority: $this->hotZonePriority,
            coldZonePriority: $this->coldZonePriority,
            flowDirection: $this->flowDirection,
            secondaryCriteria: $this->secondaryCriteria,
        );
    }

    /**
     * @param  array<string, true>  $mandatoryProductIds
     * @param  array<string, true>  $blockedProductIds
     * @param  array<string, true>  $blockedBrands
     * @param  array<string, true>  $blockedSubcategoryIds
     */
    public function withProductRules(
        array $mandatoryProductIds,
        array $blockedProductIds,
        array $blockedBrands,
        array $blockedSubcategoryIds,
    ): self {
        return new self(
            strategy: $this->strategy,
            useExistingAnalysis: $this->useExistingAnalysis,
            startDate: $this->startDate,
            endDate: $this->endDate,
            minFacings: $this->minFacings,
            maxFacings: $this->maxFacings,
            groupBySubcategory: $this->groupBySubcategory,
            includeProductsWithoutSales: $this->includeProductsWithoutSales,
            tableType: $this->tableType,
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
            storeId: $this->storeId,
            weights: $this->weights,
            blockHierarchyLevel: $this->blockHierarchyLevel,
            adjacencyHierarchyLevel: $this->adjacencyHierarchyLevel,
            targetOccupancyRate: $this->targetOccupancyRate,
            verticalBlockThreshold: $this->verticalBlockThreshold,
            verticalBlockMinShelves: $this->verticalBlockMinShelves,
            templateId: $this->templateId,
            numModules: $this->numModules,
            planogramId: $this->planogramId,
            products: $this->products,
            abcClassMap: $this->abcClassMap,
            targetStockMap: $this->targetStockMap,
            zoneMetricsMap: $this->zoneMetricsMap,
            mandatoryProductIds: $mandatoryProductIds,
            blockedProductIds: $blockedProductIds,
            blockedBrands: $blockedBrands,
            blockedSubcategoryIds: $blockedSubcategoryIds,
            facingExpansion: $this->facingExpansion,
            useTargetStock: $this->useTargetStock,
            spaceFallback: $this->spaceFallback,
            maxSharePerSku: $this->maxSharePerSku,
            maxSharePerBrand: $this->maxSharePerBrand,
            maxSharePerSubcategory: $this->maxSharePerSubcategory,
            gondolaSlotOverrides: $this->gondolaSlotOverrides,
            hotZonePriority: $this->hotZonePriority,
            coldZonePriority: $this->coldZonePriority,
            flowDirection: $this->flowDirection,
            secondaryCriteria: $this->secondaryCriteria,
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $gondolaSlotOverrides
     */
    public function withSlotOverrides(array $gondolaSlotOverrides): self
    {
        return new self(
            strategy: $this->strategy,
            useExistingAnalysis: $this->useExistingAnalysis,
            startDate: $this->startDate,
            endDate: $this->endDate,
            minFacings: $this->minFacings,
            maxFacings: $this->maxFacings,
            groupBySubcategory: $this->groupBySubcategory,
            includeProductsWithoutSales: $this->includeProductsWithoutSales,
            tableType: $this->tableType,
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
            storeId: $this->storeId,
            weights: $this->weights,
            blockHierarchyLevel: $this->blockHierarchyLevel,
            adjacencyHierarchyLevel: $this->adjacencyHierarchyLevel,
            targetOccupancyRate: $this->targetOccupancyRate,
            verticalBlockThreshold: $this->verticalBlockThreshold,
            verticalBlockMinShelves: $this->verticalBlockMinShelves,
            templateId: $this->templateId,
            numModules: $this->numModules,
            planogramId: $this->planogramId,
            products: $this->products,
            abcClassMap: $this->abcClassMap,
            targetStockMap: $this->targetStockMap,
            zoneMetricsMap: $this->zoneMetricsMap,
            mandatoryProductIds: $this->mandatoryProductIds,
            blockedProductIds: $this->blockedProductIds,
            blockedBrands: $this->blockedBrands,
            blockedSubcategoryIds: $this->blockedSubcategoryIds,
            facingExpansion: $this->facingExpansion,
            useTargetStock: $this->useTargetStock,
            spaceFallback: $this->spaceFallback,
            maxSharePerSku: $this->maxSharePerSku,
            maxSharePerBrand: $this->maxSharePerBrand,
            maxSharePerSubcategory: $this->maxSharePerSubcategory,
            gondolaSlotOverrides: $gondolaSlotOverrides,
            hotZonePriority: $this->hotZonePriority,
            coldZonePriority: $this->coldZonePriority,
            flowDirection: $this->flowDirection,
            secondaryCriteria: $this->secondaryCriteria,
        );
    }

    /**
     * @param  array<string, string>  $bcgMap  [product_id => 'star'|'cash_cow'|'question_mark'|'dog']
     */
    public function withBcgMap(array $bcgMap): self
    {
        return new self(
            strategy: $this->strategy,
            useExistingAnalysis: $this->useExistingAnalysis,
            startDate: $this->startDate,
            endDate: $this->endDate,
            minFacings: $this->minFacings,
            maxFacings: $this->maxFacings,
            groupBySubcategory: $this->groupBySubcategory,
            includeProductsWithoutSales: $this->includeProductsWithoutSales,
            tableType: $this->tableType,
            categoryId: $this->categoryId,
            tenantId: $this->tenantId,
            storeId: $this->storeId,
            weights: $this->weights,
            blockHierarchyLevel: $this->blockHierarchyLevel,
            adjacencyHierarchyLevel: $this->adjacencyHierarchyLevel,
            targetOccupancyRate: $this->targetOccupancyRate,
            verticalBlockThreshold: $this->verticalBlockThreshold,
            verticalBlockMinShelves: $this->verticalBlockMinShelves,
            templateId: $this->templateId,
            numModules: $this->numModules,
            planogramId: $this->planogramId,
            products: $this->products,
            abcClassMap: $this->abcClassMap,
            targetStockMap: $this->targetStockMap,
            zoneMetricsMap: $this->zoneMetricsMap,
            mandatoryProductIds: $this->mandatoryProductIds,
            blockedProductIds: $this->blockedProductIds,
            blockedBrands: $this->blockedBrands,
            blockedSubcategoryIds: $this->blockedSubcategoryIds,
            facingExpansion: $this->facingExpansion,
            useTargetStock: $this->useTargetStock,
            spaceFallback: $this->spaceFallback,
            maxSharePerSku: $this->maxSharePerSku,
            maxSharePerBrand: $this->maxSharePerBrand,
            maxSharePerSubcategory: $this->maxSharePerSubcategory,
            gondolaSlotOverrides: $this->gondolaSlotOverrides,
            hotZonePriority: $this->hotZonePriority,
            coldZonePriority: $this->coldZonePriority,
            flowDirection: $this->flowDirection,
            secondaryCriteria: $this->secondaryCriteria,
            bcgMap: $bcgMap,
        );
    }

    public static function fromConfigDto(AutoGenerateConfigDTO $dto): self
    {
        return new self(
            strategy: $dto->strategy,
            useExistingAnalysis: $dto->useExistingAnalysis,
            startDate: $dto->startDate,
            endDate: $dto->endDate,
            minFacings: $dto->minFacings,
            maxFacings: $dto->maxFacings,
            groupBySubcategory: $dto->groupBySubcategory,
            includeProductsWithoutSales: $dto->includeProductsWithoutSales,
            tableType: $dto->tableType,
            categoryId: $dto->categoryId,
            targetOccupancyRate: 0.90,
            facingExpansion: $dto->facingExpansion,
            useTargetStock: $dto->useTargetStock,
            spaceFallback: $dto->spaceFallback,
            maxSharePerSku: $dto->maxSharePerSku,
            maxSharePerBrand: $dto->maxSharePerBrand,
            maxSharePerSubcategory: $dto->maxSharePerSubcategory,
            hotZonePriority: $dto->hotZonePriority,
            coldZonePriority: $dto->coldZonePriority,
            flowDirection: $dto->flowDirection,
            secondaryCriteria: $dto->secondaryCriteria,
        );
    }

    public function toConfigDto(): AutoGenerateConfigDTO
    {
        return new AutoGenerateConfigDTO(
            strategy: $this->strategy,
            useExistingAnalysis: $this->useExistingAnalysis,
            startDate: $this->startDate,
            endDate: $this->endDate,
            minFacings: $this->minFacings,
            maxFacings: $this->maxFacings,
            groupBySubcategory: $this->groupBySubcategory,
            includeProductsWithoutSales: $this->includeProductsWithoutSales,
            tableType: $this->tableType,
            categoryId: $this->categoryId,
            facingExpansion: $this->facingExpansion,
            useTargetStock: $this->useTargetStock,
            spaceFallback: $this->spaceFallback,
            maxSharePerSku: $this->maxSharePerSku,
            maxSharePerBrand: $this->maxSharePerBrand,
            maxSharePerSubcategory: $this->maxSharePerSubcategory,
            hotZonePriority: $this->hotZonePriority,
            coldZonePriority: $this->coldZonePriority,
            flowDirection: $this->flowDirection,
            secondaryCriteria: $this->secondaryCriteria,
        );
    }

    /**
     * @return MetaArray
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
            'tenant_id' => $this->tenantId,
            'store_id' => $this->storeId,
            'block_hierarchy_level' => $this->resolvedBlockHierarchyLevel(),
            'adjacency_hierarchy_level' => $this->resolvedAdjacencyHierarchyLevel(),
            'target_occupancy_rate' => $this->targetOccupancyRate,
            'vertical_block_threshold' => $this->verticalBlockThreshold,
            'vertical_block_min_shelves' => $this->verticalBlockMinShelves,
        ];
    }

    public function resolvedBlockHierarchyLevel(): int
    {
        return $this->blockHierarchyLevel
            ?? $this->weights?->blockHierarchyLevel
            ?? ScoringWeightsValue::default()->blockHierarchyLevel;
    }

    public function resolvedAdjacencyHierarchyLevel(): int
    {
        return $this->adjacencyHierarchyLevel
            ?? $this->weights?->adjacencyHierarchyLevel
            ?? ScoringWeightsValue::default()->adjacencyHierarchyLevel;
    }
}
