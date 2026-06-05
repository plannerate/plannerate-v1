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
        public int $blockHierarchyLevel = 5,
        public int $adjacencyHierarchyLevel = 4,
        public float $verticalBlockThreshold = 0.20,
        public int $verticalBlockMinShelves = 2,
        /**
         * Peso do componente de crescimento BCG no score composto.
         * Padrão 0.0 = BCG não afeta o score (apenas metadado e RemoveDog fallback).
         * Configure w_crescimento na tabela scoring_weights para ativar por tenant.
         */
        public float $crescimento = 0.0,
    ) {}

    public static function fromModel(ScoringWeights $model): self
    {
        return new self(
            giro: (float) $model->w_giro,
            margem: (float) $model->w_margem,
            estrategico: (float) $model->w_estrategico,
            doh: (float) $model->w_doh,
            salesWindowMonths: $model->sales_window_months,
            blockHierarchyLevel: (int) ($model->block_hierarchy_level ?? 5),
            adjacencyHierarchyLevel: (int) ($model->adjacency_hierarchy_level ?? 4),
            verticalBlockThreshold: (float) ($model->vertical_block_threshold ?? 0.20),
            verticalBlockMinShelves: (int) ($model->vertical_block_min_shelves ?? 2),
            crescimento: (float) ($model->w_crescimento ?? 0.0),
        );
    }

    public static function default(): self
    {
        return new self(0.40, 0.30, 0.20, 0.10, 4, 5, 4, 0.20, 2, 0.0);
    }
}
