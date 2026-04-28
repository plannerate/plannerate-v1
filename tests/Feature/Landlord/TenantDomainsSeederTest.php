<?php

use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Support\Modules\ModuleSlug;
use Database\Seeders\TenantDomainsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'multitenancy.switch_tenant_tasks' => [],
        'services.sysmo.tenants.bruda.auth_password' => 'bruda-secret',
        'services.sysmo.tenants.franciosi.auth_password' => 'franciosi-secret',
    ]);
    Queue::fake([ProvisionTenantDatabaseJob::class]);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('tenant domains seeder provisions kanban plan module and sysmo integrations for bruda and franciosi', function (): void {
    Artisan::call('db:seed', [
        '--class' => TenantDomainsSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => TenantDomainsSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);

    $plan = Plan::query()->where('slug', 'plano-kanban')->firstOrFail();
    $module = Module::query()->where('slug', ModuleSlug::KANBAN)->firstOrFail();

    expect($plan->name)->toBe('Plano Kanban')
        ->and($plan->is_active)->toBeTrue()
        ->and($module->name)->toBe('Kanban')
        ->and($module->is_active)->toBeTrue();

    $tenants = Tenant::query()
        ->with(['modules', 'integration'])
        ->whereIn('slug', ['bruda', 'franciosi'])
        ->orderBy('slug')
        ->get()
        ->keyBy('slug');

    expect($tenants)->toHaveCount(2);

    expect($tenants['bruda']->modules->pluck('slug'))->toContain(ModuleSlug::KANBAN)
        ->and($tenants['franciosi']->modules->pluck('slug'))->toContain(ModuleSlug::KANBAN);

    assertSysmoIntegration(
        integration: $tenants['bruda']->integration,
        identifier: '79645404000869',
        apiUrl: 'https://brudaweb.sysmo.com.br:8443',
        username: 'gomark',
        password: 'bruda-secret',
    );

    assertSysmoIntegration(
        integration: $tenants['franciosi']->integration,
        identifier: '10623678000184',
        apiUrl: 'https://s1mobilefranciosi.sysmo.com.br:8443',
        username: 'proplanner',
        password: 'franciosi-secret',
    );

    expect(TenantIntegration::query()->count())->toBe(2);
});

function assertSysmoIntegration(
    ?TenantIntegration $integration,
    string $identifier,
    string $apiUrl,
    string $username,
    string $password,
): void {
    expect($integration)->toBeInstanceOf(TenantIntegration::class)
        ->and($integration->integration_type)->toBe('sysmo')
        ->and($integration->identifier)->toBe($identifier)
        ->and($integration->external_name)->toBe('produto')
        ->and($integration->http_method)->toBe('POST')
        ->and($integration->api_url)->toBe($apiUrl)
        ->and($integration->is_active)->toBeTrue()
        ->and($integration->authentication_headers)->toMatchArray([
            'auth_username' => $username,
            'auth_password' => $password,
        ])
        ->and($integration->authentication_body)->toMatchArray([
            'partner_key' => 'Proplanner',
            'empresa' => $identifier,
        ])
        ->and($integration->config['processing'])->toMatchArray([
            'days_to_maintain' => 120,
            'sales_initial_days' => 120,
            'products_initial_days' => 120,
            'daily_lookback_days' => 7,
            'sales_page_size' => 20000,
            'products_page_size' => 1000,
            'sales_tipo_consulta' => 'produto',
            'auto_processing_enabled' => true,
            'processing_time' => '02:00',
        ])
        ->and($integration->config['auth'])->toMatchArray([
            'type' => 'basic',
            'credentials' => [
                'username' => $username,
                'password' => $password,
            ],
        ])
        ->and($integration->config['connection'])->toMatchArray([
            'base_url' => $apiUrl,
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify_ssl' => true,
            'ping_path' => '/',
            'ping_method' => 'GET',
            'headers' => [],
        ]);
}
