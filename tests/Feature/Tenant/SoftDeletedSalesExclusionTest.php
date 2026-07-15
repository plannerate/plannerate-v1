<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\Models\Sale;
use Callcocam\LaravelRaptorPlannerate\Sales\SalesFilters;
use Callcocam\LaravelRaptorPlannerate\Sales\SalesSummaryService;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\TargetStockService;
use Database\Seeders\LandlordRbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/*
 * ProductSalesAggregateQuery::groupedByProduct() e as duas queries agregadas de
 * TargetStockService chamam ->withoutGlobalScopes() (necessário para o join bruto
 * em products por codigo_erp) mas isso também remove o filtro automático de
 * SoftDeletes do Eloquent — sem um whereNull('deleted_at') explícito, uma venda
 * OU um produto apagado (soft-delete) entrava nas somas/médias de ABC, BCG, Paper,
 * indicadores e estoque alvo. Este teste prova que uma venda soft-deleted não
 * contamina mais o resultado.
 */

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

function setupSoftDeleteExclusionTenant(string $subdomain, User $user): Tenant
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

test('indicadores por produto ignoram venda soft-deleted', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $tenant = setupSoftDeleteExclusionTenant('tenant-soft-del-indicators', $user);

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Soft Delete',
        'slug' => 'produto-soft-delete',
        'ean' => '7890000000123',
        'codigo_erp' => 'SD-1',
        'status' => 'published',
        'dimensions_status' => 'published',
    ]);

    Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'codigo_erp' => 'SD-1',
        'sale_date' => '2026-04-10',
        'total_sale_quantity' => '10.000',
        'total_sale_value' => '100.00',
        'acquisition_cost' => '50.00',
        'margem_contribuicao' => '30.00',
    ]);

    // Venda "cancelada": quantidade absurda que, se entrasse na soma, seria óbvio.
    $vendaCancelada = Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'codigo_erp' => 'SD-1',
        'sale_date' => '2026-04-11',
        'total_sale_quantity' => '999.000',
        'total_sale_value' => '9999.00',
        'acquisition_cost' => '5000.00',
        'margem_contribuicao' => '3000.00',
    ]);
    $vendaCancelada->delete();

    $results = (new SalesSummaryService)->indicatorsForProductIds(
        [$product->id],
        new SalesFilters(saleDateFrom: '2026-04-01', saleDateTo: '2026-04-30'),
    );

    $row = collect($results)->firstWhere('ean', '7890000000123');

    // Só a venda ativa deve contar: preço médio = 100/10 = 10, não algo inflado pela cancelada.
    expect($row['avg_price'])->toBe(10.0);
});

/*
 * A query agregada de TargetStockService usa STDDEV_POP, que não existe no SQLite
 * (driver dos testes) — mesma limitação já documentada em SalesAnalysisServicesTest.php.
 * O whereNull('deleted_at') adicionado é estruturalmente idêntico ao já provado no
 * teste de indicadores acima (mesmo padrão, mesmo ProductSalesAggregateQuery-like
 * join); falta só verificação manual contra pgsql.
 */
test('estoque alvo ignora venda soft-deleted no cálculo de média e desvio', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $tenant = setupSoftDeleteExclusionTenant('tenant-soft-del-stock', $user);

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Produto Estoque Soft Delete',
        'slug' => 'produto-estoque-soft-delete',
        'ean' => '7890000000456',
        'codigo_erp' => 'SD-2',
        'status' => 'published',
        'dimensions_status' => 'published',
        'current_stock' => 20,
    ]);

    foreach (['2026-04-05', '2026-04-12', '2026-04-19'] as $date) {
        Sale::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'codigo_erp' => 'SD-2',
            'sale_date' => $date,
            'total_sale_quantity' => '4.000',
            'total_sale_value' => '40.00',
        ]);
    }

    // Venda cancelada com quantidade destoante — se entrasse, puxaria a média para cima.
    $vendaCancelada = Sale::query()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'codigo_erp' => 'SD-2',
        'sale_date' => '2026-04-20',
        'total_sale_quantity' => '500.000',
        'total_sale_value' => '5000.00',
    ]);
    $vendaCancelada->delete();

    $filters = [
        'tenant_id' => $tenant->id,
        'date_from' => '2026-04-01',
        'date_to' => '2026-04-30',
    ];

    $abcResults = [[
        'product_id' => $product->id,
        'ean' => '7890000000456',
        'classificacao' => 'A',
    ]];

    $results = (new TargetStockService)->calculateByAbcResults($abcResults, 'sales', $filters);

    $row = collect($results)->firstWhere('ean', '7890000000456');

    // Média das 3 vendas ativas (4 cada) = 4; se a cancelada (500) entrasse, seria ~126.
    expect(round((float) $row['demanda_media'], 2))->toBe(4.0);
})->skip('STDDEV_POP não é suportado pelo SQLite; testar contra pgsql.');
