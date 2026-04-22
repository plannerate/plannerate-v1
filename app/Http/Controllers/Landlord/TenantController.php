<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StoreTenantRequest;
use App\Http\Requests\Landlord\UpdateTenantRequest;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
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
    public function index(): Response
    {
        $tenants = Tenant::query()
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
                'user_limit' => $tenant->user_limit,
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
        ]);
    }

    /**
     * Show the form for creating a tenant.
     */
    public function create(): Response
    {
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

        return to_route('landlord.tenants.index');
    }

    /**
     * Show the form for editing the specified tenant.
     */
    public function edit(Tenant $tenant): Response
    {
        $tenant->load(['primaryDomain:id,tenant_id,host,is_active']);

        return Inertia::render('landlord/tenants/Form', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'database' => $tenant->database,
                'status' => $tenant->status,
                'plan_id' => $tenant->plan_id,
                'user_limit' => $tenant->user_limit,
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
     * Remove the specified tenant.
     */
    public function destroy(Tenant $tenant): RedirectResponse
    {
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
