<?php

namespace App\Jobs\Integrations;

use App\Models\Product;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RunTenantIntegrationNightlyMaintenanceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $integrationId,
    ) {}

    public function handle(TenantIntegrationConfigNormalizer $configNormalizer): void
    {
        $integration = TenantIntegration::query()
            ->whereKey($this->integrationId)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return;
        }

        $processing = $configNormalizer->normalize($integration)['processing'];
        $retentionDays = max(1, (int) ($processing['sales_retention_days'] ?? 120));
        $tenantId = (string) $integration->tenant_id;
        $cutoffDate = Carbon::today()->subDays($retentionDays)->toDateString();
        $windowStart = Carbon::today()->subDays($retentionDays - 1)->toDateString();

        DB::table('sales')
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
