<?php

namespace App\Jobs\Integrations\Maintenance;

use App\Models\TenantIntegration;
use App\Services\Integrations\Orchestration\RunNightlyMaintenanceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunTenantIntegrationNightlyMaintenanceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $integrationId,
    ) {}

    public function handle(RunNightlyMaintenanceService $runNightlyMaintenanceService): void
    {
        $integration = TenantIntegration::query()
            ->whereKey($this->integrationId)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return;
        }

        $runNightlyMaintenanceService->run($integration);
    }
}
