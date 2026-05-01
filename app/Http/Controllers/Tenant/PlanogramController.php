<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\InteractsWithTrashedFilter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Http\Requests\Tenant\PlanogramStoreRequest;
use App\Http\Requests\Tenant\PlanogramUpdateRequest;
use App\Models\Cluster;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\Store;
use App\Models\WorkflowGondolaExecution;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PlanogramController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;
    use InteractsWithTrashedFilter;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Planogram::class);

        $search = $this->requestString($request, 'search');
        $status = $this->requestEnum($request, 'status', ['draft', 'published']);
        $type = $this->requestEnum($request, 'type', ['realograma', 'planograma']);
        $storeId = $this->requestString($request, 'store_id');
        $categoryId = $this->requestString($request, 'category_id');
        $trashed = $this->resolveTrashedFilter($request);

        return $this->renderDeferredIndex('tenant/planograms/Index', 'planograms', fn (): LengthAwarePaginator => $this->planogramsPaginator(
            $search,
            $status,
            $type,
            $storeId,
            $categoryId,
            $trashed,
            $this->resolvePerPage($request, 10),
        ), [
            'subdomain' => $this->tenantSubdomain(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'type' => $type,
                'store_id' => $storeId,
                'category_id' => $categoryId,
                'trashed' => $trashed,
            ],
            'filter_options' => [
                'stores' => $this->storesForSelect(),
            ],
        ]);
    }

    private function planogramsPaginator(
        string $search,
        string $status,
        string $type,
        string $storeId,
        string $categoryId,
        string $trashed,
        int $perPage,
    ): LengthAwarePaginator {
        $query = Planogram::query();
        $this->applyTrashedToQuery($query, $trashed);

        return $query
            ->with(['store:id,name', 'cluster:id,name', 'category:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($storeId !== '', fn ($query) => $query->where('store_id', $storeId))
            ->when($categoryId !== '', fn ($query) => $query->where('category_id', $categoryId))
            ->latest()
            ->paginate($perPage)
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

    public function kanban(): RedirectResponse
    {
        return to_route('tenant.kanban.index', $this->tenantRouteParameters());
    }

    public function maps(Request $request): Response
    {
        $this->authorize('viewAny', Planogram::class);

        $search = $this->requestString($request, 'search');
        $storeId = $this->requestString($request, 'store_id');
        $status = $this->requestEnum($request, 'status', ['all', 'clickable', 'pending', 'blocked']);
        $onlyEditable = $request->boolean('only_editable');

        return Inertia::render('tenant/planograms/Maps', [
            'subdomain' => $this->tenantSubdomain(),
            'store_maps' => $this->storeMaps($search, $storeId, $status, $onlyEditable),
            'filters' => [
                'search' => $search,
                'store_id' => $storeId,
                'status' => $status === '' ? 'all' : $status,
                'only_editable' => $onlyEditable,
            ],
            'filter_options' => [
                'stores' => $this->mapStoresForSelect(),
            ],
        ]);
    }

    public function orphanLayers(Request $request): Response
    {
        $this->authorize('viewAny', Planogram::class);

        $search = $this->requestString($request, 'search');
        $perPage = $this->resolvePerPage($request, 10);
        $tenantId = (string) $this->tenantId();
        $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));

        $orphans = DB::connection($tenantConnectionName)->table('layers as l')
            ->leftJoin('products as p', function ($join) use ($tenantId): void {
                $join->on('p.id', '=', 'l.product_id')
                    ->where('p.tenant_id', '=', $tenantId)
                    ->whereNull('p.deleted_at');
            })
            ->leftJoin('products as p_all', function ($join) use ($tenantId): void {
                $join->on('p_all.id', '=', 'l.product_id')
                    ->where('p_all.tenant_id', '=', $tenantId);
            })
            ->where('l.tenant_id', $tenantId)
            ->whereNotNull('l.product_id')
            ->whereNull('l.deleted_at')
            ->whereNull('p.id')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where->where('l.id', 'like', '%'.$search.'%')
                        ->orWhere('l.segment_id', 'like', '%'.$search.'%')
                        ->orWhere('l.product_id', 'like', '%'.$search.'%')
                        ->orWhere('p_all.ean', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('l.updated_at')
            ->orderByDesc('l.id')
            ->select([
                'l.id as layer_id',
                'l.segment_id',
                'l.product_id',
                'p_all.ean',
                'l.updated_at',
            ])
            ->paginate($perPage)
            ->withQueryString()
            ->through(static fn (object $row): array => [
                'layer_id' => (string) $row->layer_id,
                'segment_id' => (string) $row->segment_id,
                'product_id_atual' => (string) $row->product_id,
                'ean' => is_string($row->ean) ? $row->ean : null,
                'updated_at' => is_string($row->updated_at) ? $row->updated_at : null,
            ]);

        return Inertia::render('tenant/planograms/OrphanLayers', [
            'subdomain' => $this->tenantSubdomain(),
            'orphans' => $orphans,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function storeMaps(string $search = '', string $storeId = '', string $status = 'all', bool $onlyEditable = false): array
    {
        $stores = Store::query()
            ->whereNotNull('map_image_path')
            ->whereNotNull('map_regions')
            ->orderBy('name')
            ->get(['id', 'name', 'map_image_path', 'map_regions']);

        $regionIds = $stores
            ->pluck('map_regions')
            ->flatten(1)
            ->pluck('id')
            ->filter(fn (mixed $regionId): bool => is_string($regionId) && $regionId !== '')
            ->unique()
            ->values();

        $gondolas = Gondola::query()
            ->with('planogram:id,store_id')
            ->whereIn('linked_map_gondola_id', $regionIds->all())
            ->get(['id', 'name', 'planogram_id', 'linked_map_gondola_id'])
            ->keyBy('linked_map_gondola_id');

        $gondolaIds = $gondolas
            ->pluck('id')
            ->filter(fn (mixed $gondolaId): bool => is_string($gondolaId) && $gondolaId !== '')
            ->values();

        $activeExecutions = WorkflowGondolaExecution::query()
            ->whereIn('gondola_id', $gondolaIds->all())
            ->where('status', 'active')
            ->orderByDesc('started_at')
            ->get(['id', 'gondola_id', 'execution_started_by', 'status'])
            ->groupBy('gondola_id')
            ->map(fn ($executions) => $executions->first());

        $normalizedStatus = in_array($status, ['all', 'clickable', 'pending', 'blocked'], true) ? $status : 'all';
        $normalizedSearch = mb_strtolower(trim($search));

        return $stores
            ->map(function (Store $store) use ($gondolas, $activeExecutions): array {
                $regions = collect($store->map_regions ?? [])
                    ->filter(fn (mixed $region): bool => is_array($region))
                    ->map(function (array $region) use ($gondolas, $activeExecutions): array {
                        $regionId = is_string($region['id'] ?? null) ? $region['id'] : null;
                        $gondola = $regionId ? $gondolas->get($regionId) : null;
                        $execution = $gondola ? $activeExecutions->get($gondola->id) : null;
                        $user = request()->user();
                        $canUpdateGondola = $gondola && $user ? $user->can('update', $gondola) : false;
                        $canViewGondola = $gondola && $user ? $user->can('view', $gondola) : false;
                        $canOpenEditor = $canUpdateGondola && $execution !== null;

                        return [
                            'id' => (string) ($region['id'] ?? ''),
                            'label' => is_string($region['label'] ?? null) ? $region['label'] : null,
                            'x' => (int) ($region['x'] ?? 0),
                            'y' => (int) ($region['y'] ?? 0),
                            'width' => max(20, (int) ($region['width'] ?? 20)),
                            'height' => max(20, (int) ($region['height'] ?? 20)),
                            'shape' => in_array($region['shape'] ?? 'rectangle', ['rectangle', 'circle'], true)
                                ? $region['shape']
                                : 'rectangle',
                            'gondola' => $gondola ? [
                                'id' => $gondola->id,
                                'name' => $gondola->name,
                                'execution_id' => $execution?->id,
                                'execution_started' => $execution !== null,
                                'can_open_editor' => $canOpenEditor,
                                'can_view' => $canViewGondola,
                            ] : null,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'can_edit_store' => request()->user()?->can('update', $store) ?? false,
                    'map_image_url' => $store->map_image_path ? Storage::disk('public')->url($store->map_image_path) : null,
                    'regions' => $regions,
                ];
            })
            ->filter(function (array $store) use ($normalizedSearch, $storeId, $normalizedStatus, $onlyEditable): bool {
                if ($store['map_image_url'] === null || count($store['regions']) === 0) {
                    return false;
                }

                if ($normalizedSearch !== '' && ! str_contains(mb_strtolower((string) $store['name']), $normalizedSearch)) {
                    return false;
                }

                if ($storeId !== '' && (string) $store['id'] !== $storeId) {
                    return false;
                }

                if ($onlyEditable && ! $store['can_edit_store']) {
                    return false;
                }

                if ($normalizedStatus === 'clickable') {
                    return collect($store['regions'])->contains(function (array $region): bool {
                        return (bool) data_get($region, 'gondola.execution_started')
                            && ((bool) data_get($region, 'gondola.can_open_editor') || (bool) data_get($region, 'gondola.can_view'));
                    });
                }

                if ($normalizedStatus === 'pending') {
                    return collect($store['regions'])->contains(fn (array $region): bool => (bool) data_get($region, 'gondola') && ! (bool) data_get($region, 'gondola.execution_started'));
                }

                if ($normalizedStatus === 'blocked') {
                    return collect($store['regions'])->contains(function (array $region): bool {
                        if (! (bool) data_get($region, 'gondola.execution_started')) {
                            return false;
                        }

                        return ! (bool) data_get($region, 'gondola.can_open_editor')
                            && ! (bool) data_get($region, 'gondola.can_view');
                    });
                }

                return true;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function mapStoresForSelect(): array
    {
        return Store::query()
            ->whereNotNull('map_image_path')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Store $store): array => [
                'id' => $store->id,
                'name' => $store->name,
            ])
            ->all();
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
