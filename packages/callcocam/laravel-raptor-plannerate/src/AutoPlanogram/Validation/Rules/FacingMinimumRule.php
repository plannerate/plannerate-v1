<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\Rules;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementResult;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlanogramInput;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\ValidationResult;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\ValidationRuleInterface;
use Illuminate\Support\Collection;

/**
 * Validates that all products meet minimum facing requirements.
 *
 * Default minimum facing: 1
 * Can be configured per product via product configuration.
 */
final class FacingMinimumRule implements ValidationRuleInterface
{
    private const DEFAULT_MIN_FACING = 1;

    public function name(): string
    {
        return 'facing_minimum';
    }

    /**
     * @param  Collection<int, PlacedSegment>  $placedSegments
     * @return array<int, ValidationResult>
     */
    public function evaluate(Collection $placedSegments, PlanogramInput $input, PlacementResult $result): array
    {
        $results = [];

        foreach ($placedSegments as $segment) {
            foreach ($segment->layers as $layer) {
                $minFacing = $this->getMinFacingForProduct($layer->productId);

                if ($layer->quantity < $minFacing) {
                    $results[] = ValidationResult::warning(
                        $this->name(),
                        "Produto {$layer->productId} (EAN: {$layer->ean}) tem {$layer->quantity} facing(s) mas o mínimo é {$minFacing}.",
                        [$layer->productId],
                    );
                }
            }
        }

        return $results;
    }

    /**
     * Get the minimum facing requirement for a product.
     *
     * Currently uses default value. Can be extended to check product-specific settings.
     */
    private function getMinFacingForProduct(string $productId): int
    {
        // TODO: Check product-specific configuration if available
        // For now, return default
        return self::DEFAULT_MIN_FACING;
    }
}
