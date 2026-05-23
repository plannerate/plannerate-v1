<?php

namespace App\Services\AutoPlanogram;

use App\Services\AutoPlanogram\DTO\RankedProductDTO;
use App\Services\AutoPlanogram\DTO\ScoredProduct;

/**
 * Converte ScoredProduct → RankedProductDTO.
 */
final class ScoredProductMapper
{
    /**
     * Conversão básica sem facings.
     */
    public static function toRanked(ScoredProduct $sp): RankedProductDTO
    {
        return new RankedProductDTO(
            product: $sp->product,
            abcClass: $sp->metadata['abc_class'] ?? null,
            score: $sp->score,
            salesTotal: (float) ($sp->metadata['sales_total'] ?? $sp->metadata['raw_quantity'] ?? 0),
            margin: (float) ($sp->metadata['margin'] ?? $sp->metadata['raw_margem'] ?? 0),
            subcategoryId: $sp->product->category_id ?? null,
            targetStock: isset($sp->metadata['target_stock']) ? (float) $sp->metadata['target_stock'] : null,
            safetyStock: isset($sp->metadata['safety_stock']) ? (float) $sp->metadata['safety_stock'] : null,
        );
    }

    /**
     * Conversão com facings pré-calculados.
     */
    public static function toRankedWithFacings(ScoredProduct $sp): RankedProductDTO
    {
        $dto = self::toRanked($sp);

        $dto->setFacings((int) ($sp->metadata['facing_final']
            ?? $sp->metadata['estimated_facing']
            ?? $sp->metadata['facing_ideal']
            ?? 1));

        return $dto;
    }
}
