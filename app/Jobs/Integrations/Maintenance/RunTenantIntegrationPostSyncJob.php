<?php

namespace App\Jobs\Integrations\Maintenance;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunTenantIntegrationPostSyncJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tenantId,
    ) {}

    public function handle(): void
    {
        Artisan::call('sync:cleanup', ['--tenant' => $this->tenantId, '--all' => true]);
        Artisan::call('sync:products-from-ean-references', ['--tenant' => $this->tenantId]);
        Artisan::call('sync:link-sales', ['--tenant' => $this->tenantId]);
        Log::info('Tenant integration post sync job completed for tenant: '.$this->tenantId);
    }
}
