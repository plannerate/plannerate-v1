<?php

namespace App\Jobs\Integrations\Dispatch;

use App\Models\TenantIntegration;
use App\Services\Integrations\Orchestration\DispatchInitialSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DispatchTenantIntegrationInitialSyncJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $integrationId,
        public ?string $resource = null,
        public bool $ignoreSyncDaysCheck = false,
    ) {}

    public function handle(DispatchInitialSyncService $dispatchInitialSyncService): void
    {
        $integration = TenantIntegration::query()
            ->whereKey($this->integrationId)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            Log::warning('Dispatch inicial ignorado por integracao inativa ou inexistente.', [
                'tenant_integration_id' => $this->integrationId,
                'resource' => $this->resource ?? 'all',
                'ignore_synced_days' => $this->ignoreSyncDaysCheck,
            ]);

            return;
        }

        $dispatchInitialSyncService->dispatch(
            integration: $integration,
            resource: $this->resource,
            ignoreSyncDaysCheck: $this->ignoreSyncDaysCheck,
        );
    }
}
