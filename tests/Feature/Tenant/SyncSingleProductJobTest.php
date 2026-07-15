<?php

use App\Events\Tenant\ProductSalesSynced;
use App\Jobs\Integrations\SyncSingleProductJob;
use App\Models\IntegrationApi;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Services\Integrations\Lookup\SingleProductFetchService;
use Callcocam\LaravelRaptorPlannerate\Models\Sale;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
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

if (! function_exists('setupSyncJobTenant')) {
    function setupSyncJobTenant(string $subdomain, User $user): Tenant
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
        $tenant->makeCurrent();

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

if (! function_exists('sysmoLookupsRequests')) {
    function sysmoLookupsRequests(): array
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
                    // Sem store_transform de propósito: `document` normaliza p/ dígitos por padrão.
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

if (! function_exists('makeSyncIntegration')) {
    function makeSyncIntegration(Tenant $tenant, bool $active = true, ?array $requests = null): TenantIntegration
    {
        $api = IntegrationApi::query()->create([
            'name' => 'Sysmo',
            'slug' => 'sysmo-'.Str::lower(Str::random(6)),
            'requests' => $requests ?? sysmoLookupsRequests(),
            'response' => ['items_path' => 'dados'],
            'is_active' => true,
        ]);

        return TenantIntegration::query()->create([
            'tenant_id' => $tenant->id,
            'integration_type' => $api->id,
            'is_active' => $active,
            'config' => [
                'auth' => ['type' => 'basic', 'credentials' => ['username' => 'u', 'password' => 'p']],
                'connection' => [
                    'base_url' => 'https://api.sysmo.test',
                    'body' => [['key' => 'partner_key', 'value' => 'TESTE', 'enabled' => true]],
                ],
            ],
        ]);
    }
}

if (! function_exists('fakeSyncApi')) {
    function fakeSyncApi(): void
    {
        Http::fake([
            'https://api.sysmo.test/hubprodutos.consultar_produto' => Http::response([
                'descricao' => 'PRODUTO API ATUALIZADO',
                'produto' => '66526',
                'gtins' => ['completo' => [['principal' => 'S', 'gtin' => '7891234567895']]],
                'estoque' => ['disponivel' => '42'],
            ], 200),
            'https://api.sysmo.test/hubvendas.vendas_produtos' => Http::response([
                'dados' => [
                    ['produto' => '66526', 'data_venda' => '2026-06-01', 'promocao' => 'N', 'quantidade' => '5', 'valor_liquido' => '59.90', 'custo_aquisicao' => '23.30'],
                    ['produto' => '66526', 'data_venda' => '2026-06-02', 'promocao' => 'S', 'quantidade' => '3', 'valor_liquido' => '29.90', 'custo_aquisicao' => '12.10'],
                ],
                'total_paginas' => '1',
            ], 200),
        ]);
    }
}

if (! function_exists('makeSyncProductAndStore')) {
    /** @return array{0: Product, 1: Store} */
    function makeSyncProductAndStore(Tenant $tenant): array
    {
        $store = Store::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Loja Centro',
            'status' => 'published',
            'code' => '73',
            // CNPJ formatado de propósito: o lookup deve normalizar para só dígitos.
            'document' => '12.345.678/0001-99',
        ]);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Produto Original',
            'slug' => 'produto-original',
            'status' => 'published',
            'ean' => '7891234567895',
            'codigo_erp' => '66526',
        ]);

        return [$product, $store];
    }
}

test('job busca vendas na loja e NÃO atualiza produto por padrão', function (): void {
    $user = User::factory()->create();
    $tenant = setupSyncJobTenant('tenant-sync-job', $user);
    makeSyncIntegration($tenant);
    [$product, $store] = makeSyncProductAndStore($tenant);

    fakeSyncApi();
    Event::fake([ProductSalesSynced::class]);

    (new SyncSingleProductJob($tenant->id, $product->id, $store->id, false, $user->id))
        ->handle(app(SingleProductFetchService::class));

    // Vendas gravadas, casadas por codigo_erp e vinculadas à loja escolhida.
    $sales = Sale::query()->where('codigo_erp', '66526')->get();
    expect($sales)->toHaveCount(2)
        ->and($sales->pluck('store_id')->unique()->all())->toBe([$store->id]);

    // CNPJ enviado sem formatação (store_transform: digits) — a causa do HTTP 400 real.
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.sysmo.test/hubvendas.vendas_produtos'
        && ($request->data()['empresa'] ?? null) === '12345678000199');

    // Produto NÃO atualizado (update_product = false).
    $product->refresh();
    expect($product->name)->toBe('Produto Original');

    Event::assertDispatched(ProductSalesSynced::class, function (ProductSalesSynced $event) use ($tenant, $product): bool {
        return $event->tenantId === $tenant->id
            && $event->productId === $product->id
            && $event->status === 'success'
            && $event->sales === 2
            && $event->products === 0;
    });
});

test('job atualiza dados do produto quando update_product = true', function (): void {
    $user = User::factory()->create();
    $tenant = setupSyncJobTenant('tenant-sync-job-prod', $user);
    makeSyncIntegration($tenant);
    [$product, $store] = makeSyncProductAndStore($tenant);

    fakeSyncApi();
    Event::fake([ProductSalesSynced::class]);

    (new SyncSingleProductJob($tenant->id, $product->id, $store->id, true, $user->id))
        ->handle(app(SingleProductFetchService::class));

    // Produto reconciliado por EAN → mesma linha atualizada com os dados da API.
    $product->refresh();
    expect($product->name)->toBe('PRODUTO API ATUALIZADO')
        ->and((float) $product->current_stock)->toBe(42.0);

    Event::assertDispatched(ProductSalesSynced::class, function (ProductSalesSynced $event): bool {
        return $event->status === 'success' && $event->products >= 1 && $event->sales === 2;
    });
});

test('job usa as datas do formulário quando informadas', function (): void {
    $user = User::factory()->create();
    $tenant = setupSyncJobTenant('tenant-sync-job-dates', $user);
    makeSyncIntegration($tenant);
    [$product, $store] = makeSyncProductAndStore($tenant);

    fakeSyncApi();
    Event::fake([ProductSalesSynced::class]);

    (new SyncSingleProductJob($tenant->id, $product->id, $store->id, false, $user->id, '2026-03-01', '2026-03-31'))
        ->handle(app(SingleProductFetchService::class));

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.sysmo.test/hubvendas.vendas_produtos'
        && ($request->data()['data_inicial'] ?? null) === '2026-03-01'
        && ($request->data()['data_final'] ?? null) === '2026-03-31');
});

test('job transmite failed quando não há integração ativa', function (): void {
    $user = User::factory()->create();
    $tenant = setupSyncJobTenant('tenant-sync-job-noint', $user);
    makeSyncIntegration($tenant, active: false);
    [$product, $store] = makeSyncProductAndStore($tenant);

    Http::fake();
    Event::fake([ProductSalesSynced::class]);

    (new SyncSingleProductJob($tenant->id, $product->id, $store->id, false, $user->id))
        ->handle(app(SingleProductFetchService::class));

    Http::assertNothingSent();
    expect(Sale::query()->count())->toBe(0);

    Event::assertDispatched(ProductSalesSynced::class, fn (ProductSalesSynced $event): bool => $event->status === 'failed');
});
