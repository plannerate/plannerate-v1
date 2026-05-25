<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ShelfLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ShelfLevelPreferenceRequest;
use App\Models\ShelfLevelPreference;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ShelfLevelPreferencesController extends Controller
{
    use InteractsWithTenantContext;

    public function edit(): Response
    {
        return Inertia::render('settings/ShelfLevelPreferences', [
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
            'shelfLevels' => collect(ShelfLevel::cases())
                ->map(fn (ShelfLevel $level): array => [
                    'value' => $level->value,
                    'label' => $level->label(),
                    'color' => $level->color(),
                ])
                ->values(),
        ]);
    }

    public function store(ShelfLevelPreferenceRequest $request): RedirectResponse
    {
        ShelfLevelPreference::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.messages.shelf_level_preference_created')]);

        return $this->toTenantRoute('tenant.shelf-level-preferences.edit');
    }

    public function update(ShelfLevelPreferenceRequest $request, ShelfLevelPreference $preference): RedirectResponse
    {
        $preference->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.messages.shelf_level_preference_updated')]);

        return $this->toTenantRoute('tenant.shelf-level-preferences.edit');
    }

    public function destroy(ShelfLevelPreference $preference): RedirectResponse
    {
        $preference->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.messages.shelf_level_preference_deleted')]);

        return $this->toTenantRoute('tenant.shelf-level-preferences.edit');
    }
}
