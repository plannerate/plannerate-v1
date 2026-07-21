<?php

use App\Models\IntegrationApi;
use Illuminate\Support\Facades\Artisan;

/*
 * Cobre a migration que amplia o field_map de produtos do blueprint GesCooper
 * (ERP por trás da API da Coasgo).
 *
 * O ponto sensível não é só "acrescentou campos": é garantir que dimensões e
 * atributos que o feed manda vazios continuem FORA do mapeamento. O upsert
 * reescreve toda coluna presente no registro mapeado, então mapear esses campos
 * zeraria a pesquisa de dimensões e apagaria preenchimento manual a cada import.
 */

/**
 * field_map original do blueprint, antes da migration.
 *
 * @return array<int, array{target: string, source: string, transforms: array<int, string>}>
 */
function gescooperOriginalFieldMap(): array
{
    return [
        ['target' => 'codigo_erp', 'source' => 'id_produto', 'transforms' => ['string', 'alnum', 'not_null']],
        ['target' => 'ean', 'source' => 'ean', 'transforms' => ['string', 'not_null']],
        ['target' => 'name', 'source' => 'descricao_completa', 'transforms' => ['string']],
        ['target' => 'last_purchase_date', 'source' => 'data_ultima_compra', 'transforms' => ['date']],
    ];
}

function gescooperMigration(): object
{
    return require database_path('migrations/landlord/2026_07_21_000001_expand_gescooper_product_field_map.php');
}

function makeGescooperApi(): IntegrationApi
{
    return IntegrationApi::query()->create([
        'name' => 'GesCooper',
        'slug' => 'gescooper',
        'requests' => [
            'method' => 'GET',
            'page_field' => 'pagina',
            'page_size_field' => 'registros_por_pagina',
            'store_document_field' => 'empresa',
            'paths' => [
                'products' => [
                    'target_table' => 'products',
                    'fallback_path' => '/Produtos',
                    'unique_by' => ['ean'],
                    'field_map' => gescooperOriginalFieldMap(),
                ],
            ],
        ],
        'response' => [
            'items_path' => 'data',
            'pagination' => ['last_page_path' => 'pagination.last_page'],
        ],
        'is_active' => true,
    ]);
}

/**
 * @return array<string, string> target => source
 */
function productFieldMapByTarget(IntegrationApi $api): array
{
    $fieldMap = (array) data_get($api->fresh()->requests, 'paths.products.field_map', []);

    return array_column($fieldMap, 'source', 'target');
}

beforeEach(function (): void {
    Artisan::call('migrate:fresh', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
        '--no-interaction' => true,
    ]);
});

test('acrescenta os campos preenchidos pelo feed sem perder o mapeamento original', function (): void {
    $api = makeGescooperApi();

    gescooperMigration()->up();

    $byTarget = productFieldMapByTarget($api);

    // Campos novos: 100% de preenchimento na amostra do feed.
    expect($byTarget)
        ->toMatchArray([
            'brand' => 'marca',
            'measurement_unit' => 'unidade_medida',
            'packaging_type' => 'tipo_embalagem',
            'auxiliary_description' => 'descricao_auxiliar',
            'type' => 'tipo',
            'current_stock' => 'estoque_atual',
            'sales_status' => 'status_produto',
        ])
        // Mapeamento original preservado.
        ->toMatchArray([
            'codigo_erp' => 'id_produto',
            'ean' => 'ean',
            'name' => 'descricao_completa',
            'last_purchase_date' => 'data_ultima_compra',
        ]);
});

test('não mapeia dimensões — o feed manda vazio e sobrescreveria a pesquisa de dimensões', function (): void {
    $api = makeGescooperApi();

    gescooperMigration()->up();

    expect(productFieldMapByTarget($api))
        ->not->toHaveKeys(['width', 'height', 'depth']);
});

test('não mapeia atributos que o feed manda sempre vazios', function (): void {
    $api = makeGescooperApi();

    gescooperMigration()->up();

    expect(productFieldMapByTarget($api))
        ->not->toHaveKeys([
            'subbrand',
            'packaging_size',
            'additional_information',
            'reference',
            'fragrance',
            'flavor',
            'color',
        ]);
});

test('é idempotente: rodar duas vezes não duplica campo', function (): void {
    $api = makeGescooperApi();

    gescooperMigration()->up();
    $afterFirstRun = (array) data_get($api->fresh()->requests, 'paths.products.field_map', []);

    gescooperMigration()->up();
    $afterSecondRun = (array) data_get($api->fresh()->requests, 'paths.products.field_map', []);

    expect($afterSecondRun)->toBe($afterFirstRun)
        ->and(array_column($afterSecondRun, 'target'))
        ->toBe(array_unique(array_column($afterSecondRun, 'target')));
});

test('down remove só os campos acrescentados', function (): void {
    $api = makeGescooperApi();

    gescooperMigration()->up();
    gescooperMigration()->down();

    expect((array) data_get($api->fresh()->requests, 'paths.products.field_map', []))
        ->toBe(gescooperOriginalFieldMap());
});

test('não faz nada quando o blueprint não existe', function (): void {
    gescooperMigration()->up();

    expect(IntegrationApi::query()->where('slug', 'gescooper')->exists())->toBeFalse();
});
