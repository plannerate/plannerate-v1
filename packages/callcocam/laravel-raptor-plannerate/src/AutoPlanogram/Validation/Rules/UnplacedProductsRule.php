<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\Rules;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementResult;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlanogramInput;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\ValidationResult;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\ValidationRuleInterface;
use Callcocam\LaravelRaptorPlannerate\Enums\ValidationSeverity;
use Illuminate\Support\Collection;

final class UnplacedProductsRule implements ValidationRuleInterface
{
    public function name(): string
    {
        return 'unplaced_products';
    }

    /**
     * @param  Collection<int, PlacedSegment>  $placedSegments
     * @return array<int, ValidationResult>
     */
    public function evaluate(Collection $placedSegments, PlanogramInput $input, PlacementResult $result): array
    {
        $results = [];

        foreach ($result->rejectedProducts as $rejection) {
            $product = $rejection['product'];
            $reason = $rejection['reason'];

            if ($product === null) {
                continue;
            }
            // Apenas regras explícitas (blocked/mandatory) são Error; espaço e dimensão são Warning
            $severity = $reason->isHardRule()
                ? ValidationSeverity::Error
                : ValidationSeverity::Warning;

            $results[] = new ValidationResult(
                ruleName: $this->name(),
                severity: $severity,
                message: sprintf(
                    'Produto "%s" não foi posicionado: %s',
                    $product->name,
                    $reason->label(),
                ),
                affectedProductIds: [$product->id],
            );
        }

        return $results;
    }
}
