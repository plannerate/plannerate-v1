<?php

namespace App\Observers;

use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\Tenant;

class TenantObserver
{
    public function created(Tenant $tenant): void
    {
        ProvisionTenantDatabaseJob::dispatch($tenant);
    }
}
