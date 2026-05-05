<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Modules\ModuleSlug;
use App\Support\Modules\TenantModuleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Models\Tenant as CurrentTenantModel;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TenantModuleService $tenantModuleService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Tenant::class);

        $totals = [
            'all' => Tenant::query()->count(),
            'active' => Tenant::query()->where('status', 'active')->count(),
            'provisioning' => Tenant::query()->where('status', 'provisioning')->count(),
            'inactive' => Tenant::query()->whereIn('status', ['inactive', 'suspended'])->count(),
        ];

        $statusOrder = ['active', 'provisioning', 'suspended', 'inactive'];

        $statusDistribution = Tenant::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->status => (int) $row->total]);

        $statusChart = collect($statusOrder)->map(fn (string $status): array => [
            'status' => $status,
            'total' => (int) ($statusDistribution[$status] ?? 0),
        ])->values();

        $months = collect(range(5, 0, -1))
            ->map(fn (int $offset): CarbonImmutable => now()->toImmutable()->startOfMonth()->subMonths($offset))
            ->push(now()->toImmutable()->startOfMonth());

        $newTenantsByMonthRaw = Tenant::query()
            ->where('created_at', '>=', $months->first()?->toDateString())
            ->get(['created_at'])
            ->filter(fn (Tenant $tenant): bool => $tenant->created_at !== null)
            ->groupBy(fn (Tenant $tenant): string => $tenant->created_at->format('Y-m'))
            ->mapWithKeys(fn ($items, string $monthKey): array => [$monthKey => $items->count()]);

        $tenantsByMonth = $months->map(fn (CarbonImmutable $month): array => [
            'month' => $month->format('M/y'),
            'total' => (int) ($newTenantsByMonthRaw[$month->format('Y-m')] ?? 0),
        ])->values();

        $recentTenants = Tenant::query()
            ->with(['plan:id,name,user_limit', 'primaryDomain:id,tenant_id,host,is_active', 'integration:id,tenant_id,is_active,last_sync', 'modules:id,slug,is_active'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (Tenant $tenant): array {
                /** @var array{users_count:int,workflow_usage_count:int,workflow_in_use:bool} $tenantUsage */
                $tenantUsage = [
                    'users_count' => 0,
                    'workflow_usage_count' => 0,
                    'workflow_in_use' => false,
                ];

                try {
                    $tenantUsage = $this->runInTenantContext($tenant, function (): array {
                        $usersCount = User::query()->count();

                        $tenantConnection = User::query()->getModel()->getConnectionName();
                        $workflowTableExists = Schema::connection((string) $tenantConnection)->hasTable('workflow_gondola_executions');

                        $workflowUsageCount = 0;

                        if ($workflowTableExists) {
                            $workflowUsageCount = (int) DB::connection((string) $tenantConnection)
                                ->table('workflow_gondola_executions')
                                ->count();
                        }

                        return [
                            'users_count' => $usersCount,
                            'workflow_usage_count' => $workflowUsageCount,
                            'workflow_in_use' => $workflowUsageCount > 0,
                        ];
                    });
                } catch (Throwable) {
                    // Tenant database may still be unavailable during provisioning.
                }

                $kanbanEnabled = $this->tenantModuleService->tenantHasActiveModule($tenant, ModuleSlug::KANBAN);

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status,
                    'plan' => $tenant->plan?->name,
                    'plan_user_limit' => $tenant->plan_user_limit,
                    'users_count' => $tenantUsage['users_count'],
                    'kanban_enabled' => $kanbanEnabled,
                    'workflow_in_use' => $tenantUsage['workflow_in_use'],
                    'workflow_usage_count' => $tenantUsage['workflow_usage_count'],
                    'integration_active' => (bool) ($tenant->integration?->is_active ?? false),
                    'host' => $tenant->primaryDomain?->host,
                    'client_since_human' => $tenant->created_at?->diffForHumans(),
                    'created_at' => $tenant->created_at?->toDateTimeString(),
                ];
            });

        return inertia('landlord/Dashboard', [
            'totals' => $totals,
            'status_chart' => $statusChart,
            'tenants_by_month' => $tenantsByMonth,
            'recent_tenants' => $recentTenants,
        ]);
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function runInTenantContext(Tenant $tenant, callable $callback): mixed
    {
        $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: 'tenant');
        $originalTenantDatabase = config("database.connections.{$tenantConnectionName}.database");
        $originalTenant = CurrentTenantModel::current();
        $tenant->makeCurrent();

        try {
            return $callback();
        } finally {
            if ($originalTenant !== null) {
                $originalTenant->makeCurrent();
            } else {
                CurrentTenantModel::forgetCurrent();
                config([
                    "database.connections.{$tenantConnectionName}.database" => $originalTenantDatabase,
                ]);
                DB::purge($tenantConnectionName);
            }
        }
    }
}
