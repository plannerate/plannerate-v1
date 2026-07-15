<?php

use App\Jobs\ProvisionTenantDatabaseJob;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/*
 * O resumo de vendas do produto exibido no sidebar do editor (ProductSalesController::summary)
 * somava vendas de TODAS as lojas do tenant quando chamado a partir da gôndola em edição —
 * mesma classe de bug já corrigida em GondolaAnalysisController::buildFilters(). A composable
 * do front (useProductSales.ts) agora sempre envia gondola_id; o controller resolve a loja do
 * planograma dessa gôndola e restringe summary/by_month a ela, mantendo top_stores sem esse
 * filtro (propositalmente, para servir de comparação entre lojas).
 */

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    config()->set('permission.rbac_enabled', true);
    Queue::fake([ProvisionTenantDatabaseJob::class]);
    app()->forgetInstance((string) config('multitenancy.current_tenant_container_key', 'currentTenant'));

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

function setupProductSalesScopeTenant(string $subdomain, User $user): Tenant
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

test('resumo de vendas do produto restringe à loja da gôndola quando gondola_id é informado', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = setupProductSalesScopeTenant('tenant-sales-store-scope', $user);

    $storeA = Store::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Loja A']);
    $storeB = Store::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Loja B']);

    $planogram = Planogram::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $storeA->id,
        'cluster_id' => null,
    ]);
    $gondola = Gondola::factory()->create([
        'tenant_id' => $tenant->id,
        'planogram_id' => $planogram->id,
    ]);

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Multi-loja',
        'slug' => 'produto-multi-loja',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);

    // Loja A: 5 unidades, R$ 65 — a loja da gôndola em edição.
    Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'store_id' => $storeA->id,
        'sale_date' => '2026-04-23',
        'total_sale_quantity' => '5.000',
        'total_sale_value' => '65.00',
        'acquisition_cost' => '29.00',
        'margem_contribuicao' => '18.00',
    ]);

    // Loja B: 100 unidades, R$ 1000 — não deveria entrar no resumo desta gôndola.
    Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'store_id' => $storeB->id,
        'sale_date' => '2026-04-23',
        'total_sale_quantity' => '100.000',
        'total_sale_value' => '1000.00',
        'acquisition_cost' => '400.00',
        'margem_contribuicao' => '300.00',
    ]);

    $host = 'tenant-sales-store-scope.'.config('app.landlord_domain');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->getJson(route('api.plannerate.products.sales.summary', [
            'subdomain' => 'tenant-sales-store-scope',
            'product' => $product->id,
            'gondola_id' => $gondola->id,
        ], false));

    $response->assertOk();

    $summary = $response->json('summary');

    // Só a venda da Loja A deve compor o resumo: qtd 5, faturamento 65 — não 105/1065.
    expect((int) $summary['total_quantity'])->toBe(5)
        ->and(round((float) $summary['total_revenue'], 2))->toBe(65.0);

    // top_stores continua sem o filtro de loja de propósito: mostra as duas.
    $topStoreNames = collect($response->json('top_stores'))->pluck('store_name')->sort()->values()->all();
    expect($topStoreNames)->toBe(['Loja A', 'Loja B']);
});

test('resumo de vendas do produto soma todas as lojas quando gondola_id não é informado', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = setupProductSalesScopeTenant('tenant-sales-no-scope', $user);

    $storeA = Store::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Loja A']);
    $storeB = Store::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Loja B']);

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Sem Gôndola',
        'slug' => 'produto-sem-gondola',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);

    Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'store_id' => $storeA->id,
        'sale_date' => '2026-04-23',
        'total_sale_quantity' => '5.000',
        'total_sale_value' => '65.00',
        'acquisition_cost' => '29.00',
        'margem_contribuicao' => '18.00',
    ]);

    Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'store_id' => $storeB->id,
        'sale_date' => '2026-04-23',
        'total_sale_quantity' => '100.000',
        'total_sale_value' => '1000.00',
        'acquisition_cost' => '400.00',
        'margem_contribuicao' => '300.00',
    ]);

    $host = 'tenant-sales-no-scope.'.config('app.landlord_domain');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->getJson(route('api.plannerate.products.sales.summary', [
            'subdomain' => 'tenant-sales-no-scope',
            'product' => $product->id,
        ], false));

    $response->assertOk();

    $summary = $response->json('summary');

    // Sem gondola_id (chamada fora do editor), comportamento antigo é preservado: soma tudo.
    expect((int) $summary['total_quantity'])->toBe(105)
        ->and(round((float) $summary['total_revenue'], 2))->toBe(1065.0);
});
