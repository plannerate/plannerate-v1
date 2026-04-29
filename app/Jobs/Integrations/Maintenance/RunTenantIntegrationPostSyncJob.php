<?php

namespace App\Jobs\Integrations\Maintenance;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class RunTenantIntegrationPostSyncJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tenantId,
    ) {}

    public function handle(): void
    {
        Artisan::call('sync:cleanup', ['--tenant' => $this->tenantId, '--all']);
        Artisan::call('sync:products-from-ean-references', ['--tenant' => $this->tenantId]);
        Artisan::call('sync:link-sales', ['--tenant' => $this->tenantId]);
    }
}
