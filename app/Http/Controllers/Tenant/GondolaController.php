<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\GondolaStoreRequest;
use App\Http\Requests\Tenant\GondolaUpdateRequest;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class GondolaController extends Controller
{
    use InteractsWithTenantContext;

    public function index(Request $request, string $subdomain, Planogram $planogram): Response
    {
        unset($subdomain);
        $this->authorize('viewAny', Gondola::class);
        $this->authorize('view', $planogram);
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status'));
        $hasStatusFilter = in_array($status, ['draft', 'published'], true);

        $gondolas = Gondola::query()
            ->where('planogram_id', $planogram->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('location', 'like', '%'.$search.'%');
                });
            })
            ->when($hasStatusFilter, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($this->resolvePerPage($request, 10))
            ->withQueryString()
            ->through(fn (Gondola $gondola): array => [
                'id' => $gondola->id,
                'name' => $gondola->name,
                'slug' => $gondola->slug,
                'num_modulos' => $gondola->num_modulos,
                'location' => $gondola->location,
                'side' => $gondola->side,
                'flow' => $gondola->flow,
                'alignment' => $gondola->alignment,
                'scale_factor' => $gondola->scale_factor,
                'status' => $gondola->status,
                'created_at' => $gondola->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('tenant/gondolas/Index', [
            'subdomain' => $this->tenantSubdomain(),
            'planogram' => [
                'id' => $planogram->id,
                'name' => $planogram->name,
            ],
            'gondolas' => $gondolas,
            'filters' => [
                'search' => $search,
                'status' => $hasStatusFilter ? $status : '',
            ],
        ]);
    }

    public function create(string $subdomain, Planogram $planogram): Response
    {
        unset($subdomain);
        $this->authorize('create', Gondola::class);
        $this->authorize('view', $planogram);

        return Inertia::render('tenant/gondolas/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'planogram' => [
                'id' => $planogram->id,
                'name' => $planogram->name,
            ],
            'gondola' => null,
        ]);
    }

    public function store(GondolaStoreRequest $request, string $subdomain, Planogram $planogram): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('create', Gondola::class);
        $this->authorize('view', $planogram);

        $validated = $request->validated();

        Gondola::query()->create([
            ...$validated,
            'planogram_id' => $planogram->getKey(),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.gondolas.messages.created'),
        ]);

        return to_route('tenant.catalog.gondolas.index', [
            ...$this->tenantRouteParameters(),
            'planogram' => $planogram->getKey(),
        ]);
    }

    public function edit(string $subdomain, Planogram $planogram, Gondola $gondola): Response
    {
        unset($subdomain);
        $this->authorize('update', $gondola);
        $this->abortIfGondolaDoesNotBelongToPlanogram($gondola, $planogram);
        $this->authorize('view', $planogram);

        return Inertia::render('tenant/gondolas/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'planogram' => [
                'id' => $planogram->id,
                'name' => $planogram->name,
            ],
            'gondola' => [
                'id' => $gondola->id,
                'planogram_id' => $gondola->planogram_id,
                'linked_map_gondola_id' => $gondola->linked_map_gondola_id,
                'linked_map_gondola_category' => $gondola->linked_map_gondola_category,
                'name' => $gondola->name,
                'slug' => $gondola->slug,
                'num_modulos' => $gondola->num_modulos,
                'location' => $gondola->location,
                'side' => $gondola->side,
                'flow' => $gondola->flow,
                'alignment' => $gondola->alignment,
                'scale_factor' => $gondola->scale_factor,
                'status' => $gondola->status,
            ],
        ]);
    }

    public function update(GondolaUpdateRequest $request, string $subdomain, Planogram $planogram, Gondola $gondola): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $gondola);
        $this->abortIfGondolaDoesNotBelongToPlanogram($gondola, $planogram);
        $this->authorize('view', $planogram);

        $validated = $request->validated();

        $gondola->update([
            ...$validated,
            'planogram_id' => $gondola->planogram_id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.gondolas.messages.updated'),
        ]);

        return to_route('tenant.catalog.gondolas.index', [
            ...$this->tenantRouteParameters(),
            'planogram' => $planogram->getKey(),
        ]);
    }

    public function destroy(string $subdomain, Planogram $planogram, Gondola $gondola): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('delete', $gondola);
        $this->abortIfGondolaDoesNotBelongToPlanogram($gondola, $planogram);
        $this->authorize('view', $planogram);

        $gondola->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.gondolas.messages.deleted'),
        ]);

        return to_route('tenant.catalog.gondolas.index', [
            ...$this->tenantRouteParameters(),
            'planogram' => $planogram->getKey(),
        ]);
    }

    private function abortIfGondolaDoesNotBelongToPlanogram(Gondola $gondola, Planogram $planogram): void
    {
        if ($gondola->planogram_id !== $planogram->getKey()) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }
    }
}
