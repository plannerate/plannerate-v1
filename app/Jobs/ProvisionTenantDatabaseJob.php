<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Support\Database\DatabaseCreator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ProvisionTenantDatabaseJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public readonly Tenant $tenant) {}

    public function handle(): void
    {
        $connectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: 'tenant');
        $originalDatabase = config("database.connections.{$connectionName}.database");
        $landlordConnection = DB::connection('landlord');

        try {
            app(DatabaseCreator::class)->ensureExists($landlordConnection, $this->tenant->database);

            config(["database.connections.{$connectionName}.database" => $this->tenant->database]);
            DB::purge($connectionName);

            Artisan::call('migrate', [
                '--database' => $connectionName,
                '--force' => true,
            ]);

            $this->tenant->update([
                'status' => 'active',
                'provisioned_at' => now(),
                'provisioning_error' => null,
            ]);
        } catch (\Throwable $e) {
            $this->tenant->update([
                'provisioning_error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            config(["database.connections.{$connectionName}.database" => $originalDatabase]);
            DB::purge($connectionName);
        }
    }
}
