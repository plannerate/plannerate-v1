<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreTenantRequest;
use App\Http\Requests\Landlord\UpdateTenantRequest;
use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    /**
     * @var list<string>
     */
    private const AVAILABLE_STATUSES = ['provisioning', 'active', 'suspended', 'inactive'];

    /**
     * Display a listing of tenants.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Tenant::class);

        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');
        $planId = trim((string) $request->string('plan_id'));
        $hasStatusFilter = in_array($status, self::AVAILABLE_STATUSES, true);
        $hasPlanFilter = $planId !== '';

        $tenants = Tenant::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('database', 'like', '%'.$search.'%')
                        ->orWhereHas('primaryDomain', fn ($domainQuery) => $domainQuery->where('host', 'like', '%'.$search.'%'));
                });
            })
            ->when($hasStatusFilter, fn ($query) => $query->where('status', $status))
            ->when($hasPlanFilter, fn ($query) => $query->where('plan_id', $planId))
            ->with(['plan:id,name', 'primaryDomain:id,tenant_id,host,is_active'])
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Tenant $tenant): array => [
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

        return Inertia::render('landlord/tenants/Index', [
            'tenants' => $tenants,
            'filters' => [
                'search' => $search,
                'status' => $hasStatusFilter ? $status : '',
                'plan_id' => $hasPlanFilter ? $planId : '',
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
            ],
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

        DB::connection('landlord')->transaction(function () use ($validated): void {
            $tenant = Tenant::query()->create(Arr::except($validated, ['host', 'domain_is_active']));

            $tenant->domains()->create([
                'tenant_id' => $tenant->id,
                'host' => $validated['host'],
                'type' => 'subdomain',
                'is_primary' => true,
                'is_active' => (bool) ($validated['domain_is_active'] ?? true),
            ]);
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

        $tenant->load(['primaryDomain:id,tenant_id,host,is_active']);

        return Inertia::render('landlord/tenants/Form', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'database' => $tenant->database,
                'status' => $tenant->status,
                'provisioning_error' => $tenant->provisioning_error,
                'plan_id' => $tenant->plan_id,
                'host' => $tenant->primaryDomain?->host,
                'domain_is_active' => $tenant->primaryDomain?->is_active ?? true,
            ],
            'plans' => $this->plansForSelect(),
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

        DB::connection('landlord')->transaction(function () use ($tenant, $validated): void {
            $tenant->update(Arr::except($validated, ['host', 'domain_is_active']));

            $tenant->domains()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'host' => $validated['host'],
                    'type' => 'subdomain',
                    'is_primary' => true,
                    'is_active' => (bool) ($validated['domain_is_active'] ?? true),
                ],
            );
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
