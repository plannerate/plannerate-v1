<?php

namespace App\Services\Integrations\Orchestration;

use App\Models\Product;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RunNightlyMaintenanceService
{
    public function __construct(
        private readonly TenantIntegrationConfigNormalizer $configNormalizer,
    ) {}

    public function run(TenantIntegration $integration): void
    {
        $processing = $this->configNormalizer->normalize($integration)['processing'];
        $retentionDays = max(1, (int) ($processing['sales_retention_days'] ?? 120));
        $tenantId = (string) $integration->tenant_id;
        $cutoffDate = Carbon::today()->subDays($retentionDays)->toDateString();
        $windowStart = Carbon::today()->subDays($retentionDays - 1)->toDateString();
        $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));

        DB::connection($tenantConnectionName)->table('sales')
            ->where('tenant_id', $tenantId)
            ->whereDate('sale_date', '<', $cutoffDate)
            ->delete();

        Product::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->whereIn('codigo_erp', function ($query) use ($tenantId, $windowStart): void {
                $query->select('codigo_erp')
                    ->from('sales')
                    ->where('tenant_id', $tenantId)
                    ->whereDate('sale_date', '>=', $windowStart)
                    ->whereNotNull('codigo_erp');
            })
            ->restore();

        Product::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotIn('codigo_erp', function ($query) use ($tenantId, $windowStart): void {
                $query->select('codigo_erp')
                    ->from('sales')
                    ->where('tenant_id', $tenantId)
                    ->whereDate('sale_date', '>=', $windowStart)
                    ->whereNotNull('codigo_erp');
            })
            ->update([
                'deleted_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
    }
}
