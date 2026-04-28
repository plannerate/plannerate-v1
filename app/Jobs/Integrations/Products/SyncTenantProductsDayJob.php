<?php

namespace App\Jobs\Integrations\Products;

use App\Models\IntegrationSyncDay;
use App\Models\Store;
use App\Models\TenantIntegration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncTenantProductsDayJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public string $integrationId,
        public string $referenceDate,
    ) {}

    public function handle(
    ): void {
        $integration = TenantIntegration::query()
            ->whereKey($this->integrationId)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return;
        }

        $syncDay = IntegrationSyncDay::query()->firstOrCreate(
            [
                'tenant_integration_id' => $integration->id,
                'resource' => 'products',
                'reference_date' => $this->referenceDate,
            ],
            [
                'status' => 'pending',
            ]
        );

        $syncDay->markRunning();

        try {
            $stores = Store::query()
                ->where('tenant_id', $integration->tenant_id)
                ->whereNull('deleted_at')
                ->get(['id']);

            foreach ($stores as $store) {
                DispatchTenantProductStorePagesJob::dispatch(
                    integrationId: $integration->id,
                    referenceDate: $this->referenceDate,
                    storeId: (string) $store->id,
                );
            }

            $syncDay->markSuccess();
        } catch (Throwable $exception) {
            $syncDay->markFailed($exception->getMessage());

            throw $exception;
        }
    }
}
