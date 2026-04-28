<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Support\Modules\ModuleSlug;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TenantDomainsSeeder extends Seeder
{
    /**
     * @var array{name: string, slug: string, description: string, price_cents: int, user_limit: int|null, is_active: bool}
     */
    private const KANBAN_PLAN = [
        'name' => 'Plano Kanban',
        'slug' => 'plano-kanban',
        'description' => 'Plano com acesso ao módulo Kanban.',
        'price_cents' => 0,
        'user_limit' => null,
        'is_active' => true,
    ];

    /**
     * @var array{name: string, slug: string, description: string, is_active: bool}
     */
    private const KANBAN_MODULE = [
        'name' => 'Kanban',
        'slug' => ModuleSlug::KANBAN,
        'description' => 'Quadro de workflow de planogramas.',
        'is_active' => true,
    ];

    /**
     * @var array<int, array{name: string, slug: string, host: string, database: string, admin_name: string, admin_email: string, module_slugs?: list<string>, integration?: array{integration_type: string, identifier: string, external_name: string, http_method: string, api_url: string, auth_username: string, auth_password_config: string, partner_key: string}}>
     */
    private const TENANTS = [
        [
            'name' => 'Supermercado Coperdia',
            'slug' => 'coperdia',
            'host' => 'coperdia.plannerate-v1.test',
            'database' => 'tenant_coperdia',
            'admin_name' => 'Administrador Coperdia',
            'admin_email' => 'admin@coperdia.plannerate-v1.test',
        ],
        [
            'name' => 'Supermercado Bruda',
            'slug' => 'bruda',
            'host' => 'bruda.plannerate-v1.test',
            'database' => 'tenant_bruda',
            'admin_name' => 'Administrador Bruda',
            'admin_email' => 'admin@bruda.plannerate-v1.test',
            'module_slugs' => [ModuleSlug::KANBAN],
            'integration' => [
                'integration_type' => 'sysmo',
                'identifier' => '79645404000869',
                'external_name' => 'produto',
                'http_method' => 'POST',
                'api_url' => 'https://brudaweb.sysmo.com.br:8443',
                'auth_username' => 'gomark',
                'auth_password_config' => 'services.sysmo.tenants.bruda.auth_password',
                'partner_key' => 'Proplanner',
            ],
        ],
        [
            'name' => 'Supermercado Franciosi',
            'slug' => 'franciosi',
            'host' => 'franciosi.plannerate-v1.test',
            'database' => 'tenant_franciosi',
            'admin_name' => 'Administrador Franciosi',
            'admin_email' => 'admin@franciosi.plannerate-v1.test',
            'module_slugs' => [ModuleSlug::KANBAN],
            'integration' => [
                'integration_type' => 'sysmo',
                'identifier' => '10623678000184',
                'external_name' => 'produto',
                'http_method' => 'POST',
                'api_url' => 'https://s1mobilefranciosi.sysmo.com.br:8443',
                'auth_username' => 'proplanner',
                'auth_password_config' => 'services.sysmo.tenants.franciosi.auth_password',
                'partner_key' => 'Proplanner',
            ],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedKanbanPlan();
        $this->seedKanbanModule();

        foreach (self::TENANTS as $tenantDefinition) {
            $this->seedTenant($tenantDefinition);
        }
    }

    /**
     * @param  array{name: string, slug: string, host: string, database: string, admin_name: string, admin_email: string, module_slugs?: list<string>, integration?: array{integration_type: string, identifier: string, external_name: string, http_method: string, api_url: string, auth_username: string, auth_password_config: string, partner_key: string}}  $tenantDefinition
     */
    private function seedTenant(array $tenantDefinition): void
    {
        $database = $tenantDefinition['database'];

        if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new InvalidArgumentException("Invalid tenant database name [{$database}].");
        }

        $this->createTenantDatabaseIfNeeded($database);

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => $tenantDefinition['slug']],
            [
                'name' => $tenantDefinition['name'],
                'database' => $database,
                'status' => 'active',
            ],
        );

        $this->syncTenantModules($tenant, $tenantDefinition['module_slugs'] ?? []);
        $this->seedTenantIntegration($tenant, $tenantDefinition['integration'] ?? null);

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

    private function seedKanbanPlan(): void
    {
        Plan::query()->updateOrCreate(
            ['slug' => self::KANBAN_PLAN['slug']],
            [
                'name' => self::KANBAN_PLAN['name'],
                'description' => self::KANBAN_PLAN['description'],
                'price_cents' => self::KANBAN_PLAN['price_cents'],
                'user_limit' => self::KANBAN_PLAN['user_limit'],
                'is_active' => self::KANBAN_PLAN['is_active'],
            ],
        );
    }

    private function seedKanbanModule(): void
    {
        Module::query()->updateOrCreate(
            ['slug' => self::KANBAN_MODULE['slug']],
            [
                'name' => self::KANBAN_MODULE['name'],
                'description' => self::KANBAN_MODULE['description'],
                'is_active' => self::KANBAN_MODULE['is_active'],
            ],
        );
    }

    private function createTenantDatabaseIfNeeded(string $database): void
    {
        if (app()->runningUnitTests() || app()->environment('testing') || config('app.env') === 'testing') {
            return;
        }

        $landlordConnection = DB::connection('landlord');

        if ($landlordConnection->getDatabaseName() === ':memory:') {
            return;
        }

        if (! in_array($landlordConnection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $landlordConnection->statement(sprintf('CREATE DATABASE IF NOT EXISTS `%s`', $database));
    }

    /**
     * @param  list<string>  $moduleSlugs
     */
    private function syncTenantModules(Tenant $tenant, array $moduleSlugs): void
    {
        if ($moduleSlugs === []) {
            return;
        }

        $moduleIds = Module::query()
            ->whereIn('slug', $moduleSlugs)
            ->pluck('id')
            ->all();

        $tenant->modules()->syncWithoutDetaching($moduleIds);
    }

    /**
     * @param  array{integration_type: string, identifier: string, external_name: string, http_method: string, api_url: string, auth_username: string, auth_password_config: string, partner_key: string}|null  $integrationDefinition
     */
    private function seedTenantIntegration(Tenant $tenant, ?array $integrationDefinition): void
    {
        if ($integrationDefinition === null) {
            return;
        }

        $password = (string) config($integrationDefinition['auth_password_config'], '');
        $identifier = $integrationDefinition['identifier'];
        $partnerKey = $integrationDefinition['partner_key'];
        $apiUrl = $integrationDefinition['api_url'];
        $username = $integrationDefinition['auth_username'];

        TenantIntegration::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'integration_type' => $integrationDefinition['integration_type'],
                'identifier' => $identifier,
                'external_name' => $integrationDefinition['external_name'],
                'external_name_ean' => null,
                'external_name_status' => null,
                'external_name_sale_date' => null,
                'http_method' => $integrationDefinition['http_method'],
                'api_url' => $apiUrl,
                'authentication_headers' => [
                    'auth_username' => $username,
                    'auth_password' => $password,
                ],
                'authentication_body' => [
                    'partner_key' => $partnerKey,
                    'empresa' => $identifier,
                ],
                'config' => [
                    'processing' => [
                        'days_to_maintain' => 120,
                        'sales_retention_days' => 120,
                        'sales_initial_days' => 120,
                        'products_initial_days' => 120,
                        'daily_lookback_days' => 7,
                        'sales_page_size' => 20000,
                        'products_page_size' => 1000,
                        'sales_tipo_consulta' => 'produto',
                        'partner_key' => $partnerKey,
                        'empresa' => $identifier,
                        'auto_processing_enabled' => true,
                        'processing_time' => '02:00',
                        'initial_setup_date' => null,
                    ],
                    'auth' => [
                        'type' => 'basic',
                        'credentials' => [
                            'username' => $username,
                            'password' => $password,
                        ],
                    ],
                    'connection' => [
                        'base_url' => $apiUrl,
                        'timeout' => 30,
                        'connect_timeout' => 10,
                        'verify_ssl' => true,
                        'ping_path' => '/',
                        'ping_method' => 'GET',
                        'headers' => [],
                    ],
                ],
                'is_active' => true,
            ],
        );
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
