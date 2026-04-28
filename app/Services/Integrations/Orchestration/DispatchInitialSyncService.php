<?php

namespace App\Services\Integrations\Orchestration;

use App\Jobs\Integrations\Products\SyncTenantProductsDayJob;
use App\Jobs\Integrations\Sales\SyncTenantSalesDayJob;
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
        $processing = $this->configNormalizer->normalize($integration)['processing'];
        $yesterday = Carbon::yesterday()->startOfDay();

        $salesInitialDays = max(1, (int) ($processing['sales_initial_days'] ?? 120));
        $productsInitialDays = max(1, (int) ($processing['products_initial_days'] ?? 120));

        $salesStart = $yesterday->copy()->subDays($salesInitialDays - 1);
        $productsStart = $yesterday->copy()->subDays($productsInitialDays - 1);

        for ($date = $salesStart->copy(); $date->lte($yesterday); $date->addDay()) {
            SyncTenantSalesDayJob::dispatch($integration->id, $date->toDateString());
        }

        for ($date = $productsStart->copy(); $date->lte($yesterday); $date->addDay()) {
            SyncTenantProductsDayJob::dispatch($integration->id, $date->toDateString());
        }
    }
}
