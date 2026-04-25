<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\PlanogramStoreRequest;
use App\Http\Requests\Tenant\PlanogramUpdateRequest;
use App\Models\Cluster;
use App\Models\Planogram;
use App\Models\Store;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanogramController extends Controller
{
    use InteractsWithTenantContext;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Planogram::class);

        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status'));
        $type = trim((string) $request->string('type'));
        $storeId = trim((string) $request->string('store_id'));
        $categoryId = trim((string) $request->string('category_id'));
        $hasStatusFilter = in_array($status, ['draft', 'published'], true);
        $hasTypeFilter = in_array($type, ['realograma', 'planograma'], true);
        $hasStoreFilter = $storeId !== '';
        $hasCategoryFilter = $categoryId !== '';

        $planograms = Planogram::query()
            ->with(['store:id,name', 'cluster:id,name', 'category:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->when($hasStatusFilter, fn ($query) => $query->where('status', $status))
            ->when($hasTypeFilter, fn ($query) => $query->where('type', $type))
            ->when($hasStoreFilter, fn ($query) => $query->where('store_id', $storeId))
            ->when($hasCategoryFilter, fn ($query) => $query->where('category_id', $categoryId))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Planogram $planogram): array => [
                'id' => $planogram->id,
                'name' => $planogram->name,
                'slug' => $planogram->slug,
                'type' => $planogram->type,
                'store' => $planogram->store?->name,
                'cluster' => $planogram->cluster?->name,
                'category' => $planogram->category?->name,
                'start_date' => $planogram->start_date?->toDateString(),
                'end_date' => $planogram->end_date?->toDateString(),
                'status' => $planogram->status,
                'created_at' => $planogram->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('tenant/planograms/Index', [
            'subdomain' => $this->tenantSubdomain(),
            'planograms' => $planograms,
            'filters' => [
                'search' => $search,
                'status' => $hasStatusFilter ? $status : '',
                'type' => $hasTypeFilter ? $type : '',
                'store_id' => $hasStoreFilter ? $storeId : '',
                'category_id' => $hasCategoryFilter ? $categoryId : '',
            ],
            'filter_options' => [
                'stores' => $this->storesForSelect(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Planogram::class);

        return Inertia::render('tenant/planograms/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'planogram' => null,
            'stores' => $this->storesForSelect(),
            'clusters' => $this->clustersForSelect(),
        ]);
    }

    public function store(PlanogramStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Planogram::class);

        Planogram::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.planograms.messages.created'),
        ]);

        return to_route('tenant.planograms.index', $this->tenantRouteParameters());
    }

    public function edit(string $subdomain, Planogram $planogram): Response
    {
        unset($subdomain);
        $this->authorize('update', $planogram);

        return Inertia::render('tenant/planograms/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'planogram' => [
                'id' => $planogram->id,
                'template_id' => $planogram->template_id,
                'store_id' => $planogram->store_id,
                'cluster_id' => $planogram->cluster_id,
                'name' => $planogram->name,
                'slug' => $planogram->slug,
                'type' => $planogram->type,
                'category_id' => $planogram->category_id,
                'start_date' => $planogram->start_date?->toDateString(),
                'end_date' => $planogram->end_date?->toDateString(),
                'order' => $planogram->order,
                'description' => $planogram->description,
                'status' => $planogram->status,
            ],
            'stores' => $this->storesForSelect(),
            'clusters' => $this->clustersForSelect(),
        ]);
    }

    public function update(PlanogramUpdateRequest $request, string $subdomain, Planogram $planogram): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogram);

        $planogram->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.planograms.messages.updated'),
        ]);

        return to_route('tenant.planograms.index', $this->tenantRouteParameters());
    }

    public function destroy(string $subdomain, Planogram $planogram): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('delete', $planogram);

        $planogram->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.planograms.messages.deleted'),
        ]);

        return to_route('tenant.planograms.index', $this->tenantRouteParameters());
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

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function clustersForSelect(): array
    {
        return Cluster::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Cluster $cluster): array => [
                'id' => $cluster->id,
                'name' => $cluster->name,
            ])
            ->all();
    }
}
