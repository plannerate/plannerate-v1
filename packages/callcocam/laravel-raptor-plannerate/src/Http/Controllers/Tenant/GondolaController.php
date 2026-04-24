<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant;

use App\Support\Tenancy\InteractsWithTenantContext;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GondolaController extends Controller
{
    use InteractsWithTenantContext;

    public function index(Request $request, string $subdomain): Response
    {
        $this->authorize('viewAny', Gondola::class);

        $gondolas = Gondola::query()
            ->with(['planogram:id,name'])
            ->latest()
            ->paginate(15);

        return Inertia::render('tenant/gondolas/Index', [
            'subdomain' => $subdomain,
            'gondolas' => $gondolas,
            'filters' => ['search' => $request->query('search', '')],
        ]);
    }

    public function create(string $subdomain): Response
    {
        $this->authorize('create', Gondola::class);

        $planograms = Planogram::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('tenant/gondolas/Form', [
            'subdomain' => $subdomain,
            'gondola' => null,
            'planograms' => $planograms,
        ]);
    }

    public function store(Request $request, string $subdomain): RedirectResponse
    {
        $this->authorize('create', Gondola::class);

        $validated = $request->validate([
            'planogram_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'num_modulos' => ['nullable', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            'side' => ['nullable', 'string', 'max:255'],
            'flow' => ['nullable', 'in:left_to_right,right_to_left'],
            'alignment' => ['nullable', 'in:left,right,center,justify'],
            'scale_factor' => ['nullable', 'numeric', 'min:0.1'],
            'status' => ['nullable', 'in:draft,published'],
        ]);

        Gondola::query()->create([
            ...$validated,
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        return to_route('tenant.gondolas.index', $this->tenantRouteParameters())
            ->with('success', 'Gondola criada com sucesso.');
    }

    public function edit(string $subdomain, Gondola $gondola): Response
    {
        $this->authorize('update', $gondola);

        $planograms = Planogram::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('tenant/gondolas/Form', [
            'subdomain' => $subdomain,
            'gondola' => $gondola,
            'planograms' => $planograms,
        ]);
    }

    public function update(Request $request, string $subdomain, Gondola $gondola): RedirectResponse
    {
        $this->authorize('update', $gondola);

        $validated = $request->validate([
            'planogram_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'num_modulos' => ['nullable', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            'side' => ['nullable', 'string', 'max:255'],
            'flow' => ['nullable', 'in:left_to_right,right_to_left'],
            'alignment' => ['nullable', 'in:left,right,center,justify'],
            'scale_factor' => ['nullable', 'numeric', 'min:0.1'],
            'status' => ['nullable', 'in:draft,published'],
        ]);

        $gondola->update($validated);

        return to_route('tenant.gondolas.index', $this->tenantRouteParameters())
            ->with('success', 'Gondola atualizada com sucesso.');
    }

    public function destroy(string $subdomain, Gondola $gondola): RedirectResponse
    {
        $this->authorize('delete', $gondola);

        $gondola->delete();

        return to_route('tenant.gondolas.index', $this->tenantRouteParameters())
            ->with('success', 'Gondola excluída com sucesso.');
    }
}
