<?php

namespace App\Jobs\Integrations;

use App\Models\IntegrationSyncDay;
use App\Models\TenantIntegration;
use App\Services\Integrations\Sysmo\SysmoSalesIntegrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

class SyncTenantSalesDayJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public string $integrationId,
        public string $referenceDate,
    ) {}

    public function handle(SysmoSalesIntegrationService $salesIntegrationService): void
    {
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
                'resource' => 'sales',
                'reference_date' => $this->referenceDate,
            ],
            [
                'status' => 'pending',
            ]
        );

        $syncDay->markRunning();

        try {
            $salesIntegrationService->fetchSales($integration, [
                'date' => Carbon::parse($this->referenceDate)->toDateString(),
            ]);

            $syncDay->markSuccess();
        } catch (Throwable $exception) {
            $syncDay->markFailed($exception->getMessage());

            throw $exception;
        }
    }
}
