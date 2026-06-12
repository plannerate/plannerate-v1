<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation;

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementResult;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlanogramInput;
use Illuminate\Support\Collection;

interface ValidationRuleInterface
{
    /**
     * Evaluate the rule against placed segments.
     *
     * @param  Collection<int, PlacedSegment>  $placedSegments
     * @return array<int, ValidationResult>
     */
    public function evaluate(Collection $placedSegments, PlanogramInput $input, PlacementResult $result): array;

    /**
     * Get the rule name for identification.
     */
    public function name(): string;
}
