<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\Models\MonthlySalesSummary;
use Callcocam\LaravelRaptorPlannerate\Models\Sale;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\AbcAnalysisService;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\PaperAnalysisService;
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
 * Provisiona um tenant de teste com o schema migrado, ativa o contexto tenant e
 * dá ao usuário o papel tenant-admin. Autossuficiente (não depende da ordem de
 * outros arquivos de teste).
 */
if (! function_exists('setupAnalysisTenant')) {
    function setupAnalysisTenant(string $subdomain, User $user): Tenant
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
 * Cria um produto mínimo no tenant para as análises (casado às vendas por codigo_erp).
 */
function makeAnalysisProduct(Tenant $tenant, string $codigoErp, string $ean): Product
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

test('ABC aggregates sales (SUM) and ranks the dominant product as A', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $tenant = setupAnalysisTenant('tenant-abc-sales', $user);

    $p1 = makeAnalysisProduct($tenant, 'ABC-1', '7890000000017');
    $p2 = makeAnalysisProduct($tenant, 'ABC-2', '7890000000024');

    // P1 domina em qtde, valor e margem → independe dos pesos, deve ser ranking 1 / classe A.
    // Duas linhas para P1 validam a soma (SUM) da query de agregação.
    Sale::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'ABC-1', 'sale_date' => '2026-04-10', 'total_sale_quantity' => '60.000', 'total_sale_value' => '600.00', 'margem_contribuicao' => '120.00']);
    Sale::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'ABC-1', 'sale_date' => '2026-04-12', 'total_sale_quantity' => '40.000', 'total_sale_value' => '400.00', 'margem_contribuicao' => '80.00']);
    Sale::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p2->id, 'codigo_erp' => 'ABC-2', 'sale_date' => '2026-04-11', 'total_sale_quantity' => '10.000', 'total_sale_value' => '100.00', 'margem_contribuicao' => '20.00']);

    $result = (new AbcAnalysisService)->analyzeByProductIds(
        [$p1->id, $p2->id],
        'sales',
        ['tenant_id' => $tenant->id, 'date_from' => '2026-04-01', 'date_to' => '2026-04-30'],
    );

    $byProduct = $result->keyBy('product_id');

    // Agregação SUM correta para P1: 60+40 = 100 / 600+400 = 1000 / 120+80 = 200
    expect((float) $byProduct[$p1->id]['qtde'])->toBe(100.0)
        ->and((float) $byProduct[$p1->id]['valor'])->toBe(1000.0)
        ->and((float) $byProduct[$p1->id]['margem'])->toBe(200.0)
        // P1 domina → ranking 1 e classe A (primeiro do ranking é sempre A)
        ->and($byProduct[$p1->id]['ranking'])->toBe(1)
        ->and($byProduct[$p1->id]['classificacao'])->toBe('A')
        // P2 entra com os valores agregados próprios
        ->and((float) $byProduct[$p2->id]['valor'])->toBe(100.0);
});

test('ABC reads from monthly_sales_summaries when tableType is monthly_summaries', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $tenant = setupAnalysisTenant('tenant-abc-monthly', $user);

    $p1 = makeAnalysisProduct($tenant, 'ABCM-1', '7890000000031');

    // Dados SÓ na tabela de sumários mensais — prova que o branch monthly_summaries
    // (com filtros month_from/month_to) lê da fonte correta.
    MonthlySalesSummary::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'ABCM-1', 'sale_month' => '2026-03-01', 'total_sale_quantity' => 30, 'total_sale_value' => '300.00', 'margem_contribuicao' => '60.00']);
    MonthlySalesSummary::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'ABCM-1', 'sale_month' => '2026-04-01', 'total_sale_quantity' => 20, 'total_sale_value' => '200.00', 'margem_contribuicao' => '40.00']);

    $result = (new AbcAnalysisService)->analyzeByProductIds(
        [$p1->id],
        'monthly_summaries',
        ['tenant_id' => $tenant->id, 'month_from' => '2026-03-01', 'month_to' => '2026-04-30'],
    );

    $row = $result->firstWhere('product_id', $p1->id);

    // Soma dos dois meses: 30+20 = 50 / 300+200 = 500 / 60+40 = 100
    expect((float) $row['qtde'])->toBe(50.0)
        ->and((float) $row['valor'])->toBe(500.0)
        ->and((float) $row['margem'])->toBe(100.0)
        ->and($row['classificacao'])->toBe('A');
});

test('Paper computes market share and growth vs the auto-derived previous period', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $tenant = setupAnalysisTenant('tenant-paper-sales', $user);

    $p1 = makeAnalysisProduct($tenant, 'PAP-1', '7890000000048');
    $p2 = makeAnalysisProduct($tenant, 'PAP-2', '7890000000055');

    // Período atual: 2026-04-01..2026-04-30 (30 dias) → anterior auto = 2026-03-02..2026-03-31.
    // Atual: P1 = 100, P2 = 300 (total categoria = 400). Anterior: P1 = 80 (P2 sem histórico).
    Sale::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'PAP-1', 'sale_date' => '2026-04-10', 'total_sale_quantity' => '10.000', 'total_sale_value' => '100.00', 'margem_contribuicao' => '20.00']);
    Sale::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p2->id, 'codigo_erp' => 'PAP-2', 'sale_date' => '2026-04-15', 'total_sale_quantity' => '30.000', 'total_sale_value' => '300.00', 'margem_contribuicao' => '60.00']);
    Sale::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'PAP-1', 'sale_date' => '2026-03-15', 'total_sale_quantity' => '8.000', 'total_sale_value' => '80.00', 'margem_contribuicao' => '16.00']);

    $result = (new PaperAnalysisService)->analyzeByProductIds(
        [$p1->id, $p2->id],
        'sales',
        ['tenant_id' => $tenant->id, 'date_from' => '2026-04-01', 'date_to' => '2026-04-30'],
    );

    $byProduct = $result->keyBy('product_id');

    // P1: market share = 100/400 = 25%, growth = (100-80)/80 = 25%
    expect($byProduct[$p1->id]['market_share'])->toBe(25.0)
        ->and($byProduct[$p1->id]['growth_rate'])->toBe(25.0)
        ->and((float) $byProduct[$p1->id]['total_value_current'])->toBe(100.0)
        ->and((float) $byProduct[$p1->id]['total_value_previous'])->toBe(80.0)
        // P2: market share = 300/400 = 75%, sem período anterior → produto novo
        ->and($byProduct[$p2->id]['market_share'])->toBe(75.0)
        ->and($byProduct[$p2->id]['growth_rate'])->toBeNull()
        ->and($byProduct[$p2->id]['is_new'])->toBeTrue();
});

/**
 * A query agregada do TargetStockService usa STDDEV_POP, que NÃO existe no SQLite
 * (driver dos testes). Por isso a agregação de média/desvio só pode ser testada
 * contra PostgreSQL. As fórmulas de estoque (z-score, segurança, mínimo, alvo) já
 * têm cobertura determinística em tests/Unit/SalesStatisticsTest.php.
 */
test('TargetStock DB aggregation requires PostgreSQL (STDDEV_POP unsupported in SQLite)', function (): void {
    expect(true)->toBeTrue();
})->skip('STDDEV_POP não é suportado pelo SQLite; testar contra pgsql. Math coberta em SalesStatisticsTest.');
