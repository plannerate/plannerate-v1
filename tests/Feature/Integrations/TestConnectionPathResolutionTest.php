<?php

use App\Http\Controllers\Landlord\TenantIntegrationController;
use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

/*
 * "Testar conexão" precisa montar a MESMA chamada que o import faria.
 *
 * Antes ele concatenava o fallback_path cru: a URL saía com "{cursor}" e
 * "{store_document}" literais, e a resposta (401/404) não dizia nada sobre a
 * integração de verdade.
 */

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    // A autorização já é coberta pelos testes de CRUD do tenant; aqui o foco é
    // a montagem da requisição de teste.
    $this->actingAs(User::factory()->create());
    Gate::before(fn (): bool => true);
});

function makeTestConnectionTenant(string $slug): Tenant
{
    $tenant = Tenant::withoutEvents(function () use ($slug): Tenant {
        return Tenant::query()->create([
            'name' => strtoupper($slug),
            'slug' => $slug,
            'database' => (string) config('database.connections.landlord.database').'_'.$slug,
            'status' => 'active',
        ]);
    });

    $api = IntegrationApi::query()->create([
        'name' => strtoupper($slug),
        'slug' => $slug,
        'requests' => [
            'method' => 'GET',
            'pagination_mode' => 'cursor',
            'store_document_field' => 'unidade',
            'paths' => [
                'products' => [
                    'target_table' => 'products',
                    'fallback_path' => '/v3.2/produtos/{cursor}/unidade/{store_document}/detalhado',
                    'cursor_item_path' => 'Codigo',
                    'cursor_initial' => 0,
                    'field_map' => [],
                ],
                'sales' => [
                    'target_table' => 'sales',
                    'fallback_path' => '/v1.9/movimentos/lastid/{cursor}',
                    'cursor_item_path' => 'id',
                    'cursor_initial' => 0,
                    'date_fields' => ['start' => 'datainicial', 'end' => 'datafinal'],
                    'date_query_format' => 'd-m-Y',
                    'field_map' => [],
                ],
            ],
        ],
        'response' => ['items_path' => 'response.produtos'],
        'is_active' => true,
    ]);

    TenantIntegration::query()->create([
        'tenant_id' => $tenant->id,
        'integration_type' => $api->id,
        'config' => ['connection' => ['base_url' => 'https://erp.testpanel.test']],
        'is_active' => true,
    ]);

    return $tenant;
}

function runTestConnection(Tenant $tenant, string $pathKey): void
{
    $request = Request::create('/', 'POST', [
        'test_path_key' => $pathKey,
        'test_method' => 'get',
    ]);
    $request->setUserResolver(fn () => auth()->user());

    app(TenantIntegrationController::class)->testConnection($request, $tenant->refresh());
}

test('resolve {cursor} e {store_document} na URL de teste', function (): void {
    Http::fake(['erp.testpanel.test/*' => Http::response(['response' => ['produtos' => []]])]);

    $tenant = makeTestConnectionTenant('testpanel-url');

    // Sem loja publicada no tenant de teste, o documento fica nulo — o que
    // importa aqui é que o placeholder não sobrevive na URL.
    runTestConnection($tenant, 'products');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v3.2/produtos/0/unidade/')
        && ! str_contains($request->url(), '{cursor}')
        && ! str_contains($request->url(), '{store_document}'));
});

test('manda a janela de datas no formato da API para o path que exige', function (): void {
    Http::fake(['erp.testpanel.test/*' => Http::response(['response' => ['movimentos' => []]])]);

    $tenant = makeTestConnectionTenant('testpanel-dates');

    runTestConnection($tenant, 'sales');

    $hoje = now()->format('d-m-Y');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'datainicial='.$hoje)
        && str_contains($request->url(), 'datafinal='.$hoje));
});

test('path desconhecido cai no comportamento antigo, sem quebrar blueprints legados', function (): void {
    Http::fake(['erp.testpanel.test/*' => Http::response(['data' => []])]);

    $tenant = makeTestConnectionTenant('testpanel-legacy');

    $request = Request::create('/', 'POST', [
        'test_path' => '/endpoint-manual',
        'test_method' => 'get',
    ]);
    $request->setUserResolver(fn () => auth()->user());

    app(TenantIntegrationController::class)->testConnection($request, $tenant);

    Http::assertSent(fn ($r): bool => $r->url() === 'https://erp.testpanel.test/endpoint-manual');
});
