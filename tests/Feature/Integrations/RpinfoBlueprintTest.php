<?php

use App\Models\IntegrationApi;
use App\Services\Integrations\FieldValueResolver;
use App\Services\Integrations\RecordMapper;
use App\Services\Integrations\Support\IntegrationPaginationMode;
use App\Services\Integrations\Support\IntegrationUrlBuilder;
use Illuminate\Support\Facades\Artisan;

/*
 * Contrato do blueprint da RP Info, validado contra itens REAIS da API
 * (recortados de storage/app/private/rpinfo/, que é gitignored).
 *
 * O ponto sensível é o mesmo do GesCooper: o upsert reescreve toda coluna presente
 * no registro mapeado. Dimensão e peso são do pipeline de pesquisa por IA — mapear
 * os campos zerados que a RP manda apagaria esse trabalho a cada import.
 */

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

function rpinfoApi(): IntegrationApi
{
    return IntegrationApi::query()->where('slug', 'rpinfo')->firstOrFail();
}

/** @return array<string, mixed> Item real de /v3.2/produtounidade/listaprodutos (recortado) */
function rpinfoProdutoReal(): array
{
    return [
        'Codigo' => 1, 'SKU' => 1, 'CodigoBarras' => '0000000000001',
        'Descricao' => 'Uva Niagara Grl Kg', 'Complemento' => '', 'Marca' => 'Flv',
        'Status' => 'NORMAL', 'Ativo' => true, 'Bloqueado' => 'S',
        'Grupo' => '11434', 'CodigoDepartamento' => '004', 'Departamento' => '',
        'TipoEmbalagem' => 'KG', 'QuantidadeEmbalagem' => 7,
        'Estoque1' => 0.0, 'DtUltComp' => '13-03-2026',
        // Sempre zerados no feed — não podem virar coluna mapeada.
        'Altura' => 0, 'Largura' => 0, 'Profundidade' => 0, 'Peso' => 1.0, 'PesoLiquido' => 1.0,
        'DataHoraManutencao' => '15/07/2026 06:01:19.000',
    ];
}

/** @return array<string, mixed> Item real de /v1.9/movimentoprodutos/listarmovimentos (recortado) */
function rpinfoMovimentoReal(): array
{
    return [
        'id' => 16557968, 'transacao' => '00122090888', 'status' => 'NORMAL',
        'tipoMovimento' => 'SAIDA', 'tipoDcto' => 'EVP', 'data' => '15/07/2026',
        'unidade' => '001', 'codigoProduto' => 19426, 'sequencial' => 1,
        'quantidadeUnitaria' => 22.0, 'valor' => 65.78,
        'ctCompra' => 48.18, 'ctMedio' => 48.18, 'ctEmpresa' => 48.18, 'ctFiscal' => 48.18,
        'valorPIS' => 0.0, 'valorCofins' => 0.0, 'valorIcms' => 0.0,
    ];
}

/**
 * @return array<string, string> target => source
 */
function rpinfoFieldMapByTarget(string $pathKey): array
{
    $fieldMap = (array) data_get(rpinfoApi()->requests, "paths.{$pathKey}.field_map", []);

    return array_column($fieldMap, 'source', 'target');
}

function rpinfoMapper(): RecordMapper
{
    return new RecordMapper(new FieldValueResolver);
}

test('o blueprint é criado com os dois paths em modo cursor', function (): void {
    $requests = rpinfoApi()->requests;

    expect(data_get($requests, 'pagination_mode'))->toBe('cursor')
        ->and(array_keys((array) data_get($requests, 'paths')))->toBe(['products', 'sales'])
        ->and(IntegrationPaginationMode::isCursor($requests, (array) data_get($requests, 'paths.products')))->toBeTrue()
        ->and(IntegrationPaginationMode::isCursor($requests, (array) data_get($requests, 'paths.sales')))->toBeTrue();
});

test('mapeia um produto real da API', function (): void {
    $pathConfig = (array) data_get(rpinfoApi()->requests, 'paths.products');

    $record = rpinfoMapper()->map(
        rpinfoProdutoReal(),
        $pathConfig['field_map'],
        'store-ulid',
        $pathConfig['validations'],
    );

    expect($record)->toMatchArray([
        'codigo_erp' => '1',
        'ean' => '0000000000001',
        'name' => 'Uva Niagara Grl Kg',
        'brand' => 'Flv',
        'measurement_unit' => 'KG',
        'last_purchase_date' => '2026-03-13',
        'store_id' => 'store-ulid',
    ]);
});

test('não mapeia dimensões nem peso — a RP manda zerado e o pipeline de IA é dono dessas colunas', function (): void {
    expect(rpinfoFieldMapByTarget('products'))
        ->not->toHaveKeys(['width', 'height', 'depth', 'weight']);
});

test('não mapeia campos que o feed manda sempre vazios nem a caixa de compra', function (): void {
    expect(rpinfoFieldMapByTarget('products'))
        ->not->toHaveKeys([
            'packaging_content',  // QuantidadeEmbalagem é caixa de compra, não conteúdo de venda
            'packaging_size',
            'subbrand',
            'reference',
            'fragrance',
            'flavor',
            'color',
            'additional_information',
        ]);
});

test('descarta produto cancelado', function (): void {
    $pathConfig = (array) data_get(rpinfoApi()->requests, 'paths.products');

    [$record, $rejected] = rpinfoMapper()->mapWithRejectionReason(
        [...rpinfoProdutoReal(), 'Status' => 'CANCELADO'],
        $pathConfig['field_map'],
        null,
        $pathConfig['validations'],
    );

    expect($record)->toBeNull()->and($rejected)->toBeNull();
});

test('descarta produto sem código de barras — o unique_by é o ean', function (): void {
    $pathConfig = (array) data_get(rpinfoApi()->requests, 'paths.products');

    [$record, $rejected] = rpinfoMapper()->mapWithRejectionReason(
        [...rpinfoProdutoReal(), 'CodigoBarras' => ''],
        $pathConfig['field_map'],
        null,
        $pathConfig['validations'],
    );

    expect($record)->toBeNull()->and($rejected)->toBe('ean');
});

test('mapeia um movimento de venda real, com a margem de contribuição calculada', function (): void {
    $pathConfig = (array) data_get(rpinfoApi()->requests, 'paths.sales');

    $record = rpinfoMapper()->map(
        rpinfoMovimentoReal(),
        $pathConfig['field_map'],
        'store-ulid',
        $pathConfig['validations'],
    );

    expect($record)->toMatchArray([
        'codigo_erp' => '19426',
        // "15/07/2026" só passa pelo date_dmy; o transform `date` devolveria null
        // e o not_null descartaria a venda inteira.
        'sale_date' => '2026-07-15',
        'total_sale_quantity' => 22.0,
        'total_sale_value' => 65.78,
        'acquisition_cost' => 48.18,
        'margem_contribuicao' => 17.6,
        'store_id' => 'store-ulid',
    ]);
});

test('descarta movimento cancelado ou de entrada', function (array $overrides): void {
    $pathConfig = (array) data_get(rpinfoApi()->requests, 'paths.sales');

    expect(rpinfoMapper()->map(
        [...rpinfoMovimentoReal(), ...$overrides],
        $pathConfig['field_map'],
        null,
        $pathConfig['validations'],
    ))->toBeNull();
})->with([
    'cancelado' => [['status' => 'CANCELADO']],
    'entrada' => [['tipoMovimento' => 'ENTRADA']],
]);

test('monta as URLs reais dos dois endpoints a partir do fallback_path', function (): void {
    $config = ['connection' => ['base_url' => 'http://erp.rpinfo.test:8010']];
    $requests = rpinfoApi()->requests;

    expect(IntegrationUrlBuilder::build($config, (array) data_get($requests, 'paths.products'), '268', '00073351000122'))
        ->toBe('http://erp.rpinfo.test:8010/v3.2/produtounidade/listaprodutos/268/unidade/00073351000122/detalhado')
        ->and(IntegrationUrlBuilder::build($config, (array) data_get($requests, 'paths.sales'), '16557968', '00073351000122'))
        ->toBe('http://erp.rpinfo.test:8010/v1.9/movimentoprodutos/listarmovimentos/lastid/16557968');
});

test('o documento vai no path em produtos e na query em vendas', function (): void {
    $requests = rpinfoApi()->requests;

    expect(IntegrationUrlBuilder::consumesStoreDocumentInPath((array) data_get($requests, 'paths.products')))->toBeTrue()
        ->and(IntegrationUrlBuilder::consumesStoreDocumentInPath((array) data_get($requests, 'paths.sales')))->toBeFalse()
        ->and(data_get($requests, 'store_document_field'))->toBe('unidade');
});

test('estoque e última compra alimentam só a pivot — são métricas por loja', function (): void {
    $products = (array) data_get(rpinfoApi()->requests, 'paths.products');

    // Continuam no field_map: o valor precisa ser mapeado para chegar ao registro.
    expect(rpinfoFieldMapByTarget('products'))->toHaveKeys(['current_stock', 'last_purchase_date'])
        // Mas saem do upsert de `products` (o id do produto não inclui a loja,
        // então as duas cadeias se sobrescreveriam).
        ->and($products['pivot_only_targets'])->toBe(['current_stock', 'last_purchase_date']);

    $pivot = collect($products['pivot_tables'])->firstWhere('table', 'product_store');

    // E o upsert da pivot precisa atualizá-las: sem update_columns o valor
    // congelaria no primeiro import.
    expect($pivot['update_columns'])->toBe(['current_stock', 'last_purchase_date']);
});

test('é idempotente e o down remove o blueprint', function (): void {
    $migration = require database_path('migrations/landlord/2026_07_21_000002_create_rpinfo_integration_api.php');

    $original = rpinfoApi();
    $migration->up();

    expect(IntegrationApi::query()->where('slug', 'rpinfo')->count())->toBe(1)
        ->and(rpinfoApi()->id)->toBe($original->id);

    $migration->down();

    expect(IntegrationApi::query()->withTrashed()->where('slug', 'rpinfo')->exists())->toBeFalse();
});
