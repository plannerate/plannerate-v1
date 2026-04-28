<?php

namespace App\Jobs\Integrations;

use App\Models\TenantIntegration;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class DispatchTenantIntegrationInitialSyncJob implements ShouldQueue
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
