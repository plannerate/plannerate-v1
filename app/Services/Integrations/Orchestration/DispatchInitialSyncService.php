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
use Illuminate\Support\Facades\Log;

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
                    Log::info('Dispatch inicial de produtos forcado por --ignore-synced-days.', [
                        'tenant_id' => (string) $integration->tenant_id,
                        'tenant_integration_id' => (string) $integration->id,
                        'reference_date' => $productsReferenceDate,
                    ]);
                    $jobs[] = new SyncTenantProductsDayJob((string) $integration->id, $productsReferenceDate, true);
                } else {
                    $productsAlreadySynced = IntegrationSyncDay::query()
                        ->where('tenant_integration_id', $integration->id)
                        ->where('resource', 'products')
                        ->where('status', 'success')
                        ->exists();

                    if (! $productsAlreadySynced) {
                        Log::info('Dispatch inicial de produtos enfileirado.', [
                            'tenant_id' => (string) $integration->tenant_id,
                            'tenant_integration_id' => (string) $integration->id,
                            'reference_date' => $productsReferenceDate,
                            'reason' => 'nenhum dia de produtos com status success encontrado',
                        ]);
                        $jobs[] = new SyncTenantProductsDayJob((string) $integration->id, $productsReferenceDate, true);
                    } else {
                        Log::warning('Dispatch inicial de produtos ignorado.', [
                            'tenant_id' => (string) $integration->tenant_id,
                            'tenant_integration_id' => (string) $integration->id,
                            'reason' => 'ja existe sincronizacao de produtos com status success',
                            'hint' => 'use --ignore-synced-days para forcar reprocessamento',
                        ]);
                    }
                }
            } else {
                Log::info('Dispatch inicial de produtos nao solicitado para esta execucao.', [
                    'tenant_id' => (string) $integration->tenant_id,
                    'tenant_integration_id' => (string) $integration->id,
                    'resource_option' => $resource,
                ]);
            }

            if ($jobs === []) {
                return;
            }

            $jobs[] = new RunTenantIntegrationPostSyncJob((string) $integration->tenant_id);

            Bus::chain($jobs)->dispatch();
        });
    }
}
