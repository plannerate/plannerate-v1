<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\InteractsWithTenantContext;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Scoring\ScoringWeightsValue;
use Callcocam\LaravelRaptorPlannerate\Models\ScoringWeights;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScoringWeightsController extends Controller
{
    use InteractsWithTenantContext;

    public function edit(): Response
    {
        $model = ScoringWeights::first();
        $defaults = ScoringWeightsValue::default();

        return Inertia::render('settings/ScoringWeights', [
            'weights' => [
                'w_giro' => $model?->w_giro ?? $defaults->giro,
                'w_margem' => $model?->w_margem ?? $defaults->margem,
                'w_estrategico' => $model?->w_estrategico ?? $defaults->estrategico,
                'w_doh' => $model?->w_doh ?? $defaults->doh,
                'w_crescimento' => $model?->w_crescimento ?? $defaults->crescimento,
                'sales_window_months' => $model?->sales_window_months ?? $defaults->salesWindowMonths,
                'block_hierarchy_level' => $model?->block_hierarchy_level ?? $defaults->blockHierarchyLevel,
                'adjacency_hierarchy_level' => $model?->adjacency_hierarchy_level ?? $defaults->adjacencyHierarchyLevel,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'w_giro' => ['required', 'numeric', 'min:0', 'max:1'],
            'w_margem' => ['required', 'numeric', 'min:0', 'max:1'],
            'w_estrategico' => ['required', 'numeric', 'min:0', 'max:1'],
            'w_doh' => ['required', 'numeric', 'min:0', 'max:1'],
            'w_crescimento' => ['required', 'numeric', 'min:0', 'max:1'],
            'sales_window_months' => ['required', 'integer', 'min:1', 'max:24'],
            'block_hierarchy_level' => ['required', 'integer', 'min:1', 'max:7'],
            'adjacency_hierarchy_level' => ['required', 'integer', 'min:1', 'max:7'],
        ]);

        ScoringWeights::updateOrCreate([], $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.messages.scoring_weights_updated')]);

        return $this->toTenantRoute('tenant.scoring-weights.edit');
    }
}
