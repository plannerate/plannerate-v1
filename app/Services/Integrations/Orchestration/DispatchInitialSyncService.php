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

class DispatchInitialSyncService
{
    public function __construct(
        private readonly TenantIntegrationConfigNormalizer $configNormalizer,
    ) {}

    public function dispatch(
        TenantIntegration $integration,
        ?string $resource = null,
        bool $ignoreSyncDaysCheck = false,
    ): void {
        $tenant = $integration->tenant;
        if (! $tenant) {
            return;
        }

        $processing = $this->configNormalizer->normalize($integration)['processing'];
        $yesterday = Carbon::yesterday()->startOfDay();

        $salesInitialDays = max(1, (int) ($processing['sales_initial_days'] ?? 120));

        $salesStart = $yesterday->copy()->subDays($salesInitialDays - 1);
        $tenant->execute(function () use ($integration, $salesStart, $yesterday, $resource, $ignoreSyncDaysCheck): void {
            $jobs = [];

            if ($resource === null || $resource === 'sales') {
                for ($date = $salesStart->copy(); $date->lte($yesterday); $date->addDay()) {
                    $referenceDate = $date->toDateString();

                    if (! $ignoreSyncDaysCheck) {
                        $alreadySynced = IntegrationSyncDay::query()
                            ->where('tenant_integration_id', $integration->id)
                            ->where('resource', 'sales')
                            ->whereDate('reference_date', $referenceDate)
                            ->where('status', 'success')
                            ->exists();

                        if ($alreadySynced) {
                            continue;
                        }
                    }

                    $jobs[] = new SyncTenantSalesDayJob((string) $integration->id, $referenceDate, true);
                }
            }

            $productsReferenceDate = $yesterday->toDateString();
            if ($resource === null || $resource === 'products') {
                if ($ignoreSyncDaysCheck) {
                    $jobs[] = new SyncTenantProductsDayJob((string) $integration->id, $productsReferenceDate, true);
                } else {
                    $productsAlreadySynced = IntegrationSyncDay::query()
                        ->where('tenant_integration_id', $integration->id)
                        ->where('resource', 'products')
                        ->where('status', 'success')
                        ->exists();

                    if (! $productsAlreadySynced) {
                        $jobs[] = new SyncTenantProductsDayJob((string) $integration->id, $productsReferenceDate, true);
                    }
                }
            }

            if ($jobs === []) {
                return;
            }

            $jobs[] = new RunTenantIntegrationPostSyncJob((string) $integration->tenant_id);

            Bus::chain($jobs)->dispatch();
        });
    }
}
