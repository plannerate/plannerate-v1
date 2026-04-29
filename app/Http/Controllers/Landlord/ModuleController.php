<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreModuleRequest;
use App\Http\Requests\Landlord\UpdateModuleRequest;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    /**
     * Display a listing of modules.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Module::class);

        $search = trim((string) $request->string('search'));
        $isActive = $request->query('is_active');
        $hasIsActiveFilter = in_array($isActive, ['0', '1'], true);

        $modules = Module::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->when($hasIsActiveFilter, fn ($query) => $query->where('is_active', $isActive === '1'))
            ->withCount('tenants')
            ->latest()
            ->paginate($this->resolvePerPage($request, 10))
            ->withQueryString()
            ->through(fn (Module $module): array => [
                'id' => $module->id,
                'name' => $module->name,
                'slug' => $module->slug,
                'description' => $module->description,
                'is_active' => $module->is_active,
                'tenants_count' => $module->tenants_count,
                'created_at' => $module->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('landlord/modules/Index', [
            'modules' => $modules,
            'filters' => [
                'search' => $search,
                'is_active' => $hasIsActiveFilter ? $isActive : '',
            ],
        ]);
    }

    /**
     * Show the form for creating a module.
     */
    public function create(): Response
    {
        $this->authorize('create', Module::class);

        return Inertia::render('landlord/modules/Form', [
            'module' => null,
        ]);
    }

    /**
     * Store a newly created module.
     */
    public function store(StoreModuleRequest $request): RedirectResponse
    {
        $this->authorize('create', Module::class);

        Module::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.modules.messages.created'),
        ]);

        return to_route('landlord.modules.index');
    }

    /**
     * Show the form for editing the specified module.
     */
    public function edit(Module $module): Response
    {
        $this->authorize('update', $module);

        return Inertia::render('landlord/modules/Form', [
            'module' => [
                'id' => $module->id,
                'name' => $module->name,
                'slug' => $module->slug,
                'description' => $module->description,
                'is_active' => $module->is_active,
            ],
        ]);
    }

    /**
     * Update the specified module.
     */
    public function update(UpdateModuleRequest $request, Module $module): RedirectResponse
    {
        $this->authorize('update', $module);

        $module->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.modules.messages.updated'),
        ]);

        return to_route('landlord.modules.index');
    }

    /**
     * Remove the specified module.
     */
    public function destroy(Module $module): RedirectResponse
    {
        $this->authorize('delete', $module);

        if ($module->tenants()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('app.landlord.modules.messages.in_use'),
            ]);

            return back();
        }

        $module->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.modules.messages.deleted'),
        ]);

        return to_route('landlord.modules.index');
    }
}
