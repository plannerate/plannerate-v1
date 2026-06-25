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
