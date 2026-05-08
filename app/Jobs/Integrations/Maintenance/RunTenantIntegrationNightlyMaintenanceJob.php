<?php

namespace App\Jobs\Integrations\Maintenance;

use App\Models\TenantIntegration;
use App\Services\Integrations\Orchestration\RunNightlyMaintenanceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunTenantIntegrationNightlyMaintenanceJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(
        public string $integrationId,
    ) {
        $this->onQueue('maintenance');
    }

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
