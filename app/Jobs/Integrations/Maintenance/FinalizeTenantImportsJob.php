<?php

namespace App\Jobs\Integrations\Maintenance;

use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class FinalizeTenantImportsJob implements NotTenantAware, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $uniqueFor = 1800;

    public function __construct(
        public string $tenantId,
    ) {
        $this->onQueue('maintenance');
    }

    public function uniqueId(): string
    {
        return $this->tenantId;
    }

    public function handle(): void
    {
        $tenant = Tenant::query()
            ->whereKey($this->tenantId)
            ->where('status', 'active')
            ->first();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $hasSales = $tenant->execute(function () use ($tenant): bool {
            $connection = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));

            return DB::connection($connection)
                ->table('sales')
                ->where('tenant_id', (string) $tenant->id)
                ->whereNull('deleted_at')
                ->exists();
        });

        if (! $hasSales) {
            Log::info('Finalização de imports ignorada: tenant sem vendas.', [
                'tenant_id' => (string) $tenant->id,
                'tenant_name' => (string) $tenant->name,
            ]);

            return;
        }

        Artisan::call('sync:link-sales', [
            '--tenant' => (string) $tenant->id,
        ]);

        Artisan::call('monthly-sales:recalculate', [
            '--tenant' => (string) $tenant->id,
            '--sync' => true,
        ]);

        Artisan::call('sync:cleanup', [
            '--tenant' => (string) $tenant->id,
        ]);

        Log::info('Finalização de imports concluída.', [
            'tenant_id' => (string) $tenant->id,
            'tenant_name' => (string) $tenant->name,
        ]);
    }
}
