<?php

use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Artisan;

/*
 * `integration:status` é comando de diagnóstico — precisa rodar onde não há
 * terminal (cron, CI, `docker compose exec -T`). Sem --tenant ele abre um
 * select() e morre com NonInteractiveValidationException.
 */

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

function makeStatusTenant(string $slug): Tenant
{
    $tenant = Tenant::withoutEvents(function () use ($slug): Tenant {
        return Tenant::query()->create([
            'name' => strtoupper($slug),
            'slug' => $slug,
            'database' => (string) config('database.connections.tenant.database'),
            'status' => 'active',
        ]);
    });

    $api = IntegrationApi::query()->create([
        'name' => strtoupper($slug),
        'slug' => $slug,
        'requests' => ['method' => 'GET', 'paths' => ['products' => ['target_table' => 'products']]],
        'response' => ['items_path' => 'data'],
        'is_active' => true,
    ]);

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => ['connection' => ['base_url' => 'https://erp.status.test']],
        'is_active' => true,
    ]);

    return $tenant;
}

test('roda sem prompt quando o tenant é passado pelo slug', function (): void {
    $tenant = makeStatusTenant('status-slug');

    $this->artisan('integration:status', ['--tenant' => 'status-slug'])
        ->expectsOutputToContain($tenant->name)
        ->assertSuccessful();
});

test('aceita o id do tenant', function (): void {
    $tenant = makeStatusTenant('status-id');

    $this->artisan('integration:status', ['--tenant' => (string) $tenant->id])
        ->expectsOutputToContain($tenant->name)
        ->assertSuccessful();
});

test('avisa quando o tenant não existe, sem estourar', function (): void {
    makeStatusTenant('status-outro');

    $this->artisan('integration:status', ['--tenant' => 'inexistente'])
        ->expectsOutputToContain('Tenant não encontrado: inexistente')
        ->assertSuccessful();
});

test('nunca cai no caminho de exclusão em modo não-interativo', function (): void {
    makeStatusTenant('status-seguro');

    // O picker de exclusão pede loja e confirmação; se o comando escolhesse
    // 'delete' sozinho, morreria no prompt — ou pior, apagaria dado.
    $this->artisan('integration:status', ['--tenant' => 'status-seguro'])
        ->doesntExpectOutputToContain('Excluir registros')
        ->assertSuccessful();
});
