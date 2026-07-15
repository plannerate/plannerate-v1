<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\InteractsWithDeferredIndex;
use App\Http\Controllers\Concerns\InteractsWithResourceAbilities;
use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\ImportTenantConfigurationsRequest;
use App\Http\Requests\Landlord\StoreTenantRequest;
use App\Http\Requests\Landlord\UpdateTenantRequest;
use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\IntegrationApi;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Cloudflare\CloudflareService;
use App\Support\Authorization\RbacType;
use App\Support\Modules\ModuleSlug;
use App\Support\Modules\TenantModuleService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithResourceAbilities;

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
            'can' => $this->resolveResourceAbilities(Tenant::class),
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
        return $this->filteredTenantsQuery($search, $status, $planId, $module)
            ->with(['plan:id,name', 'primaryDomain:id,tenant_id,host,is_active', 'modules:id,slug,is_active', 'socialiteProvider'])
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
                'sso_provider' => $tenant->socialiteProvider ? [
                    'id' => $tenant->socialiteProvider->id,
                    'provider' => $tenant->socialiteProvider->provider,
                    'label' => $tenant->socialiteProvider->label,
                    'client_id' => $tenant->socialiteProvider->client_id,
                    'azure_tenant' => $tenant->socialiteProvider->azure_tenant,
                    'is_active' => $tenant->socialiteProvider->is_active,
                ] : null,
                'created_at' => $tenant->created_at?->toDateTimeString(),
            ]);
    }

    private function filteredTenantsQuery(string $search, string $status, string $planId, string $module): Builder
    {
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
            ->when($module !== '', fn ($query) => $query->whereHasActiveModule($module));
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
            'roles' => $this->rolesForSelect(),
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
        $roleIds = $validated['role_ids'] ?? [];

        DB::connection('landlord')->transaction(function () use ($validated, $moduleIds, $roleIds): void {
            $tenant = Tenant::query()->create(Arr::except($validated, ['host', 'domain_is_active', 'module_ids', 'role_ids']));

            $tenant->domains()->create([
                'tenant_id' => $tenant->id,
                'host' => $validated['host'],
                'type' => 'subdomain',
                'is_primary' => true,
                'is_active' => (bool) ($validated['domain_is_active'] ?? true),
            ]);

            $tenant->modules()->sync($moduleIds);
            $tenant->roles()->sync($roleIds);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenants.messages.created'),
        ]);

        $createdTenant = Tenant::query()->where('slug', $validated['slug'])->first();

        return $this->toLandlordRoute('landlord.tenants.setup', ['tenant' => $createdTenant]);
    }

    /**
     * Show the form for editing the specified tenant.
     */
    public function edit(Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        $tenant->load(['primaryDomain:id,tenant_id,host,is_active', 'modules:id,name', 'roles:id,name']);

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
                'role_ids' => $tenant->roles->pluck('id')->values()->all(),
                'host' => $tenant->primaryDomain?->host,
                'domain_is_active' => $tenant->primaryDomain?->is_active ?? true,
            ],
            'plans' => $this->plansForSelect(),
            'modules' => $this->modulesForSelect(),
            'roles' => $this->rolesForSelect(),
            'statuses' => $this->statusesForSelect(),
            'cloudflare_record' => Inertia::defer(fn (): ?array => $this->resolveCloudflareRecord($tenant)),
        ]);
    }

    private function resolveCloudflareRecord(Tenant $tenant): ?array
    {
        $host = $tenant->primaryDomain?->host;
        $zoneId = config('cloudflare.zone_id', '');

        /** @var CloudflareService $cloudflare */
        $cloudflare = app(CloudflareService::class);

        if (! $cloudflare->isConfigured() || $zoneId === '' || ! $host) {
            return null;
        }

        try {
            $result = $cloudflare->listRecords($zoneId, 'CNAME', $host);

            if (! ($result['success'] ?? false)) {
                return null;
            }

            $records = $result['result'] ?? [];
            $record = $records[0] ?? null;

            if (! $record) {
                return ['exists' => false, 'cname_target' => config('cloudflare.cname_target', '')];
            }

            return [
                'exists' => true,
                'id' => $record['id'],
                'name' => $record['name'],
                'content' => $record['content'],
                'cname_target' => config('cloudflare.cname_target', ''),
            ];
        } catch (\Throwable $e) {
            // Silently fail if Cloudflare is not properly configured or accessible
            return null;
        }
    }

    /**
     * Update the specified tenant.
     */
    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validated();
        $moduleIds = $validated['module_ids'] ?? [];
        $roleIds = $validated['role_ids'] ?? [];

        DB::connection('landlord')->transaction(function () use ($tenant, $validated, $moduleIds, $roleIds): void {
            $tenant->update(Arr::except($validated, ['host', 'domain_is_active', 'module_ids', 'role_ids']));

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
            $tenant->roles()->sync($roleIds);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenants.messages.updated'),
        ]);

        return $this->toLandlordRoute('landlord.tenants.index');
    }

    public function exportConfigurations(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Tenant::class);

        $search = $this->requestString($request, 'search');
        $status = $this->requestEnum($request, 'status', self::AVAILABLE_STATUSES);
        $planId = $this->requestString($request, 'plan_id');
        $module = $this->requestString($request, 'module');

        $tenants = $this->filteredTenantsQuery($search, $status, $planId, $module)
            ->with([
                'plan:id,slug,name',
                'primaryDomain:id,tenant_id,host,type,is_primary,is_active',
                'integration:id,tenant_id,integration_type,identifier,config,is_active,last_sync',
            ])
            ->orderBy('name')
            ->get();

        $payload = [
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'plan_id' => $planId,
                'module' => $module,
            ],
            'integration_apis' => IntegrationApi::query()
                ->orderBy('slug')
                ->get()
                ->map(fn (IntegrationApi $integrationApi): array => [
                    'name' => $integrationApi->name,
                    'slug' => $integrationApi->slug,
                    'description' => $integrationApi->description,
                    'requests' => is_array($integrationApi->requests) ? $integrationApi->requests : [],
                    'response' => is_array($integrationApi->response) ? $integrationApi->response : [],
                    'is_active' => (bool) $integrationApi->is_active,
                ])
                ->values()
                ->all(),
            'tenants' => $tenants->map(fn (Tenant $tenant): array => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'database' => $tenant->database,
                'status' => $tenant->status,
                'plan_slug' => $tenant->plan?->slug,
                'primary_domain' => $tenant->primaryDomain ? [
                    'host' => $tenant->primaryDomain->host,
                    'type' => $tenant->primaryDomain->type,
                    'is_primary' => (bool) $tenant->primaryDomain->is_primary,
                    'is_active' => (bool) $tenant->primaryDomain->is_active,
                ] : null,
                'integration' => $tenant->integration ? [
                    'integration_type' => $tenant->integration->integration_type,
                    'identifier' => $tenant->integration->identifier,
                    'config' => is_array($tenant->integration->config) ? $tenant->integration->config : [],
                    'is_active' => (bool) $tenant->integration->is_active,
                    'last_sync' => $tenant->integration->last_sync?->toIso8601String(),
                ] : null,
            ])->values()->all(),
        ];

        $fileName = sprintf('tenant-configs-%s.json', now()->format('Ymd-His'));

        return response()->streamDownload(
            static function () use ($payload): void {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            },
            $fileName,
            ['Content-Type' => 'application/json']
        );
    }

    public function importConfigurations(ImportTenantConfigurationsRequest $request): RedirectResponse
    {
        $this->authorize('create', Tenant::class);

        $uploadedFile = $request->file('spreadsheet');

        if (! $uploadedFile instanceof UploadedFile) {
            return $this->toLandlordRoute('landlord.tenants.index');
        }

        $decoded = json_decode((string) $uploadedFile->get(), true);
        if (! is_array($decoded)) {
            return back()->withErrors([
                'spreadsheet' => __('app.landlord.tenants.messages.import_invalid_json'),
            ]);
        }

        $rawTenants = $decoded['tenants'] ?? $decoded;
        if (! is_array($rawTenants)) {
            return back()->withErrors([
                'spreadsheet' => __('app.landlord.tenants.messages.import_invalid_structure'),
            ]);
        }

        $apiCreated = 0;
        $apiUpdated = 0;

        collect($decoded['integration_apis'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->each(function (array $item) use (&$apiCreated, &$apiUpdated): void {
                $slug = Str::of((string) ($item['slug'] ?? ''))->trim()->lower()->toString();
                if ($slug === '') {
                    return;
                }

                $name = Str::of((string) ($item['name'] ?? ''))->trim()->toString();

                $payload = [
                    'name' => $name !== '' ? $name : Str::of($slug)->replace(['-', '_'], ' ')->title()->toString(),
                    'slug' => $slug,
                    'description' => is_string($item['description'] ?? null) ? $item['description'] : null,
                    'requests' => is_array($item['requests'] ?? null) ? $item['requests'] : [],
                    'response' => is_array($item['response'] ?? null) ? $item['response'] : [],
                    'is_active' => (bool) ($item['is_active'] ?? true),
                ];

                $existing = IntegrationApi::withTrashed()->where('slug', $slug)->first();

                if ($existing instanceof IntegrationApi) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }

                    $existing->update($payload);
                    $apiUpdated++;

                    return;
                }

                IntegrationApi::query()->create($payload);
                $apiCreated++;
            });

        $tenantCreated = 0;
        $tenantUpdated = 0;
        $tenantSkipped = 0;
        $domainUpserted = 0;
        $integrationUpserted = 0;

        collect($rawTenants)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->each(function (array $item) use (&$tenantCreated, &$tenantUpdated, &$tenantSkipped, &$domainUpserted, &$integrationUpserted): void {
                $slug = Str::of((string) ($item['slug'] ?? ''))->trim()->lower()->toString();
                if ($slug === '') {
                    $tenantSkipped++;

                    return;
                }

                $name = Str::of((string) ($item['name'] ?? ''))->trim()->toString();
                $database = Str::of((string) ($item['database'] ?? ''))->trim()->toString();
                $status = Str::of((string) ($item['status'] ?? 'provisioning'))->trim()->toString();
                $planSlug = Str::of((string) ($item['plan_slug'] ?? ''))->trim()->toString();

                $existing = Tenant::query()->where('slug', $slug)->first();
                $planId = $planSlug !== '' ? Plan::query()->where('slug', $planSlug)->value('id') : null;

                $payload = [
                    'name' => $name !== '' ? $name : Str::of($slug)->replace(['-', '_'], ' ')->title()->toString(),
                    'slug' => $slug,
                    /**
                     * An existing tenant keeps its own database: the exported name is
                     * environment-specific and repointing a live tenant to another
                     * environment's database would orphan all of its data.
                     */
                    'database' => $existing?->database
                        ?? ($database !== '' ? $database : Str::of($slug)->replace('-', '_')->toString()),
                    'status' => in_array($status, self::AVAILABLE_STATUSES, true) ? $status : 'provisioning',
                    'plan_id' => is_string($planId) ? $planId : null,
                ];

                if ($existing instanceof Tenant) {
                    $existing->update($payload);
                    $tenant = $existing;
                    $tenantUpdated++;
                } else {
                    $tenant = Tenant::query()->create($payload);
                    $tenantCreated++;
                }

                $primaryDomain = $item['primary_domain'] ?? null;
                if (is_array($primaryDomain)) {
                    $host = Str::of((string) ($primaryDomain['host'] ?? ''))->trim()->toString();

                    if ($host !== '') {
                        $tenant->domains()->updateOrCreate(
                            ['tenant_id' => $tenant->id],
                            [
                                'host' => $host,
                                'type' => (string) ($primaryDomain['type'] ?? 'subdomain'),
                                'is_primary' => (bool) ($primaryDomain['is_primary'] ?? true),
                                'is_active' => (bool) ($primaryDomain['is_active'] ?? true),
                            ],
                        );

                        $domainUpserted++;
                    }
                }

                $integration = $item['integration'] ?? null;
                if (is_array($integration)) {
                    $integrationType = Str::of((string) ($integration['integration_type'] ?? ''))->trim()->toString();

                    if ($integrationType !== '') {
                        TenantIntegration::query()->updateOrCreate(
                            ['tenant_id' => $tenant->id],
                            [
                                'integration_type' => $integrationType,
                                'identifier' => Str::of((string) ($integration['identifier'] ?? ''))->trim()->toString() ?: null,
                                'config' => is_array($integration['config'] ?? null) ? $integration['config'] : [],
                                'is_active' => (bool) ($integration['is_active'] ?? true),
                                'last_sync' => (string) ($integration['last_sync'] ?? '') !== '' ? $integration['last_sync'] : null,
                            ],
                        );

                        $integrationUpserted++;
                    }
                }
            });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenants.messages.imported', [
                'tenant_created' => $tenantCreated,
                'tenant_updated' => $tenantUpdated,
                'tenant_skipped' => $tenantSkipped,
                'domains' => $domainUpserted,
                'integrations' => $integrationUpserted,
                'api_created' => $apiCreated,
                'api_updated' => $apiUpdated,
            ]),
        ]);

        return $this->toLandlordRoute('landlord.tenants.index');
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

        return $this->toLandlordRoute('landlord.tenants.setup', ['tenant' => $tenant]);
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

        return $this->toLandlordRoute('landlord.tenants.index');
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
     * Perfis (roles) de tenant que podem ser vinculados a um tenant.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function rolesForSelect(): array
    {
        return Role::query()
            ->whereNull('tenant_id')
            ->where('guard_name', 'web')
            ->where('type', RbacType::TENANT)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
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
