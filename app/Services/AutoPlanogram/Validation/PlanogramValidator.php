<?php

namespace App\Services\AutoPlanogram\Validation;

use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\DTO\PlacementResult;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\DTO\ValidationReport;
use Illuminate\Support\Collection;

/**
 * Validador de planograma gerado.
 *
 * Executa uma série de regras de validação contra os segmentos posicionados
 * e retorna um relatório detalhado com erros, avisos e informações.
 */
final class PlanogramValidator
{
    /** @var array<int, ValidationRuleInterface> */
    private array $rules;

    /**
     * @param  array<int, ValidationRuleInterface>  $rules
     */
    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }

    /**
     * Add a validation rule to the validator.
     */
    public function addRule(ValidationRuleInterface $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * Validate placed segments and return a comprehensive report.
     *
     * @param  Collection<int, PlacedSegment>  $placedSegments
     */
    public function validate(Collection $placedSegments, PlanogramInput $input, PlacementResult $result): ValidationReport
    {
        $results = [];

        foreach ($this->rules as $rule) {
            $ruleResults = $rule->evaluate($placedSegments, $input, $result);
            $results = array_merge($results, $ruleResults);
        }

        return ValidationReport::fromResults($results);
    }
}
