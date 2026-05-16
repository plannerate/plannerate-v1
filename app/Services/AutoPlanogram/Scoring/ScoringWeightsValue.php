<?php

namespace App\Services\AutoPlanogram\Scoring;

use App\Models\ScoringWeights;

final readonly class ScoringWeightsValue
{
    public function __construct(
        public float $giro,
        public float $margem,
        public float $estrategico,
        public float $doh,
        public int $salesWindowMonths,
        public int $blockHierarchyLevel = 6,
        public int $adjacencyHierarchyLevel = 4,
    ) {}

    public static function fromModel(ScoringWeights $model): self
    {
        return new self(
            giro: (float) $model->w_giro,
            margem: (float) $model->w_margem,
            estrategico: (float) $model->w_estrategico,
            doh: (float) $model->w_doh,
            salesWindowMonths: $model->sales_window_months,
            blockHierarchyLevel: (int) ($model->block_hierarchy_level ?? 6),
            adjacencyHierarchyLevel: (int) ($model->adjacency_hierarchy_level ?? 4),
        );
    }

    public static function default(): self
    {
        return new self(0.40, 0.30, 0.20, 0.10, 4, 6, 4);
    }
}
