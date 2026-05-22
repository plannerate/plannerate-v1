<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\InteractsWithPlanLimits;
use App\Http\Controllers\Concerns\InteractsWithTrashedFilter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Http\Requests\Tenant\GondolaStoreRequest;
use App\Http\Requests\Tenant\GondolaUpdateRequest;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class GondolaController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithPlanLimits;
    use InteractsWithTenantContext;
    use InteractsWithTrashedFilter;

    public function index(Request $request, Planogram $planogram): Response
    {
        $this->authorize('viewAny', Gondola::class);
        $this->authorize('view', $planogram);
        $search = $this->requestString($request, 'search');
        $status = $this->requestEnum($request, 'status', ['draft', 'published']);
        $trashed = $this->resolveTrashedFilter($request);

        return $this->renderDeferredIndex('tenant/gondolas/Index', 'gondolas', fn (): LengthAwarePaginator => $this->gondolasPaginator(
            $planogram,
            $search,
            $status,
            $trashed,
            $this->resolvePerPage($request, 10),
        ), [
            'planogram' => [
                'id' => $planogram->id,
                'name' => $planogram->name,
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'trashed' => $trashed,
            ],
            'can' => $this->resolveCanCreate(Gondola::class, 'gondola_limit', Gondola::count()),
        ]);
    }

    private function gondolasPaginator(Planogram $planogram, string $search, string $status, string $trashed, int $perPage): LengthAwarePaginator
    {
        $query = Gondola::query();
        $this->applyTrashedToQuery($query, $trashed);

        return $query
            ->where('planogram_id', $planogram->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('location', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
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
    }

    public function create(Planogram $planogram): Response
    {
        $this->authorize('create', Gondola::class);
        $this->authorize('view', $planogram);

        return Inertia::render('tenant/gondolas/Form', [
            'planogram' => [
                'id' => $planogram->id,
                'name' => $planogram->name,
            ],
            'gondola' => null,
        ]);
    }

    public function store(GondolaStoreRequest $request, Planogram $planogram): RedirectResponse
    {
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

        return $this->toTenantRoute('tenant.catalog.gondolas.index', [
            'planogram' => $planogram->getKey(),
        ]);
    }

    public function edit(Planogram $planogram, Gondola $gondola): Response
    {
        $this->authorize('update', $gondola);
        $this->abortIfGondolaDoesNotBelongToPlanogram($gondola, $planogram);
        $this->authorize('view', $planogram);

        return Inertia::render('tenant/gondolas/Form', [
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

    public function update(GondolaUpdateRequest $request, Planogram $planogram, Gondola $gondola): RedirectResponse
    {
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

        return $this->toTenantRoute('tenant.catalog.gondolas.index', [
            'planogram' => $planogram->getKey(),
        ]);
    }

    public function destroy(Planogram $planogram, Gondola $gondola): RedirectResponse
    {
        $this->authorize('delete', $gondola);
        $this->abortIfGondolaDoesNotBelongToPlanogram($gondola, $planogram);
        $this->authorize('view', $planogram);

        $gondola->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.gondolas.messages.deleted'),
        ]);

        return $this->toTenantRoute('tenant.catalog.gondolas.index', [
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
