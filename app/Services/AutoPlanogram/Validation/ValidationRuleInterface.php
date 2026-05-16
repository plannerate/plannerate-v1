<?php

namespace App\Services\AutoPlanogram\Validation;

use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use Illuminate\Support\Collection;

interface ValidationRuleInterface
{
    /**
     * Evaluate the rule against placed segments.
     *
     * @param  Collection<int, PlacedSegment>  $placedSegments
     * @return array<int, ValidationResult>
     */
    public function evaluate(Collection $placedSegments, PlanogramInput $input): array;

    /**
     * Get the rule name for identification.
     */
    public function name(): string;
}
