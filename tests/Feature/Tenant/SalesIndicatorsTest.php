<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\Models\Sale;
use Callcocam\LaravelRaptorPlannerate\Sales\SalesFilters;
use Callcocam\LaravelRaptorPlannerate\Sales\SalesSummaryService;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

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
 * Provisiona um tenant de teste com schema migrado e contexto tenant ativo.
 */
if (! function_exists('setupIndicatorsTenant')) {
    function setupIndicatorsTenant(string $subdomain, User $user): Tenant
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
 * Cria um produto mínimo casado às vendas por codigo_erp.
 */
function makeIndicatorProduct(Tenant $tenant, string $codigoErp, string $ean): Product
{
    return Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto '.$codigoErp,
        'slug' => 'produto-'.strtolower($codigoErp),
        'ean' => $ean,
        'codigo_erp' => $codigoErp,
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);
}

test('indicadores agregam preço e margem por unidade keyed por EAN', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $tenant = setupIndicatorsTenant('tenant-indicators', $user);

    $p1 = makeIndicatorProduct($tenant, 'IND-1', '7890000000017');

    // Duas vendas → soma: qtde 100, valor 1000, custo 600, margem 200.
    // avg_price = 1000/100 = 10; net_margin_pct = 200/1000*100 = 20; gross = (1000-600)/1000*100 = 40.
    Sale::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'IND-1', 'sale_date' => '2026-04-10', 'total_sale_quantity' => '60.000', 'total_sale_value' => '600.00', 'acquisition_cost' => '360.00', 'margem_contribuicao' => '120.00']);
    Sale::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'IND-1', 'sale_date' => '2026-04-12', 'total_sale_quantity' => '40.000', 'total_sale_value' => '400.00', 'acquisition_cost' => '240.00', 'margem_contribuicao' => '80.00']);

    $results = (new SalesSummaryService)->indicatorsForProductIds(
        [$p1->id],
        new SalesFilters(saleDateFrom: '2026-04-01', saleDateTo: '2026-04-30'),
    );

    $byEan = collect($results)->keyBy('ean');

    expect($byEan)->toHaveKey('7890000000017')
        ->and($byEan['7890000000017']['avg_price'])->toBe(10.0)
        // avg_cost = 600/100 = 6; avg_margin = 200/100 = 2 (por unidade).
        ->and($byEan['7890000000017']['avg_cost'])->toBe(6.0)
        ->and($byEan['7890000000017']['avg_margin'])->toBe(2.0)
        ->and($byEan['7890000000017']['net_margin_pct'])->toBe(20.0)
        ->and($byEan['7890000000017']['gross_margin_pct'])->toBe(40.0);
});

test('indicadores respeitam o período do planograma', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $tenant = setupIndicatorsTenant('tenant-indicators-period', $user);

    $p1 = makeIndicatorProduct($tenant, 'INDP-1', '7890000000024');

    // Venda dentro do período (abril) e fora (março). Só a de abril deve contar.
    Sale::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'INDP-1', 'sale_date' => '2026-04-10', 'total_sale_quantity' => '10.000', 'total_sale_value' => '100.00', 'acquisition_cost' => '50.00', 'margem_contribuicao' => '30.00']);
    Sale::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'INDP-1', 'sale_date' => '2026-03-10', 'total_sale_quantity' => '999.000', 'total_sale_value' => '9990.00', 'acquisition_cost' => '5000.00', 'margem_contribuicao' => '5000.00']);

    $results = (new SalesSummaryService)->indicatorsForProductIds(
        [$p1->id],
        new SalesFilters(saleDateFrom: '2026-04-01', saleDateTo: '2026-04-30'),
    );

    $row = collect($results)->firstWhere('ean', '7890000000024');

    // avg_price = 100/10 = 10 (a venda de março, com preço 10 também, foi excluída pelo período).
    expect($row['avg_price'])->toBe(10.0)
        ->and($row['net_margin_pct'])->toBe(30.0);
});

test('indicadores retornam vazio quando não há produtos', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    setupIndicatorsTenant('tenant-indicators-empty', $user);

    $results = (new SalesSummaryService)->indicatorsForProductIds([], new SalesFilters);

    expect($results)->toBe([]);
});
