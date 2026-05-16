<?php

namespace App\Services\AutoPlanogram\Adjacency;

use App\Enums\AdjacencyRuleType;
use App\Models\AdjacencyRule;

final class AdjacencyMatrix
{
    /**
     * @param  array<string, array<string, array{type: AdjacencyRuleType, weight: float}>>  $rules
     */
    public function __construct(private array $rules) {}

    public static function loadForTenant(string $tenantId): self
    {
        $indexed = [];

        AdjacencyRule::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->get()
            ->each(function (AdjacencyRule $rule) use (&$indexed): void {
                $payload = [
                    'type' => $rule->rule_type,
                    'weight' => (float) $rule->weight,
                ];

                $indexed[$rule->source_category_id][$rule->target_category_id] = $payload;
                $indexed[$rule->target_category_id][$rule->source_category_id] = $payload;
            });

        return new self($indexed);
    }

    public function weightBetween(?string $left, ?string $right): float
    {
        if ($left === null || $right === null || $left === $right) {
            return 0.0;
        }

        return $this->rules[$left][$right]['weight'] ?? 0.0;
    }

    public function isForbidden(?string $left, ?string $right): bool
    {
        if ($left === null || $right === null || $left === $right) {
            return false;
        }

        return ($this->rules[$left][$right]['type'] ?? null) === AdjacencyRuleType::MustAvoid;
    }
}
