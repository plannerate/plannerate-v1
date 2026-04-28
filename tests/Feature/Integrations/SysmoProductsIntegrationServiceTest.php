<?php

use App\Models\Category;
use App\Models\EanReference;
use App\Models\Product;
use App\Models\Store;
use App\Services\Integrations\ExternalApiBaseService;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use App\Services\Integrations\Support\SyncSalesProductReferencesService;
use App\Services\Integrations\Sysmo\SysmoEndpoints;
use App\Services\Integrations\Sysmo\SysmoProductsIntegrationService;
use App\Services\Integrations\Sysmo\SysmoProductsResponseMapper;
use App\Services\Integrations\Sysmo\SysmoSalesIntegrationService;
use App\Services\Integrations\Sysmo\SysmoSalesResponseMapper;
use Illuminate\Support\Facades\DB;

test('persist mapped products uses ean reference as knowledge base', function () {
    config(['multitenancy.tenant_database_connection_name' => null]);

    $tenantId = (string) str()->ulid();
    $category = Category::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Categoria Base',
    ]);

    EanReference::query()->create([
        'tenant_id' => $tenantId,
        'ean' => '789000000002',
        'category_id' => $category->id,
        'reference_description' => 'Descricao da base de conhecimento',
        'brand' => 'Marca da Base',
        'subbrand' => 'Submarca da Base',
        'packaging_type' => 'Caixa',
        'packaging_size' => '12',
        'measurement_unit' => 'UN',
    ]);

    $service = new SysmoProductsIntegrationService(
        app(ExternalApiBaseService::class),
        app(SysmoEndpoints::class),
        new SysmoProductsResponseMapper,
        new DeterministicIdGenerator,
        app(SyncSalesProductReferencesService::class),
    );

    $service->persistMappedProducts($tenantId, 'sysmo', [
        [
            'external_id' => '66526',
            'ean' => '789.0000.00002',
            'name' => 'Produto API',
            'brand' => 'Marca API',
            'unit' => 'LT',
            'status' => 'ATIVO',
        ],
        [
            'external_id' => '66527',
            'ean' => '7899999999999',
            'name' => 'Produto sem referencia',
            'brand' => 'Marca API Sem Base',
            'unit' => 'UN',
            'status' => 'ATIVO',
        ],
        [
            'external_id' => 'N/A',
            'ean' => '7891111111111',
            'name' => 'Produto com codigo invalido',
            'brand' => 'Marca Invalida',
            'unit' => 'UN',
            'status' => 'ATIVO',
        ],
        [
            'external_id' => '66528',
            'ean' => '12345678901234',
            'name' => 'Produto com gtin invalido',
            'brand' => 'Marca Invalida GTIN',
            'unit' => 'UN',
            'status' => 'ATIVO',
        ],
        [
            'external_id' => '66529',
            'ean' => '7891111111112',
            'name' => 'Produto inativo na empresa',
            'brand' => 'Marca Inativa',
            'unit' => 'UN',
            'status' => 'ATIVO',
            'raw' => [
                'gtins' => [
                    'completo' => [
                        [
                            'gtin' => '7891111111112',
                            'principal' => 'S',
                        ],
                    ],
                ],
                'cadastro_ativo' => 'S',
                'ativo_na_empresa' => 'N',
                'pertence_ao_mix' => 'S',
            ],
        ],
    ]);

    $store = Store::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Loja Teste',
        'code' => '1',
    ]);

    $service->persistMappedProducts($tenantId, 'sysmo', [
        [
            'external_id' => '66526',
            'ean' => '789.0000.00002',
            'name' => 'Produto API',
            'brand' => 'Marca API',
            'unit' => 'LT',
            'status' => 'ATIVO',
        ],
    ], (string) $store->id);

    $knownProduct = Product::query()
        ->where('tenant_id', $tenantId)
        ->where('ean', '789000000002')
        ->first();

    $unknownProduct = Product::query()
        ->where('tenant_id', $tenantId)
        ->where('ean', '7899999999999')
        ->first();

    expect($knownProduct)->not->toBeNull()
        ->and((string) $knownProduct?->id)->toStartWith('P1')
        ->and($knownProduct?->category_id)->toBe($category->id)
        ->and($knownProduct?->brand)->toBe('Marca da Base')
        ->and($knownProduct?->subbrand)->toBe('Submarca da Base')
        ->and($knownProduct?->packaging_type)->toBe('Caixa')
        ->and($knownProduct?->packaging_size)->toBe('12')
        ->and($knownProduct?->measurement_unit)->toBe('UN')
        ->and($knownProduct?->description)->toBe('Descricao da base de conhecimento')
        ->and($knownProduct?->status)->toBe('synced')
        ->and($knownProduct?->sync_source)->toBe('sysmo');

    expect($unknownProduct)->not->toBeNull()
        ->and((string) $unknownProduct?->id)->toStartWith('P1')
        ->and($unknownProduct?->category_id)->toBeNull()
        ->and($unknownProduct?->brand)->toBe('Marca API Sem Base');

    expect(Product::query()
        ->where('tenant_id', $tenantId)
        ->where('name', 'Produto com codigo invalido')
        ->exists())->toBeFalse();

    expect(Product::query()
        ->where('tenant_id', $tenantId)
        ->where('name', 'Produto com gtin invalido')
        ->exists())->toBeFalse();

    expect(Product::query()
        ->where('tenant_id', $tenantId)
        ->where('name', 'Produto inativo na empresa')
        ->exists())->toBeFalse();

    $this->assertDatabaseHas('product_store', [
        'tenant_id' => $tenantId,
        'product_id' => $knownProduct?->id,
        'store_id' => $store->id,
    ]);
});

test('persist mapped products backfills sales references by codigo erp', function () {
    config(['multitenancy.tenant_database_connection_name' => null]);

    $tenantId = (string) str()->ulid();
    $integrationId = (string) str()->ulid();

    $salesService = new SysmoSalesIntegrationService(
        app(ExternalApiBaseService::class),
        app(SysmoEndpoints::class),
        new SysmoSalesResponseMapper,
        new DeterministicIdGenerator,
        app(SyncSalesProductReferencesService::class),
    );

    $salesService->persistMappedSales($tenantId, $integrationId, [
        [
            'codigo_erp' => '66599',
            'store_identifier' => '81342172000145',
            'promocao' => 'N',
            'sold_at' => '2025-01-23 10:00:00',
            'quantity' => 1.0,
            'unit_price' => 10.0,
            'total_price' => 10.0,
            'custo_aquisicao' => 8.0,
            'empresa' => '7',
        ],
    ]);

    $saleBefore = DB::table('sales')
        ->where('tenant_id', $tenantId)
        ->where('codigo_erp', '66599')
        ->first();

    expect($saleBefore?->product_id)->toBeNull()
        ->and($saleBefore?->ean)->toBeNull();

    $productsService = new SysmoProductsIntegrationService(
        app(ExternalApiBaseService::class),
        app(SysmoEndpoints::class),
        new SysmoProductsResponseMapper,
        new DeterministicIdGenerator,
        app(SyncSalesProductReferencesService::class),
    );

    $productsService->persistMappedProducts($tenantId, 'sysmo', [
        [
            'external_id' => '66599',
            'ean' => '7891234500001',
            'name' => 'Produto Backfill',
            'brand' => 'Marca Backfill',
            'unit' => 'UN',
            'status' => 'ATIVO',
        ],
    ]);

    $product = Product::query()
        ->where('tenant_id', $tenantId)
        ->where('codigo_erp', '66599')
        ->first();

    $saleAfter = DB::table('sales')
        ->where('tenant_id', $tenantId)
        ->where('codigo_erp', '66599')
        ->first();

    expect($product)->not->toBeNull()
        ->and($saleAfter)->not->toBeNull()
        ->and($saleAfter?->product_id)->toBe($product?->id)
        ->and($saleAfter?->ean)->toBe('7891234500001');
});
