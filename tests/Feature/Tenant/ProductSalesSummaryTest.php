<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
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

test('sales summary returns per-unit average price and cost (divided by quantity)', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenant('tenant-sales-summary');
    assignTenantAdminRole($user, $tenant->id);

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Sales Summary',
        'slug' => 'produto-sales-summary',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);

    // Duas vendas com quantidades diferentes para validar a média ponderada por unidade.
    // sale_price/acquisition_cost são totais da linha (não unitários), por isso a média
    // correta divide a soma dos valores pela soma das quantidades.
    Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
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
        'sale_date' => '2026-04-24',
        'total_sale_quantity' => '3.000',
        'total_sale_value' => '45.00',
        'sale_price' => '45.00',
        'acquisition_cost' => '21.00',
        'margem_contribuicao' => '12.00',
    ]);

    $host = 'tenant-sales-summary.'.config('app.landlord_domain');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->getJson(route('api.plannerate.products.sales.summary', [
            'subdomain' => 'tenant-sales-summary',
            'product' => $product->id,
        ], false));

    $response->assertOk();

    $summary = $response->json('summary');

    // total: qtd = 5, faturamento = 65
    expect((int) $summary['total_quantity'])->toBe(5);
    expect(round((float) $summary['total_revenue'], 2))->toBe(65.0);
    // preço unitário = 65 / 5 = 13,00
    expect(round((float) $summary['avg_price'], 2))->toBe(13.0);
    // custo unitário = (8 + 21) / 5 = 5,80
    expect(round((float) $summary['avg_cost'], 2))->toBe(5.8);
    // margem unitária = (6 + 12) / 5 = 3,60
    expect(round((float) $summary['avg_margin'], 2))->toBe(3.6);
});

test('sales summary filters by planogram period (start_date/end_date)', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenant('tenant-sales-period');
    assignTenantAdminRole($user, $tenant->id);

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Período',
        'slug' => 'produto-periodo',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);

    // Dentro do período do planograma.
    Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'sale_date' => '2026-02-10',
        'total_sale_quantity' => '4.000',
        'total_sale_value' => '40.00',
        'sale_price' => '40.00',
        'acquisition_cost' => '16.00',
        'margem_contribuicao' => '10.00',
    ]);

    // Fora do período — não deve entrar no resumo.
    Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'sale_date' => '2025-08-01',
        'total_sale_quantity' => '10.000',
        'total_sale_value' => '999.00',
        'sale_price' => '999.00',
        'acquisition_cost' => '500.00',
        'margem_contribuicao' => '200.00',
    ]);

    $host = 'tenant-sales-period.'.config('app.landlord_domain');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->getJson(route('api.plannerate.products.sales.summary', [
            'subdomain' => 'tenant-sales-period',
            'product' => $product->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
        ], false));

    $response->assertOk();

    $summary = $response->json('summary');

    // Apenas a venda dentro do período: qtd = 4, faturamento = 40, preço unit = 10,00
    expect((int) $summary['total_quantity'])->toBe(4);
    expect(round((float) $summary['total_revenue'], 2))->toBe(40.0);
    expect(round((float) $summary['avg_price'], 2))->toBe(10.0);
});

test('sales summary includes sales linked only by codigo_erp or ean (product_id null)', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tenant = makeTenant('tenant-sales-erp');
    assignTenantAdminRole($user, $tenant->id);

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto ERP',
        'slug' => 'produto-erp',
        'ean' => '7891150095953',
        'codigo_erp' => '95616',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);

    // Venda vinculada pelo product_id.
    Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'ean' => '7891150095953',
        'codigo_erp' => '95616',
        'sale_date' => '2026-03-10',
        'total_sale_quantity' => '1.000',
        'total_sale_value' => '8.99',
    ]);

    // Venda da integração SEM product_id, vinculada apenas pelo codigo_erp.
    Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => null,
        'codigo_erp' => '95616',
        'sale_date' => '2026-05-29',
        'total_sale_quantity' => '1.000',
        'total_sale_value' => '8.99',
    ]);

    $host = 'tenant-sales-erp.'.config('app.landlord_domain');

    $response = $this
        ->withServerVariables(['HTTP_HOST' => $host])
        ->getJson(route('api.plannerate.products.sales.summary', [
            'subdomain' => 'tenant-sales-erp',
            'product' => $product->id,
        ], false));

    $response->assertOk();

    $summary = $response->json('summary');

    // Ambas as vendas devem ser contadas (product_id + codigo_erp).
    expect((int) $summary['total_sales'])->toBe(2);
    expect((int) $summary['total_quantity'])->toBe(2);
    expect(round((float) $summary['total_revenue'], 2))->toBe(17.98);
});
