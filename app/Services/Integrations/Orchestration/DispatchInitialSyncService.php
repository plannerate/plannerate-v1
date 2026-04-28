<?php

namespace App\Services\Integrations\Orchestration;

use App\Jobs\Integrations\Products\SyncTenantProductsDayJob;
use App\Jobs\Integrations\Sales\SyncTenantSalesDayJob;
use App\Models\IntegrationSyncDay;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DispatchInitialSyncService
{
    public function __construct(
        private readonly TenantIntegrationConfigNormalizer $configNormalizer,
    ) {}

    public function dispatch(TenantIntegration $integration): void
    {
        $tenant = $integration->tenant;
        if (! $tenant) {
            return;
        }

        $processing = $this->configNormalizer->normalize($integration)['processing'];
        $yesterday = Carbon::yesterday()->startOfDay();

        $salesInitialDays = max(1, (int) ($processing['sales_initial_days'] ?? 120));
        $productsInitialDays = max(1, (int) ($processing['products_initial_days'] ?? 120));

        $salesStart = $yesterday->copy()->subDays($salesInitialDays - 1);
        $productsStart = $yesterday->copy()->subDays($productsInitialDays - 1);
        $tenant->execute(function () use ($integration, $productsStart, $salesStart, $yesterday): void {
            for ($date = $salesStart->copy(); $date->lte($yesterday); $date->addDay()) {
                $referenceDate = $date->toDateString();

                $alreadySynced = IntegrationSyncDay::query()
                    ->where('tenant_integration_id', $integration->id)
                    ->where('resource', 'sales')
                    ->whereDate('reference_date', $referenceDate)
                    ->where('status', 'success')
                    ->exists();

                if ($alreadySynced) {
                    Log::info('Initial sync skipped sales date already successful.', [
                        'integration_id' => $integration->id,
                        'tenant_id' => $integration->tenant_id,
                        'reference_date' => $referenceDate,
                        'resource' => 'sales',
                    ]);

                    continue;
                }

                SyncTenantSalesDayJob::dispatch($integration->id, $referenceDate);
            }

            for ($date = $productsStart->copy(); $date->lte($yesterday); $date->addDay()) {
                $referenceDate = $date->toDateString();

                $alreadySynced = IntegrationSyncDay::query()
                    ->where('tenant_integration_id', $integration->id)
                    ->where('resource', 'products')
                    ->whereDate('reference_date', $referenceDate)
                    ->where('status', 'success')
                    ->exists();

                if ($alreadySynced) {
                    Log::info('Initial sync skipped products date already successful.', [
                        'integration_id' => $integration->id,
                        'tenant_id' => $integration->tenant_id,
                        'reference_date' => $referenceDate,
                        'resource' => 'products',
                    ]);

                    continue;
                }

                SyncTenantProductsDayJob::dispatch($integration->id, $referenceDate);
            }
        });
    }
}
