<?php

namespace App\Services\AutoPlanogram\DTO;

use App\Services\AutoPlanogram\Scoring\ScoringWeightsValue;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\AutoGenerateConfigDTO;
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
