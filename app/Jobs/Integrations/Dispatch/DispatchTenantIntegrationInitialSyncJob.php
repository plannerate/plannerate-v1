<?php

namespace App\Jobs\Integrations\Dispatch;

use App\Models\TenantIntegration;
use App\Services\Integrations\Orchestration\DispatchInitialSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
            return;
        }

        $dispatchInitialSyncService->dispatch(
            integration: $integration,
            resource: $this->resource,
            ignoreSyncDaysCheck: $this->ignoreSyncDaysCheck,
        );
    }
}
