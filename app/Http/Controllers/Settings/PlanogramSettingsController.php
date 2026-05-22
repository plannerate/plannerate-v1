<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ShelfLevel;
use App\Http\Controllers\Controller;
use App\Models\ScoringWeights;
use App\Models\ShelfLevelPreference;
use App\Services\AutoPlanogram\Scoring\ScoringWeightsValue;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PlanogramSettingsController extends Controller
{
    use InteractsWithTenantContext;

    public function edit(string $subdomain): Response
    {
        $model = ScoringWeights::first();
        $defaults = ScoringWeightsValue::default();

        return Inertia::render('settings/PlanogramSettings', [
            'weights' => [
                'w_giro' => $model?->w_giro ?? $defaults->giro,
                'w_margem' => $model?->w_margem ?? $defaults->margem,
                'w_estrategico' => $model?->w_estrategico ?? $defaults->estrategico,
                'w_doh' => $model?->w_doh ?? $defaults->doh,
                'sales_window_months' => $model?->sales_window_months ?? $defaults->salesWindowMonths,
                'block_hierarchy_level' => $model?->block_hierarchy_level ?? $defaults->blockHierarchyLevel,
                'adjacency_hierarchy_level' => $model?->adjacency_hierarchy_level ?? $defaults->adjacencyHierarchyLevel,
                'vertical_block_threshold' => $model?->vertical_block_threshold ?? $defaults->verticalBlockThreshold,
                'vertical_block_min_shelves' => $model?->vertical_block_min_shelves ?? $defaults->verticalBlockMinShelves,
            ],
            'hierarchy_levels' => $this->hierarchyLevels(),
            'shelf_levels' => collect(ShelfLevel::cases())->map(fn (ShelfLevel $level): array => [
                'value' => $level->value,
                'label' => $level->label(),
                'color' => $level->color(),
            ])->values(),
            'preferences' => ShelfLevelPreference::query()
                ->with('category:id,name,full_path')
                ->orderByRaw('category_id IS NOT NULL')
                ->orderBy('created_at')
                ->get()
                ->map(fn (ShelfLevelPreference $pref): array => [
                    'id' => $pref->id,
                    'category_id' => $pref->category_id,
                    'category_label' => $pref->category?->full_path ?? $pref->category?->name,
                    'preferred_level' => $pref->preferred_level?->value,
                    'preferred_level_label' => $pref->preferred_level?->label(),
                    'preferred_level_color' => $pref->preferred_level?->color(),
                ])
                ->values(),
        ]);
    }

    public function update(Request $request, string $subdomain): RedirectResponse
    {
        unset($subdomain);

        $validated = $request->validate([
            'w_giro' => ['required', 'numeric', 'min:0', 'max:1'],
            'w_margem' => ['required', 'numeric', 'min:0', 'max:1'],
            'w_estrategico' => ['required', 'numeric', 'min:0', 'max:1'],
            'w_doh' => ['required', 'numeric', 'min:0', 'max:1'],
            'sales_window_months' => ['required', 'integer', 'min:1', 'max:24'],
            'block_hierarchy_level' => ['required', 'integer', 'min:2', 'max:7', 'gte:adjacency_hierarchy_level'],
            'adjacency_hierarchy_level' => ['required', 'integer', 'min:2', 'max:7'],
            'vertical_block_threshold' => ['required', 'numeric', 'min:0.05', 'max:0.50'],
            'vertical_block_min_shelves' => ['required', 'integer', 'min:2', 'max:4'],
        ]);

        ScoringWeights::updateOrCreate([], $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.messages.planogram_settings_updated')]);

        return $this->toTenantRoute('tenant.planogram-settings.edit');
    }

    /** @return array<int, array{value: int, label: string, note: string}> */
    private function hierarchyLevels(): array
    {
        return [
            ['value' => 2, 'label' => 'Departamento',    'note' => 'Muito amplo — poucos blocos grandes'],
            ['value' => 3, 'label' => 'Subdepartamento', 'note' => 'Amplo'],
            ['value' => 4, 'label' => 'Categoria',       'note' => 'Padrão de mercado — recomendado para adjacência'],
            ['value' => 5, 'label' => 'Subcategoria',    'note' => 'Padrão de mercado — recomendado para agrupamento'],
            ['value' => 6, 'label' => 'Segmento',        'note' => 'Granular — requer dados preenchidos até este nível'],
            ['value' => 7, 'label' => 'Subsegmento',     'note' => 'Muito granular — raramente recomendado'],
        ];
    }
}
