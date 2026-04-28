<?php

namespace App\Jobs\Integrations\Dispatch;

use App\Models\TenantIntegration;
use App\Services\Integrations\Orchestration\DispatchDailySyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchTenantIntegrationDailySyncJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $integrationId,
    ) {}

    public function handle(DispatchDailySyncService $dispatchDailySyncService): void
    {
        $integration = TenantIntegration::query()
            ->whereKey($this->integrationId)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return;
        }

        $dispatchDailySyncService->dispatch($integration);
    }
}
