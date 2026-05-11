<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
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
     * @var array<int, array{name: string, slug: string, host: string, database: string, admin_name: string, admin_email: string, module_slugs?: list<string>}>
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
        ],
        [
            'name' => 'Supermercado Franciosi',
            'slug' => 'franciosi',
            'host' => 'franciosi.plannerate-v1.test',
            'database' => 'tenant_franciosi',
            'admin_name' => 'Administrador Franciosi',
            'admin_email' => 'admin@franciosi.plannerate-v1.test',
            'module_slugs' => [ModuleSlug::KANBAN],
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
     * @param  array{name: string, slug: string, host: string, database: string, admin_name: string, admin_email: string, module_slugs?: list<string>}  $tenantDefinition
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
