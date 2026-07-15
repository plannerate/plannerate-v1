<?php

use App\Models\IntegrationApi;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\Models\Sale;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    config()->set('permission.rbac_enabled', true);

    Artisan::call('migrate', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => LandlordRbacSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

if (! function_exists('setupSyncSingleTenant')) {
    function setupSyncSingleTenant(string $subdomain, User $user): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => strtoupper($subdomain),
            'slug' => $subdomain,
            'database' => (string) config('database.connections.'.config('database.default').'.database'),
            'status' => 'active',
        ]);

        $tenant->domains()->create([
            'host' => $subdomain.'.'.config('app.landlord_domain'),
            'type' => 'subdomain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        app()->instance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'), $tenant);

        if (! Schema::connection('tenant')->hasTable('products')) {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations',
                '--force' => true,
                '--no-interaction' => true,
            ]);
        }

        $role = Role::query()->where('system_name', 'tenant-admin')->firstOrFail();
        setPermissionsTeamId($tenant->id);
        $user->assignRole($role);

        return $tenant;
    }
}

/**
 * Blueprint estilo Sysmo com o bloco `lookups` (busca pontual) — o que o
 * SingleProductFetchService consome.
 */
if (! function_exists('sysmoLikeBlueprintRequests')) {
    function sysmoLikeBlueprintRequests(): array
    {
        return [
            'method' => 'POST',
            'page_field' => 'pagina',
            'page_size_field' => 'tamanho_pagina',
            'max_page_size' => 1000,
            'lookups' => [
                'product' => [
                    'target_table' => 'products',
                    'fallback_path' => '/hubprodutos.consultar_produto',
                    'method' => 'post',
                    'lookup_field' => 'produto',
                    'lookup_key' => 'codigo_erp',
                    'store_field' => 'empresa',
                    'store_key' => 'code',
                    'single_item' => true,
                    'extra_params' => ['somente_precos' => 'N'],
                    'response' => ['items_path' => ''],
                    'unique_by' => ['ean'],
                    'field_map' => [
                        ['target' => 'name', 'source' => 'descricao', 'transforms' => ['string']],
                        ['target' => 'codigo_erp', 'source' => 'produto', 'transforms' => ['string', 'alnum', 'not_null']],
                        ['target' => 'ean', 'source' => 'gtins.completo[principal=S].gtin', 'transforms' => ['first', 'ean', 'not_null']],
                        ['target' => 'current_stock', 'source' => 'estoque.disponivel', 'transforms' => ['decimal']],
                        ['target' => 'brand', 'source' => 'marca.descricao', 'transforms' => ['string']],
                    ],
                ],
                'sales' => [
                    'target_table' => 'sales',
                    'fallback_path' => '/hubvendas.vendas_produtos',
                    'method' => 'post',
                    'lookup_field' => 'produto',
                    'lookup_key' => 'ean',
                    'store_field' => 'empresa',
                    'store_key' => 'document',
                    'extra_params' => ['tipo_consulta' => 'produto'],
                    'date_fields' => ['start' => 'data_inicial', 'end' => 'data_final'],
                    'initial_days' => 200,
                    'response' => ['items_path' => 'dados'],
                    'unique_by' => ['codigo_erp', 'sale_date', 'promotion'],
                    'include_store_in_id' => true,
                    'field_map' => [
                        ['target' => 'codigo_erp', 'source' => 'produto', 'transforms' => ['string', 'not_null']],
                        ['target' => 'sale_date', 'source' => 'data_venda', 'transforms' => ['date', 'not_null']],
                        ['target' => 'promotion', 'source' => 'promocao', 'transforms' => ['string']],
                        ['target' => 'total_sale_quantity', 'source' => 'quantidade', 'transforms' => ['decimal']],
                        ['target' => 'total_sale_value', 'source' => 'valor_liquido', 'transforms' => ['decimal']],
                        ['target' => 'acquisition_cost', 'source' => 'custo_aquisicao', 'transforms' => ['decimal']],
                    ],
                ],
            ],
        ];
    }
}

if (! function_exists('activeSyncIntegration')) {
    function activeSyncIntegration(Tenant $tenant): TenantIntegration
    {
        $api = IntegrationApi::query()->create([
            'name' => 'Sysmo',
            'slug' => 'sysmo',
            'requests' => sysmoLikeBlueprintRequests(),
            'response' => ['items_path' => 'dados'],
            'is_active' => true,
        ]);

        return TenantIntegration::query()->create([
            'tenant_id' => $tenant->id,
            'integration_type' => $api->id,
            'is_active' => true,
            'config' => [
                'auth' => [
                    'type' => 'basic',
                    'credentials' => ['username' => 'u', 'password' => 'p'],
                ],
                'connection' => [
                    'base_url' => 'https://api.sysmo.test',
                    'body' => [
                        ['key' => 'partner_key', 'value' => 'TESTE', 'enabled' => true],
                    ],
                ],
            ],
        ]);
    }
}

test('sincroniza produto e vendas da API do tenant e grava (upsert)', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = setupSyncSingleTenant('tenant-sync-single', $user);
    activeSyncIntegration($tenant);

    $store = Store::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Loja Centro',
        'status' => 'published',
        'code' => '73',
        'document' => '12345678000199',
    ]);

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Original',
        'slug' => 'produto-original',
        'status' => 'published',
        'ean' => '7891234567895',
        'codigo_erp' => '66526',
    ]);

    $product->stores()->attach($store->id, [
        'id' => (string) Str::ulid(),
        'tenant_id' => $tenant->id,
    ]);

    Http::fake([
        'https://api.sysmo.test/hubprodutos.consultar_produto' => Http::response([
            'descricao' => 'PRODUTO API ATUALIZADO',
            'produto' => '66526',
            'gtins' => ['completo' => [['principal' => 'S', 'gtin' => '7891234567895']]],
            'estoque' => ['disponivel' => '42'],
            'marca' => ['descricao' => 'MARCA X'],
        ], 200),
        'https://api.sysmo.test/hubvendas.vendas_produtos' => Http::response([
            'dados' => [
                ['produto' => '66526', 'data_venda' => '2026-06-01', 'promocao' => 'N', 'quantidade' => '5', 'valor_liquido' => '59.90', 'custo_aquisicao' => '23.30'],
                ['produto' => '66526', 'data_venda' => '2026-06-02', 'promocao' => 'S', 'quantidade' => '3', 'valor_liquido' => '29.90', 'custo_aquisicao' => '12.10'],
            ],
            'total_paginas' => '1',
        ], 200),
    ]);

    $host = 'tenant-sync-single.'.config('app.landlord_domain');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.products.sync-single', ['subdomain' => 'tenant-sync-single'], false), [
            'product' => $product->id,
        ]);

    $response->assertRedirect();

    // Produto reconciliado por EAN → mesma linha atualizada com dados da API.
    $product->refresh();
    expect($product->name)->toBe('PRODUTO API ATUALIZADO')
        ->and((float) $product->current_stock)->toBe(42.0);

    // Vendas gravadas na conexão do tenant, casadas por codigo_erp.
    $sales = Sale::query()->where('codigo_erp', '66526')->get();
    expect($sales)->toHaveCount(2)
        ->and($sales->pluck('store_id')->unique()->all())->toBe([$store->id]);
});

test('sem integração ativa: avisa e não grava nada', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = setupSyncSingleTenant('tenant-sync-none', $user);

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Original',
        'slug' => 'produto-original',
        'status' => 'published',
        'ean' => '7891234567895',
        'codigo_erp' => '66526',
    ]);

    Http::fake();

    $host = 'tenant-sync-none.'.config('app.landlord_domain');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->post(route('tenant.products.sync-single', ['subdomain' => 'tenant-sync-none'], false), [
            'product' => $product->id,
        ]);

    $response->assertRedirect();

    Http::assertNothingSent();

    $product->refresh();
    expect($product->name)->toBe('Produto Original')
        ->and(Sale::query()->count())->toBe(0);
});
