<?php

namespace App\Services\AutoPlanogram\Validation\Rules;

use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\DTO\PlacementResult;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\Validation\ValidationResult;
use App\Services\AutoPlanogram\Validation\ValidationRuleInterface;
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
