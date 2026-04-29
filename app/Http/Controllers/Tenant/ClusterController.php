<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Http\Requests\Tenant\ClusterStoreRequest;
use App\Http\Requests\Tenant\ClusterUpdateRequest;
use App\Models\Cluster;
use App\Models\Store;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClusterController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Cluster::class);

        $search = $this->requestString($request, 'search');
        $status = $this->requestEnum($request, 'status', ['draft', 'published']);
        $storeId = $this->requestString($request, 'store_id');

        return $this->renderDeferredIndex('tenant/clusters/Index', 'clusters', fn (): LengthAwarePaginator => $this->clustersPaginator(
            $search,
            $status,
            $storeId,
            $this->resolvePerPage($request, 10),
        ), [
            'subdomain' => $this->tenantSubdomain(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'store_id' => $storeId,
            ],
            'filter_options' => [
                'stores' => $this->storesForSelect(),
            ],
        ]);
    }

    private function clustersPaginator(string $search, string $status, string $storeId, int $perPage): LengthAwarePaginator
    {
        return Cluster::query()
            ->with(['store:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('specification_1', 'like', '%'.$search.'%')
                        ->orWhere('specification_2', 'like', '%'.$search.'%')
                        ->orWhere('specification_3', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($storeId !== '', fn ($query) => $query->where('store_id', $storeId))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Cluster $cluster): array => [
                'id' => $cluster->id,
                'store_id' => $cluster->store_id,
                'store' => $cluster->store?->name,
                'name' => $cluster->name,
                'slug' => $cluster->slug,
                'specification_1' => $cluster->specification_1,
                'status' => $cluster->status,
                'created_at' => $cluster->created_at?->toDateTimeString(),
            ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Cluster::class);

        return Inertia::render('tenant/clusters/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'cluster' => null,
            'stores' => $this->storesForSelect(),
        ]);
    }

    public function store(ClusterStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Cluster::class);

        Cluster::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.clusters.messages.created'),
        ]);

        return to_route('tenant.clusters.index', $this->tenantRouteParameters());
    }

    public function edit(string $subdomain, Cluster $cluster): Response
    {
        unset($subdomain);
        $this->authorize('update', $cluster);

        return Inertia::render('tenant/clusters/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'cluster' => [
                'id' => $cluster->id,
                'store_id' => $cluster->store_id,
                'name' => $cluster->name,
                'specification_1' => $cluster->specification_1,
                'specification_2' => $cluster->specification_2,
                'specification_3' => $cluster->specification_3,
                'slug' => $cluster->slug,
                'status' => $cluster->status,
                'description' => $cluster->description,
            ],
            'stores' => $this->storesForSelect(),
        ]);
    }

    public function update(ClusterUpdateRequest $request, string $subdomain, Cluster $cluster): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $cluster);

        $cluster->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.clusters.messages.updated'),
        ]);

        return to_route('tenant.clusters.index', $this->tenantRouteParameters());
    }

    public function destroy(string $subdomain, Cluster $cluster): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('delete', $cluster);

        $cluster->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.clusters.messages.deleted'),
        ]);

        return to_route('tenant.clusters.index', $this->tenantRouteParameters());
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function storesForSelect(): array
    {
        return Store::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Store $store): array => [
                'id' => $store->id,
                'name' => $store->name,
            ])
            ->all();
    }
}
