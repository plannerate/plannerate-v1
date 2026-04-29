<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\InteractsWithDeferredIndex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreTenantRequest;
use App\Http\Requests\Landlord\UpdateTenantRequest;
use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Modules\ModuleSlug;
use App\Support\Modules\TenantModuleService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    use InteractsWithDeferredIndex;

    /**
     * @var list<string>
     */
    private const AVAILABLE_STATUSES = ['provisioning', 'active', 'suspended', 'inactive'];

    public function __construct(
        private readonly TenantModuleService $tenantModuleService,
    ) {}

    /**
     * Display a listing of tenants.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Tenant::class);

        $search = $this->requestString($request, 'search');
        $status = $this->requestEnum($request, 'status', self::AVAILABLE_STATUSES);
        $planId = $this->requestString($request, 'plan_id');
        $module = $this->requestString($request, 'module');

        return $this->renderDeferredIndex('landlord/tenants/Index', 'tenants', fn (): LengthAwarePaginator => $this->tenantsPaginator(
            $search,
            $status,
            $planId,
            $module,
            $this->resolvePerPage($request, 10),
        ), [
            'filters' => [
                'search' => $search,
                'status' => $status,
                'plan_id' => $planId,
                'module' => $module,
            ],
            'filter_options' => [
                'statuses' => $this->statusesForSelect(),
                'plans' => Plan::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Plan $plan): array => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                    ])
                    ->all(),
                'modules' => $this->modulesForFilter(),
            ],
        ]);
    }

    private function tenantsPaginator(
        string $search,
        string $status,
        string $planId,
        string $module,
        int $perPage,
    ): LengthAwarePaginator {
        return Tenant::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('database', 'like', '%'.$search.'%')
                        ->orWhereHas('primaryDomain', fn ($domainQuery) => $domainQuery->where('host', 'like', '%'.$search.'%'));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($planId !== '', fn ($query) => $query->where('plan_id', $planId))
            ->when($module !== '', fn ($query) => $query->whereHasActiveModule($module))
            ->with(['plan:id,name', 'primaryDomain:id,tenant_id,host,is_active', 'modules:id,slug,is_active'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Tenant $tenant): array => [
                'active_modules' => $this->tenantModuleService->tenantActiveModuleSlugs($tenant),
                'has_kanban' => $this->tenantModuleService->tenantHasActiveModule($tenant, ModuleSlug::KANBAN),
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'database' => $tenant->database,
                'status' => $tenant->status,
                'plan' => $tenant->plan ? [
                    'id' => $tenant->plan->id,
                    'name' => $tenant->plan->name,
                ] : null,
                'primary_domain' => $tenant->primaryDomain ? [
                    'id' => $tenant->primaryDomain->id,
                    'host' => $tenant->primaryDomain->host,
                    'is_active' => $tenant->primaryDomain->is_active,
                ] : null,
                'created_at' => $tenant->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * Show the form for creating a tenant.
     */
    public function create(): Response
    {
        $this->authorize('create', Tenant::class);

        return Inertia::render('landlord/tenants/Form', [
            'tenant' => null,
            'plans' => $this->plansForSelect(),
            'modules' => $this->modulesForSelect(),
            'statuses' => $this->statusesForSelect(),
        ]);
    }

    /**
     * Store a newly created tenant.
     */
    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $this->authorize('create', Tenant::class);

        $validated = $request->validated();
        $moduleIds = $validated['module_ids'] ?? [];

        DB::connection('landlord')->transaction(function () use ($validated, $moduleIds): void {
            $tenant = Tenant::query()->create(Arr::except($validated, ['host', 'domain_is_active', 'module_ids']));

            $tenant->domains()->create([
                'tenant_id' => $tenant->id,
                'host' => $validated['host'],
                'type' => 'subdomain',
                'is_primary' => true,
                'is_active' => (bool) ($validated['domain_is_active'] ?? true),
            ]);

            $tenant->modules()->sync($moduleIds);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenants.messages.created'),
        ]);

        $createdTenant = Tenant::query()->where('slug', $validated['slug'])->first();

        return to_route('landlord.tenants.setup', $createdTenant);
    }

    /**
     * Show the form for editing the specified tenant.
     */
    public function edit(Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        $tenant->load(['primaryDomain:id,tenant_id,host,is_active', 'modules:id,name']);

        return Inertia::render('landlord/tenants/Form', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'database' => $tenant->database,
                'status' => $tenant->status,
                'provisioning_error' => $tenant->provisioning_error,
                'plan_id' => $tenant->plan_id,
                'module_ids' => $tenant->modules->pluck('id')->values()->all(),
                'host' => $tenant->primaryDomain?->host,
                'domain_is_active' => $tenant->primaryDomain?->is_active ?? true,
            ],
            'plans' => $this->plansForSelect(),
            'modules' => $this->modulesForSelect(),
            'statuses' => $this->statusesForSelect(),
        ]);
    }

    /**
     * Update the specified tenant.
     */
    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validated();
        $moduleIds = $validated['module_ids'] ?? [];

        DB::connection('landlord')->transaction(function () use ($tenant, $validated, $moduleIds): void {
            $tenant->update(Arr::except($validated, ['host', 'domain_is_active', 'module_ids']));

            $tenant->domains()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'host' => $validated['host'],
                    'type' => 'subdomain',
                    'is_primary' => true,
                    'is_active' => (bool) ($validated['domain_is_active'] ?? true),
                ],
            );

            $tenant->modules()->sync($moduleIds);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenants.messages.updated'),
        ]);

        return to_route('landlord.tenants.index');
    }

    /**
     * Show the setup/provisioning status page for a tenant.
     */
    public function setup(Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        $tenant->load(['primaryDomain:id,tenant_id,host,is_active', 'plan:id,name']);

        return Inertia::render('landlord/tenants/Setup', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'database' => $tenant->database,
                'status' => $tenant->status,
                'provisioned_at' => $tenant->provisioned_at?->toDateTimeString(),
                'provisioning_error' => $tenant->provisioning_error,
                'plan' => $tenant->plan ? ['id' => $tenant->plan->id, 'name' => $tenant->plan->name] : null,
                'primary_domain' => $tenant->primaryDomain ? [
                    'host' => $tenant->primaryDomain->host,
                    'is_active' => $tenant->primaryDomain->is_active,
                ] : null,
            ],
        ]);
    }

    /**
     * Trigger (or retry) provisioning for the given tenant.
     * Skips if already provisioning with no error (job likely still running).
     */
    public function provision(Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $alreadyRunning = $tenant->status === 'provisioning' && $tenant->provisioning_error === null;

        if (! $alreadyRunning) {
            $tenant->update(['status' => 'provisioning', 'provisioning_error' => null]);
            ProvisionTenantDatabaseJob::dispatch($tenant);
        }

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __('app.landlord.tenants.messages.provisioning_started'),
        ]);

        return to_route('landlord.tenants.setup', $tenant);
    }

    /**
     * Remove the specified tenant.
     */
    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->authorize('delete', $tenant);

        $tenant->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenants.messages.deleted'),
        ]);

        return to_route('landlord.tenants.index');
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function plansForSelect(): array
    {
        return Plan::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Plan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, is_active: bool}>
     */
    private function modulesForSelect(): array
    {
        return Module::query()
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(fn (Module $module): array => [
                'id' => $module->id,
                'name' => $module->name,
                'is_active' => $module->is_active,
            ])
            ->all();
    }

    /**
     * @return array<int, array{slug: string, name: string}>
     */
    private function modulesForFilter(): array
    {
        return Module::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (Module $module): array => [
                'slug' => $module->slug,
                'name' => $module->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusesForSelect(): array
    {
        return collect(self::AVAILABLE_STATUSES)
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => __('app.landlord.tenant_statuses.'.$status),
            ])
            ->all();
    }
}
