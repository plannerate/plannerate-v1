<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TenantDomainsSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, slug: string, host: string, database: string, admin_name: string, admin_email: string}>
     */
    private const TENANTS = [
        [
            'name' => 'Alfa',
            'slug' => 'alfa',
            'host' => 'alfa.plannerate-v1.test',
            'database' => 'tenant_alfa',
            'admin_name' => 'Administrador Alfa',
            'admin_email' => 'admin@alfa.plannerate-v1.test',
        ],
        [
            'name' => 'Coperdia',
            'slug' => 'coperdia',
            'host' => 'coperdia.plannerate-v1.test',
            'database' => 'tenant_coperdia',
            'admin_name' => 'Administrador Coperdia',
            'admin_email' => 'admin@coperdia.plannerate-v1.test',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::TENANTS as $tenantDefinition) {
            $this->seedTenant($tenantDefinition);
        }
    }

    /**
     * @param  array{name: string, slug: string, host: string, database: string, admin_name: string, admin_email: string}  $tenantDefinition
     */
    private function seedTenant(array $tenantDefinition): void
    {
        $database = $tenantDefinition['database'];

        if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new InvalidArgumentException("Invalid tenant database name [{$database}].");
        }

        DB::connection('landlord')->statement(sprintf('CREATE DATABASE IF NOT EXISTS `%s`', $database));

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => $tenantDefinition['slug']],
            [
                'name' => $tenantDefinition['name'],
                'database' => $database,
                'status' => 'active',
            ],
        );

        $tenant->domains()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'host' => $tenantDefinition['host'],
                'type' => 'subdomain',
                'is_primary' => true,
                'is_active' => true,
            ],
        );

        $tenant->execute(function () use ($tenantDefinition): void {
            $this->runTenantMigrationsIfNeeded();

            $user = User::query()->firstOrNew(['email' => $tenantDefinition['admin_email']]);

            if (! $user->exists) {
                $user->id = (string) Str::ulid();
            }

            $user->fill([
                'name' => $tenantDefinition['admin_name'],
                'password' => 'password',
                'email_verified_at' => now(),
            ]);

            $user->save();
        });
    }

    private function runTenantMigrationsIfNeeded(): void
    {
        $tenantConnection = config('multitenancy.tenant_database_connection_name') ?? config('database.default');

        if (! is_string($tenantConnection) || $tenantConnection === '') {
            throw new InvalidArgumentException('Tenant connection name is not configured.');
        }

        if (Schema::connection($tenantConnection)->hasTable('users')) {
            return;
        }

        Artisan::call('migrate', [
            '--database' => $tenantConnection,
            '--path' => 'database/migrations',
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }
}
