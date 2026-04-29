<?php

namespace App\Jobs\Integrations\Maintenance;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class RunTenantIntegrationPostSyncJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tenantId,
    ) {}

    public function handle(): void
    {
        $this->callCommand('sync:cleanup', ['--tenant' => $this->tenantId, '--all' => true]);
        $this->callCommand('sync:products-from-ean-references', ['--tenant' => $this->tenantId]);
        $this->callCommand('sync:link-sales', ['--tenant' => $this->tenantId]);

        RecalculateTenantMonthlySalesSummariesJob::dispatch($this->tenantId);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function callCommand(string $command, array $parameters): void
    {
        $exitCode = Artisan::call($command, $parameters);

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'Command [%s] failed for tenant [%s] with exit code [%d].',
                $command,
                $this->tenantId,
                $exitCode,
            ));
        }
    }
}
