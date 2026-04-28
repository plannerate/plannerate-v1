<?php

use App\Models\Category;
use App\Models\EanReference;
use App\Models\Product;
use App\Services\Integrations\ExternalApiBaseService;
use App\Services\Integrations\Sysmo\SysmoEndpoints;
use App\Services\Integrations\Sysmo\SysmoProductsIntegrationService;
use App\Services\Integrations\Sysmo\SysmoProductsResponseMapper;

test('persist mapped products uses ean reference as knowledge base', function () {
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
    ]);

    $knownProduct = Product::query()
        ->where('tenant_id', $tenantId)
        ->where('ean', '789000000002')
        ->first();

    $unknownProduct = Product::query()
        ->where('tenant_id', $tenantId)
        ->where('ean', '7899999999999')
        ->first();

    expect($knownProduct)->not->toBeNull()
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
        ->and($unknownProduct?->category_id)->toBeNull()
        ->and($unknownProduct?->brand)->toBe('Marca API Sem Base');
});
