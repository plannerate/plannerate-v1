<?php

namespace App\Services\Integrations\Orchestration;

use App\Jobs\Integrations\Products\SyncTenantProductsDayJob;
use App\Jobs\Integrations\Sales\SyncTenantSalesDayJob;
use App\Models\IntegrationSyncDay;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Support\Carbon;

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
        $tenant->execute(function () use ($integration, $salesStart, $yesterday): void {
            for ($date = $salesStart->copy(); $date->lte($yesterday); $date->addDay()) {
                $referenceDate = $date->toDateString();

                $alreadySynced = IntegrationSyncDay::query()
                    ->where('tenant_integration_id', $integration->id)
                    ->where('resource', 'sales')
                    ->whereDate('reference_date', $referenceDate)
                    ->where('status', 'success')
                    ->exists();

                if ($alreadySynced) {
                    continue;
                }

                SyncTenantSalesDayJob::dispatch($integration->id, $referenceDate, true);
            }

            $productsReferenceDate = $yesterday->toDateString();
            $productsAlreadySynced = IntegrationSyncDay::query()
                ->where('tenant_integration_id', $integration->id)
                ->where('resource', 'products')
                ->where('status', 'success')
                ->exists();

            if ($productsAlreadySynced) {
                return;
            }

            SyncTenantProductsDayJob::dispatch($integration->id, $productsReferenceDate, true);
        });
    }
}
