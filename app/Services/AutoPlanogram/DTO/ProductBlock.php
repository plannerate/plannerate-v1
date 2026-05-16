<?php

namespace App\Services\AutoPlanogram\DTO;

use Illuminate\Support\Collection;

/**
 * Agrupamento de produtos com score agregado.
 *
 * Representa um bloco coeso de produtos (ex.: mesma subcategoria)
 * que deve ser posicionado junto na gôndola.
 *
 * @phpstan-type BlockArray array{grouping_key: string, aggregate_score: float, total_width_estimate: float, children_count: int, block_hierarchy_level: int, adjacency_category_id: ?string, is_placeholder: bool}
 */
final readonly class ProductBlock
{
    public function __construct(
        /** @var Collection<int, ScoredProduct> */
        public Collection $children,
        public float $aggregateScore,
        /** Chave de agrupamento (ex.: category_id, 'all') */
        public string $groupingKey,
        /** Estimativa de largura total em cm */
        public float $totalWidthEstimate,
        public int $blockHierarchyLevel = 0,
        public ?string $adjacencyCategoryId = null,
        public bool $isPlaceholder = false,
    ) {}

    /**
     * @return BlockArray
     */
    public function toArray(): array
    {
        return [
            'grouping_key' => $this->groupingKey,
            'aggregate_score' => $this->aggregateScore,
            'total_width_estimate' => $this->totalWidthEstimate,
            'children_count' => $this->children->count(),
            'block_hierarchy_level' => $this->blockHierarchyLevel,
            'adjacency_category_id' => $this->adjacencyCategoryId,
            'is_placeholder' => $this->isPlaceholder,
        ];
    }
}
