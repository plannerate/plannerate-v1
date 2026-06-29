<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\Models\Store;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

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

/**
 * Provisiona um tenant de teste com o schema migrado e o usuário como tenant-admin.
 * Autossuficiente: migra o schema tenant (:memory:) quando ainda não existe, sem
 * depender da ordem de execução de outros arquivos de teste.
 */
if (! function_exists('setupSalesDashboardTenant')) {
    function setupSalesDashboardTenant(string $subdomain, User $user): Tenant
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
 * Garante que a página de vendas (ProductController::sales) entrega os totais
 * derivados já calculados pelo backend (SalesSummary), sem depender de cálculo
 * no frontend — provando que o mini-dashboard e o card lateral do editor
 * compartilham a mesma fonte de verdade.
 */
test('product sales page returns backend-derived totals (single source of truth)', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = setupSalesDashboardTenant('tenant-sales-dash', $user);

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Dashboard',
        'slug' => 'produto-dashboard',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);

    // qtd = 5, faturamento = 65, custo = 29, margem = 18; 1 de 2 registros em promoção.
    Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'sale_date' => '2026-04-23',
        'promotion' => 'S',
        'total_sale_quantity' => '2.000',
        'total_sale_value' => '20.00',
        'sale_price' => '20.00',
        'acquisition_cost' => '8.00',
        'margem_contribuicao' => '6.00',
    ]);

    Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'sale_date' => '2026-04-24',
        'promotion' => 'N',
        'total_sale_quantity' => '3.000',
        'total_sale_value' => '45.00',
        'sale_price' => '45.00',
        'acquisition_cost' => '21.00',
        'margem_contribuicao' => '12.00',
    ]);

    $host = 'tenant-sales-dash.'.config('app.landlord_domain');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->get(route('tenant.products.sales', [
            'subdomain' => 'tenant-sales-dash',
            'product' => $product->id,
        ], false));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('tenant/products/Sales')
        ->where('totals.total_records', 2)
        // derivados prontos do backend — preço médio por unidade = 65 / 5 = 13,00
        // (valores inteiros são serializados sem casa decimal no JSON)
        ->where('totals.avg_price', 13)
        // custo médio = (8 + 21) / 5 = 5,80
        ->where('totals.avg_cost', 5.8)
        // lucro bruto total = 65 − 29 = 36,00
        ->where('totals.gross_profit_total', 36)
        // 1 de 2 registros em promoção = 50%
        ->where('totals.promo_percent', 50)
    );
});

/**
 * O endpoint JSON do editor (ProductSalesController::summary) compartilha a mesma
 * fonte de verdade (SalesSummaryService) e agora expõe também os derivados de lucro
 * bruto, além de by_month e top_stores com o nome real da loja.
 */
test('editor sales summary endpoint shares the same derived metrics', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = setupSalesDashboardTenant('tenant-editor-summary', $user);

    $store = Store::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Loja Centro',
        'status' => 'published',
    ]);

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Editor',
        'slug' => 'produto-editor',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);

    Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'store_id' => $store->id,
        'sale_date' => '2026-04-23',
        'total_sale_quantity' => '2.000',
        'total_sale_value' => '20.00',
        'sale_price' => '20.00',
        'acquisition_cost' => '8.00',
        'margem_contribuicao' => '6.00',
    ]);

    Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'store_id' => $store->id,
        'sale_date' => '2026-04-24',
        'total_sale_quantity' => '3.000',
        'total_sale_value' => '45.00',
        'sale_price' => '45.00',
        'acquisition_cost' => '21.00',
        'margem_contribuicao' => '12.00',
    ]);

    $host = 'tenant-editor-summary.'.config('app.landlord_domain');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->getJson(route('api.plannerate.products.sales.summary', [
            'subdomain' => 'tenant-editor-summary',
            'product' => $product->id,
        ], false));

    $response->assertOk();

    $summary = $response->json('summary');

    // Mesmos derivados do mini-dashboard: preço unit = 13, custo unit = 5,8, lucro bruto total = 36.
    expect(round((float) $summary['avg_price'], 2))->toBe(13.0)
        ->and(round((float) $summary['avg_cost'], 2))->toBe(5.8)
        ->and(round((float) $summary['gross_profit_total'], 2))->toBe(36.0)
        ->and(round((float) $summary['gross_profit_unit'], 2))->toBe(7.2);

    // top_stores agora resolve o nome real da loja (antes caía em "Loja não encontrada").
    expect($response->json('top_stores.0.store_name'))->toBe('Loja Centro');
});
