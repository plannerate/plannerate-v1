<?php

use App\Support\Database\TenantConnectionSwitcher;
use Illuminate\Support\Facades\DB;

/**
 * Reproduz o estado que o worker de fila deixa antes de rodar um job NotTenantAware:
 * `Tenant::forgetCurrent()` → `SwitchTenantDatabaseTask::forgetCurrent()` registra uma
 * extension na conexão de tenant fixando `database = null`.
 */
function registerStaleTenantConnectionExtension(?string $database = null): void
{
    app('db')->extend('tenant', function (array $config, string $name) use ($database) {
        $config['database'] = $database;

        return app('db.factory')->make($config, $name);
    });

    DB::purge('tenant');
}

it('mostra que só mexer no config não vence a extension stale do DatabaseManager', function () {
    registerStaleTenantConnectionExtension();

    config(['database.connections.tenant.database' => 'testing_tenant_provision']);
    DB::purge('tenant');

    expect(DB::connection('tenant')->getDatabaseName())->toBeNull();
});

it('aponta a conexão de tenant para o banco alvo mesmo com extension stale registrada', function () {
    registerStaleTenantConnectionExtension();

    app(TenantConnectionSwitcher::class)->useDatabase('tenant', 'testing_tenant_provision');

    expect(DB::connection('tenant')->getDatabaseName())->toBe('testing_tenant_provision')
        ->and(config('database.connections.tenant.database'))->toBe('testing_tenant_provision');
});

it('restaura a conexão de tenant para o banco original', function () {
    registerStaleTenantConnectionExtension('testing_tenant_provision');

    app(TenantConnectionSwitcher::class)->useDatabase('tenant', null);

    expect(DB::connection('tenant')->getDatabaseName())->toBeNull()
        ->and(config('database.connections.tenant.database'))->toBeNull();
});
