<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AdjacencyRuleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AdjacencyRuleRequest;
use App\Models\AdjacencyRule;
use App\Models\ScoringWeights;
use App\Services\AutoPlanogram\Scoring\ScoringWeightsValue;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdjacencyMatrixController extends Controller
{
    use InteractsWithTenantContext;

    public function edit(string $subdomain): Response
    {
        $model = ScoringWeights::first();
        $defaults = ScoringWeightsValue::default();
        $adjacencyLevel = (int) ($model?->adjacency_hierarchy_level ?? $defaults->adjacencyHierarchyLevel);

        return Inertia::render('settings/AdjacencyMatrix', [
            'adjacencyHierarchyLevel' => $adjacencyLevel,
            'rules' => AdjacencyRule::query()
                ->with(['source:id,name,full_path', 'target:id,name,full_path'])
                ->orderBy('source_category_id')
                ->orderBy('target_category_id')
                ->get()
                ->map(fn (AdjacencyRule $rule): array => [
                    'id' => $rule->id,
                    'source_category_id' => $rule->source_category_id,
                    'target_category_id' => $rule->target_category_id,
                    'source_label' => $rule->source?->full_path ?? $rule->source?->name ?? $rule->source_category_id,
                    'target_label' => $rule->target?->full_path ?? $rule->target?->name ?? $rule->target_category_id,
                    'rule_type' => $rule->rule_type?->value,
                    'rule_type_label' => $rule->rule_type?->label(),
                    'rule_type_color' => $rule->rule_type?->color(),
                    'weight' => (float) $rule->weight,
                    'reason' => $rule->reason,
                ])
                ->values(),
            'ruleTypes' => collect(AdjacencyRuleType::cases())
                ->map(fn (AdjacencyRuleType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'color' => $type->color(),
                    'default_weight' => $type->defaultWeight(),
                ])
                ->values(),
        ]);
    }

    public function store(AdjacencyRuleRequest $request, string $subdomain): RedirectResponse
    {
        unset($subdomain);

        AdjacencyRule::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.messages.adjacency_rule_created')]);

        return $this->toTenantRoute('tenant.adjacency-matrix.edit');
    }

    public function update(AdjacencyRuleRequest $request, string $subdomain, AdjacencyRule $adjacencyRule): RedirectResponse
    {
        unset($subdomain);

        $adjacencyRule->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.messages.adjacency_rule_updated')]);

        return $this->toTenantRoute('tenant.adjacency-matrix.edit');
    }

    public function destroy(string $subdomain, AdjacencyRule $adjacencyRule): RedirectResponse
    {
        unset($subdomain);

        $adjacencyRule->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.messages.adjacency_rule_deleted')]);

        return $this->toTenantRoute('tenant.adjacency-matrix.edit');
    }
}
