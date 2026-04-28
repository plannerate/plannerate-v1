<?php

namespace App\Jobs\Integrations;

use App\Models\IntegrationSyncDay;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class DispatchTenantIntegrationDailySyncJob implements ShouldQueue
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
        $lookbackDays = max(2, (int) ($processing['daily_lookback_days'] ?? 7));
        $yesterday = Carbon::yesterday()->startOfDay();
        $startDate = $yesterday->copy()->subDays($lookbackDays - 1);

        $targetDates = [];
        for ($date = $startDate->copy(); $date->lte($yesterday); $date->addDay()) {
            $targetDates[] = $date->toDateString();
        }

        $failedSalesDates = IntegrationSyncDay::query()
            ->where('tenant_integration_id', $integration->id)
            ->where('resource', 'sales')
            ->whereBetween('reference_date', [$startDate->toDateString(), $yesterday->toDateString()])
            ->whereIn('status', ['failed', 'pending'])
            ->pluck('reference_date')
            ->map(fn ($value): string => Carbon::parse((string) $value)->toDateString())
            ->all();

        $failedProductsDates = IntegrationSyncDay::query()
            ->where('tenant_integration_id', $integration->id)
            ->where('resource', 'products')
            ->whereBetween('reference_date', [$startDate->toDateString(), $yesterday->toDateString()])
            ->whereIn('status', ['failed', 'pending'])
            ->pluck('reference_date')
            ->map(fn ($value): string => Carbon::parse((string) $value)->toDateString())
            ->all();

        $salesDatesToDispatch = array_values(array_unique(array_merge($targetDates, $failedSalesDates)));
        $productsDatesToDispatch = array_values(array_unique(array_merge($targetDates, $failedProductsDates)));

        sort($salesDatesToDispatch);
        sort($productsDatesToDispatch);

        foreach ($salesDatesToDispatch as $date) {
            SyncTenantSalesDayJob::dispatch($integration->id, $date);
        }

        foreach ($productsDatesToDispatch as $date) {
            SyncTenantProductsDayJob::dispatch($integration->id, $date);
        }

    }
}
