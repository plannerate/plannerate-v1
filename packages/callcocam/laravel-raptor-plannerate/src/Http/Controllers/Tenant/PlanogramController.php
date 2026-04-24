<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant;

use App\Support\Tenancy\InteractsWithTenantContext;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanogramController extends Controller
{
    use InteractsWithTenantContext;

    public function index(Request $request, string $subdomain): Response
    {
        $this->authorize('viewAny', Planogram::class);

        $planograms = Planogram::query()
            ->with(['store:id,name', 'category:id,name'])
            ->latest()
            ->paginate(15);

        return Inertia::render('tenant/planograms/Index', [
            'subdomain' => $subdomain,
            'planograms' => $planograms,
            'filters' => ['search' => $request->query('search', '')],
        ]);
    }

    public function create(string $subdomain): Response
    {
        $this->authorize('create', Planogram::class);

        $stores = Store::query()->orderBy('name')->get(['id', 'name']);
        $categories = Category::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('tenant/planograms/Form', [
            'subdomain' => $subdomain,
            'planogram' => null,
            'stores' => $stores,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request, string $subdomain): RedirectResponse
    {
        $this->authorize('create', Planogram::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'store_id' => ['nullable', 'string'],
            'category_id' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:draft,published'],
            'description' => ['nullable', 'string'],
        ]);

        Planogram::query()->create([
            ...$validated,
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        return to_route('tenant.planograms.index', $this->tenantRouteParameters())
            ->with('success', 'Planograma criado com sucesso.');
    }

    public function edit(string $subdomain, Planogram $planogram): Response
    {
        $this->authorize('update', $planogram);

        $stores = Store::query()->orderBy('name')->get(['id', 'name']);
        $categories = Category::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('tenant/planograms/Form', [
            'subdomain' => $subdomain,
            'planogram' => $planogram,
            'stores' => $stores,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, string $subdomain, Planogram $planogram): RedirectResponse
    {
        $this->authorize('update', $planogram);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'store_id' => ['nullable', 'string'],
            'category_id' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:draft,published'],
            'description' => ['nullable', 'string'],
        ]);

        $planogram->update($validated);

        return to_route('tenant.planograms.index', $this->tenantRouteParameters())
            ->with('success', 'Planograma atualizado com sucesso.');
    }

    public function destroy(string $subdomain, Planogram $planogram): RedirectResponse
    {
        $this->authorize('delete', $planogram);

        $planogram->delete();

        return to_route('tenant.planograms.index', $this->tenantRouteParameters())
            ->with('success', 'Planograma excluído com sucesso.');
    }
}
