<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\InteractsWithPlanLimits;
use App\Http\Controllers\Concerns\InteractsWithTrashedFilter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Http\Requests\Tenant\SimilarGroupStoreRequest;
use App\Http\Requests\Tenant\SimilarGroupUpdateRequest;
use App\Models\SimilarGroup;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SimilarGroupController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithPlanLimits;
    use InteractsWithTenantContext;
    use InteractsWithTrashedFilter;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SimilarGroup::class);

        $search = $this->requestString($request, 'search');
        $status = $this->requestEnum($request, 'status', ['draft', 'published']);
        $trashed = $this->resolveTrashedFilter($request);

        return $this->renderDeferredIndex('tenant/similar-groups/Index', 'similarGroups', fn (): LengthAwarePaginator => $this->similarGroupsPaginator(
            $search,
            $status,
            $trashed,
            $this->resolvePerPage($request, 10),
        ), [
            'subdomain' => $this->tenantSubdomain(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'trashed' => $trashed,
            ],
            'can' => $this->resolveCanCreate(SimilarGroup::class, 'similar_groups_limit', SimilarGroup::count()),
        ]);
    }

    private function similarGroupsPaginator(string $search, string $status, string $trashed, int $perPage): LengthAwarePaginator
    {
        $query = SimilarGroup::query();
        $this->applyTrashedToQuery($query, $trashed);

        return $query
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('grouper_code', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SimilarGroup $group): array => [
                'id' => $group->id,
                'grouper_code' => $group->grouper_code,
                'name' => $group->name,
                'product_codes' => $group->product_codes ?? [],
                'status' => $group->status,
                'created_at' => $group->created_at?->toDateTimeString(),
            ]);
    }

    public function create(): Response
    {
        $this->authorize('create', SimilarGroup::class);

        return Inertia::render('tenant/similar-groups/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'similarGroup' => null,
        ]);
    }

    public function store(SimilarGroupStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', SimilarGroup::class);

        SimilarGroup::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Grupo de similares criado com sucesso.',
        ]);

        return to_route('tenant.similar-groups.index', $this->tenantRouteParameters());
    }

    public function edit(string $subdomain, SimilarGroup $similarGroup): Response
    {
        unset($subdomain);
        $this->authorize('update', $similarGroup);

        return Inertia::render('tenant/similar-groups/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'similarGroup' => [
                'id' => $similarGroup->id,
                'grouper_code' => $similarGroup->grouper_code,
                'name' => $similarGroup->name,
                'product_codes' => $similarGroup->product_codes ?? [],
                'status' => $similarGroup->status,
                'description' => $similarGroup->description,
            ],
        ]);
    }

    public function update(SimilarGroupUpdateRequest $request, string $subdomain, SimilarGroup $similarGroup): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $similarGroup);

        $similarGroup->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Grupo de similares atualizado com sucesso.',
        ]);

        return to_route('tenant.similar-groups.index', $this->tenantRouteParameters());
    }

    public function destroy(string $subdomain, SimilarGroup $similarGroup): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('delete', $similarGroup);

        $similarGroup->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Grupo de similares excluído com sucesso.',
        ]);

        return to_route('tenant.similar-groups.index', $this->tenantRouteParameters());
    }
}
