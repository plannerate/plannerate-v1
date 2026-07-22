<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Support\Database\DatabaseCreator;
use App\Support\Database\TenantConnectionSwitcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ProvisionTenantDatabaseJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 10;

    public function __construct(public readonly Tenant $tenant)
    {
        $this->onQueue('critical');
    }

    public function handle(): void
    {
        // In testing, the database is managed by the test suite itself
        if (app()->environment('testing')) {
            return;
        }

        $connectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: 'tenant');
        $originalDatabase = config("database.connections.{$connectionName}.database");
        $originalDatabase = is_string($originalDatabase) ? $originalDatabase : null;
        $landlordConnection = DB::connection('landlord');
        $landlordDatabase = DB::connection('landlord')->getDatabaseName();
        $connectionSwitcher = app(TenantConnectionSwitcher::class);

        try {
            app(DatabaseCreator::class)->ensureExists($landlordConnection, $this->tenant->database);

            $connectionSwitcher->useDatabase($connectionName, $this->tenant->database);

            $resolvedTenantDatabase = DB::connection($connectionName)->getDatabaseName();

            if ($resolvedTenantDatabase !== $this->tenant->database || $resolvedTenantDatabase === $landlordDatabase) {
                throw new \RuntimeException(sprintf(
                    'Invalid tenant connection resolution. Expected "%s", got "%s".',
                    $this->tenant->database,
                    (string) $resolvedTenantDatabase,
                ));
            }

            Artisan::call('migrate', [
                '--database' => $connectionName,
                '--path' => database_path('migrations'),
                '--realpath' => true,
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
            $connectionSwitcher->useDatabase($connectionName, $originalDatabase);
        }
    }
}
