<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\Models\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\MonthlySalesSummary;
use Callcocam\LaravelRaptorPlannerate\Models\Sale;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Segment;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\AbcAnalysisService;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\BcgAnalysisService;
use Callcocam\LaravelRaptorPlannerate\Services\Analysis\GondolaSpaceService;
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

test('BCG soma as colunas dos eixos escolhidos e zera quem não vendeu', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $tenant = setupAnalysisTenant('tenant-bcg-sales', $user);

    $p1 = makeAnalysisProduct($tenant, 'BCG-1', '7890000000062');
    $p2 = makeAnalysisProduct($tenant, 'BCG-2', '7890000000079');
    $semVenda = makeAnalysisProduct($tenant, 'BCG-3', '7890000000086');

    // Eixos padrão: X = quantidade, Y = margem. Duas linhas de P1 provam o SUM.
    Sale::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'BCG-1', 'sale_date' => '2026-04-10', 'total_sale_quantity' => '60.000', 'total_sale_value' => '600.00', 'margem_contribuicao' => '120.00']);
    Sale::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'BCG-1', 'sale_date' => '2026-04-12', 'total_sale_quantity' => '40.000', 'total_sale_value' => '400.00', 'margem_contribuicao' => '80.00']);
    Sale::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p2->id, 'codigo_erp' => 'BCG-2', 'sale_date' => '2026-04-11', 'total_sale_quantity' => '10.000', 'total_sale_value' => '100.00', 'margem_contribuicao' => '20.00']);

    $result = (new BcgAnalysisService)->analyzeByProductIds(
        [$p1->id, $p2->id, $semVenda->id],
        'sales',
        ['tenant_id' => $tenant->id, 'date_from' => '2026-04-01', 'date_to' => '2026-04-30'],
    );

    $byProduct = $result->keyBy('product_id');

    // P1 agregado: qtde 60+40 = 100 (eixo X), margem 120+80 = 200 (eixo Y)
    expect($byProduct[$p1->id]['x_value'])->toBe(100.0)
        ->and($byProduct[$p1->id]['y_value'])->toBe(200.0)
        ->and($byProduct[$p1->id]['quadrant'])->toBe('alto_alto')
        ->and($byProduct[$p1->id]['x_axis'])->toBe('quantidade')
        ->and($byProduct[$p1->id]['y_axis'])->toBe('margem')
        // P2 é o mais fraco dos ativos → abaixo da mediana nos dois eixos
        ->and($byProduct[$p2->id]['x_value'])->toBe(10.0)
        ->and($byProduct[$p2->id]['quadrant'])->toBe('baixo_baixo')
        // Produto sem venda permanece no resultado (senão sumiria da gôndola), zerado
        ->and($byProduct[$semVenda->id]['sem_venda'])->toBeTrue()
        ->and($byProduct[$semVenda->id]['x_value'])->toBe(0.0)
        ->and($byProduct[$semVenda->id]['quadrant'])->toBe('baixo_baixo')
        // ...mas fora do limiar: a mediana é dos ATIVOS [100, 10] = 55, não de [100, 10, 0] = 10
        ->and($byProduct[$p1->id]['x_threshold'])->toBe(55.0);
});

test('BCG respeita eixos configurados e o corte pela média (planilha VBA)', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $tenant = setupAnalysisTenant('tenant-bcg-eixos', $user);

    $p1 = makeAnalysisProduct($tenant, 'BCGX-1', '7890000000093');
    $p2 = makeAnalysisProduct($tenant, 'BCGX-2', '7890000000109');

    Sale::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'BCGX-1', 'sale_date' => '2026-04-10', 'total_sale_quantity' => '5.000', 'total_sale_value' => '900.00', 'margem_contribuicao' => '10.00']);
    Sale::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p2->id, 'codigo_erp' => 'BCGX-2', 'sale_date' => '2026-04-11', 'total_sale_quantity' => '50.000', 'total_sale_value' => '100.00', 'margem_contribuicao' => '90.00']);

    // Eixos trocados: X = valor de venda, Y = margem. Média de [900, 100] = 500.
    $result = (new BcgAnalysisService)
        ->setAxes('valor', 'margem')
        ->setThresholdMethod(BcgAnalysisService::THRESHOLD_MEAN)
        ->analyzeByProductIds(
            [$p1->id, $p2->id],
            'sales',
            ['tenant_id' => $tenant->id, 'date_from' => '2026-04-01', 'date_to' => '2026-04-30'],
        );

    $byProduct = $result->keyBy('product_id');

    // P1 fatura muito e ganha pouco; P2 o inverso → cada um forte em um eixo.
    expect($byProduct[$p1->id]['x_value'])->toBe(900.0)   // valor, não quantidade
        ->and($byProduct[$p1->id]['x_threshold'])->toBe(500.0)  // média, não mediana
        ->and($byProduct[$p1->id]['quadrant'])->toBe('forte_x')
        ->and($byProduct[$p2->id]['quadrant'])->toBe('forte_y');
});

test('BCG lê os sumários mensais por start_month/end_month (a chave que o controller envia)', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $tenant = setupAnalysisTenant('tenant-bcg-monthly', $user);

    $p1 = makeAnalysisProduct($tenant, 'BCGM-1', '7890000000116');

    MonthlySalesSummary::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'BCGM-1', 'sale_month' => '2026-03-01', 'total_sale_quantity' => 30, 'total_sale_value' => '300.00', 'margem_contribuicao' => '60.00']);
    MonthlySalesSummary::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'BCGM-1', 'sale_month' => '2026-04-01', 'total_sale_quantity' => 20, 'total_sale_value' => '200.00', 'margem_contribuicao' => '40.00']);
    // Fora do período pedido: se a chave de período fosse ignorada (como acontece
    // hoje na ABC e no Estoque-Alvo, que leem month_from/month_to), este mês entraria
    // na soma e a quantidade viria 500 em vez de 50.
    MonthlySalesSummary::query()->create(['tenant_id' => $tenant->id, 'product_id' => $p1->id, 'codigo_erp' => 'BCGM-1', 'sale_month' => '2026-08-01', 'total_sale_quantity' => 450, 'total_sale_value' => '4500.00', 'margem_contribuicao' => '900.00']);

    $result = (new BcgAnalysisService)->analyzeByProductIds(
        [$p1->id],
        'monthly_summaries',
        ['tenant_id' => $tenant->id, 'start_month' => '2026-03', 'end_month' => '2026-04'],
    );

    $row = $result->firstWhere('product_id', $p1->id);

    // Só março + abril: qtde 30+20 = 50, margem 60+40 = 100. Agosto fica de fora.
    expect($row['x_value'])->toBe(50.0)
        ->and($row['y_value'])->toBe(100.0);
});

test('BCG classify_by muda o grupo de comparação e, com ele, o quadrante', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $tenant = setupAnalysisTenant('tenant-bcg-hierarquia', $user);

    // Hierarquia: Mercearia (raiz) → Biscoitos / Massas
    $raiz = Category::query()->create(['tenant_id' => $tenant->id, 'name' => 'Mercearia', 'slug' => 'mercearia', 'status' => 'published']);
    $biscoitos = Category::query()->create(['tenant_id' => $tenant->id, 'category_id' => $raiz->id, 'name' => 'Biscoitos', 'slug' => 'biscoitos', 'status' => 'published']);
    $massas = Category::query()->create(['tenant_id' => $tenant->id, 'category_id' => $raiz->id, 'name' => 'Massas', 'slug' => 'massas', 'status' => 'published']);

    $mk = function (string $codigo, string $ean, string $categoryId, float $qtde, float $margem) use ($tenant) {
        $product = makeAnalysisProduct($tenant, $codigo, $ean);
        $product->forceFill(['category_id' => $categoryId])->save();

        Sale::query()->create([
            'tenant_id' => $tenant->id, 'product_id' => $product->id, 'codigo_erp' => $codigo,
            'sale_date' => '2026-04-10',
            'total_sale_quantity' => (string) $qtde, 'total_sale_value' => '100.00',
            'margem_contribuicao' => (string) $margem,
        ]);

        return $product;
    };

    // Biscoitos opera numa escala muito maior que Massas
    $bisForte = $mk('HIER-1', '7890000000147', $biscoitos->id, 100, 100);
    $bisFraco = $mk('HIER-2', '7890000000154', $biscoitos->id, 80, 80);
    $masForte = $mk('HIER-3', '7890000000161', $massas->id, 10, 10);
    $masFraco = $mk('HIER-4', '7890000000178', $massas->id, 5, 5);

    $ids = [$bisForte->id, $bisFraco->id, $masForte->id, $masFraco->id];
    $filters = ['tenant_id' => $tenant->id, 'date_from' => '2026-04-01', 'date_to' => '2026-04-30'];

    // Nível folha (a hierarquia tem 2 níveis, então 'subcategoria' cai no mais profundo
    // disponível): cada categoria é seu próprio grupo → mediana de Massas = 7,5
    $porFolha = (new BcgAnalysisService)
        ->setClassifyBy('subcategoria')
        ->analyzeByProductIds($ids, 'sales', $filters)
        ->keyBy('product_id');

    // Nível raiz: os 4 produtos viram um único grupo → mediana da quantidade = 45
    $porRaiz = (new BcgAnalysisService)
        ->setClassifyBy('segmento_varejista')
        ->analyzeByProductIds($ids, 'sales', $filters)
        ->keyBy('product_id');

    // O líder de Massas é forte entre os seus pares...
    expect($porFolha[$masForte->id]['quadrant'])->toBe('alto_alto')
        ->and($porFolha[$masForte->id]['x_threshold'])->toBe(7.5)
        ->and($porFolha[$masForte->id]['group_name'])->toBe('Massas')
        // ...mas vira fraco quando comparado à mercearia inteira, onde os biscoitos dominam.
        // É exatamente isto que o "Classificar por" controla — e que a tela prometia sem entregar.
        ->and($porRaiz[$masForte->id]['quadrant'])->toBe('baixo_baixo')
        ->and($porRaiz[$masForte->id]['x_threshold'])->toBe(45.0)
        ->and($porRaiz[$masForte->id]['group_name'])->toBe('Mercearia')
        // O líder de Biscoitos é forte nos dois recortes
        ->and($porFolha[$bisForte->id]['quadrant'])->toBe('alto_alto')
        ->and($porRaiz[$bisForte->id]['quadrant'])->toBe('alto_alto')
        // A categoria folha do produto continua exposta para exibição, separada do grupo do corte
        ->and($porRaiz[$masFraco->id]['category_name'])->toBe('Massas');
});

test('GondolaSpace soma frentes e espaço linear descendo a hierarquia física', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $tenant = setupAnalysisTenant('tenant-bcg-espaco', $user);

    // Produtos com largura: o espaço linear depende dela, não só das frentes.
    $largo = makeAnalysisProduct($tenant, 'ESP-1', '7890000000123');
    $largo->forceFill(['width' => 20.0])->save();

    $estreito = makeAnalysisProduct($tenant, 'ESP-2', '7890000000130');
    $estreito->forceFill(['width' => 5.0])->save();

    // Hierarquia: gôndola → seção → prateleira → segmento → layer
    $gondola = Gondola::query()->create(['tenant_id' => $tenant->id, 'name' => 'Gôndola Teste']);
    $section = Section::query()->create(['tenant_id' => $tenant->id, 'gondola_id' => $gondola->id, 'name' => 'Seção 1']);
    $shelf = Shelf::query()->create(['tenant_id' => $tenant->id, 'section_id' => $section->id]);

    // Segment e Layer não declaram $fillable → mass assignment bloqueado; forceCreate.
    $seg1 = Segment::query()->forceCreate(['tenant_id' => $tenant->id, 'shelf_id' => $shelf->id, 'ordering' => 1]);
    $seg2 = Segment::query()->forceCreate(['tenant_id' => $tenant->id, 'shelf_id' => $shelf->id, 'ordering' => 2]);

    // 2 frentes do produto largo (2 × 20 = 40cm)
    Layer::query()->forceCreate(['tenant_id' => $tenant->id, 'segment_id' => $seg1->id, 'product_id' => $largo->id, 'quantity' => 2]);
    // 8 frentes do estreito, em dois segmentos (5 + 3 = 8 frentes × 5cm = 40cm)
    Layer::query()->forceCreate(['tenant_id' => $tenant->id, 'segment_id' => $seg1->id, 'product_id' => $estreito->id, 'quantity' => 5]);
    Layer::query()->forceCreate(['tenant_id' => $tenant->id, 'segment_id' => $seg2->id, 'product_id' => $estreito->id, 'quantity' => 3]);

    $space = (new GondolaSpaceService)->spaceByProduct($gondola->id);

    // O estreito tem 4x mais frentes, mas ocupa o MESMO linear que o largo — é por isso
    // que o share é medido em cm, não em contagem de frentes.
    expect($space[$largo->id]['facings'])->toBe(2)
        ->and($space[$largo->id]['espaco_linear_cm'])->toBe(40.0)
        ->and($space[$estreito->id]['facings'])->toBe(8)   // somou os dois segmentos
        ->and($space[$estreito->id]['espaco_linear_cm'])->toBe(40.0)
        ->and($space[$largo->id]['share_gondola'])->toBe(50.0)
        ->and($space[$estreito->id]['share_gondola'])->toBe(50.0);

    // E o BCG enxerga a gôndola pelos layers, sem precisar da categoria do planograma
    expect((new BcgAnalysisService)->getProductIdsByGondola($gondola->id))
        ->toHaveCount(2);
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
