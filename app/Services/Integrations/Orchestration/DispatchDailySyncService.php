<?php

namespace App\Services\Integrations\Orchestration;

use App\Jobs\Integrations\Maintenance\RunTenantIntegrationPostSyncJob;
use App\Jobs\Integrations\Products\SyncTenantProductsDayJob;
use App\Jobs\Integrations\Sales\SyncTenantSalesDayJob;
use App\Models\IntegrationSyncDay;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

class DispatchDailySyncService
{
    public function __construct(
        private readonly TenantIntegrationConfigNormalizer $configNormalizer,
    ) {}

    public function dispatch(TenantIntegration $integration, ?string $resource = null): void
    {
        $tenant = $integration->tenant;
        if (! $tenant) {
            return;
        }

        $processing = $this->configNormalizer->normalize($integration)['processing'];
        $lookbackDays = max(2, (int) ($processing['daily_lookback_days'] ?? 7));
        $yesterday = Carbon::yesterday()->startOfDay();
        $startDate = $yesterday->copy()->subDays($lookbackDays - 1);

        $targetDates = [];
        for ($date = $startDate->copy(); $date->lte($yesterday); $date->addDay()) {
            $targetDates[] = $date->toDateString();
        }

        $salesDatesToDispatch = [];
        if ($resource === null || $resource === 'sales') {
            $failedSalesDates = IntegrationSyncDay::query()
                ->where('tenant_integration_id', $integration->id)
                ->where('resource', 'sales')
                ->whereBetween('reference_date', [$startDate->toDateString(), $yesterday->toDateString()])
                ->whereIn('status', ['failed', 'pending'])
                ->pluck('reference_date')
                ->map(fn ($value): string => Carbon::parse((string) $value)->toDateString())
                ->all();

            $salesDatesToDispatch = array_values(array_unique(array_merge($targetDates, $failedSalesDates)));
        }

        $productsDatesToDispatch = [];
        if ($resource === null || $resource === 'products') {
            $failedProductsDates = IntegrationSyncDay::query()
                ->where('tenant_integration_id', $integration->id)
                ->where('resource', 'products')
                ->whereBetween('reference_date', [$startDate->toDateString(), $yesterday->toDateString()])
                ->whereIn('status', ['failed', 'pending'])
                ->pluck('reference_date')
                ->map(fn ($value): string => Carbon::parse((string) $value)->toDateString())
                ->all();

            $productsDatesToDispatch = array_values(array_unique(array_merge($targetDates, $failedProductsDates)));
        }

        sort($salesDatesToDispatch);
        sort($productsDatesToDispatch);

        $tenant->execute(function () use ($integration, $productsDatesToDispatch, $salesDatesToDispatch): void {
            $jobs = [];

            foreach ($salesDatesToDispatch as $date) {
                $jobs[] = new SyncTenantSalesDayJob((string) $integration->id, $date);
            }

            foreach ($productsDatesToDispatch as $date) {
                $jobs[] = new SyncTenantProductsDayJob((string) $integration->id, $date);
            }

            if ($jobs === []) {
                return;
            }

            $jobs[] = new RunTenantIntegrationPostSyncJob((string) $integration->tenant_id);

            Bus::chain($jobs)->dispatch();
        });
    }
}
