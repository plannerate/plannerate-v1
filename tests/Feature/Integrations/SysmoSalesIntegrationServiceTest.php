<?php

use App\Models\Product;
use App\Services\Integrations\ExternalApiBaseService;
use App\Services\Integrations\Sysmo\SysmoEndpoints;
use App\Services\Integrations\Sysmo\SysmoSalesIntegrationService;
use App\Services\Integrations\Sysmo\SysmoSalesResponseMapper;
use Illuminate\Support\Facades\DB;

test('persist mapped sales fills ean from product using codigo erp', function () {
    $tenantId = (string) str()->ulid();

    $product = Product::query()->create([
        'tenant_id' => $tenantId,
        'codigo_erp' => '10022',
        'ean' => '789000000002',
        'name' => 'Produto Vinculado',
    ]);

    $service = new SysmoSalesIntegrationService(
        app(ExternalApiBaseService::class),
        app(SysmoEndpoints::class),
        new SysmoSalesResponseMapper,
    );

    $service->persistMappedSales($tenantId, [
        [
            'codigo_erp' => '10022',
            'promocao' => 'N',
            'sold_at' => '2025-01-22 10:00:00',
            'quantity' => 2.0,
            'unit_price' => 29.198,
            'total_price' => 59.9,
            'custo_aquisicao' => 23.3175,
            'empresa' => '7',
            'valor_liquido' => 59.9,
            'valor_impostos' => 14.78,
            'custo_medio_loja' => 23.226,
            'custo_medio_geral' => 23.307,
            'custo_comercial' => 23.3175,
        ],
    ]);

    $sale = DB::table('sales')
        ->where('tenant_id', $tenantId)
        ->where('codigo_erp', '10022')
        ->where('sale_date', '2025-01-22')
        ->first();

    expect($sale)->not->toBeNull()
        ->and($sale?->product_id)->toBe($product->id)
        ->and($sale?->ean)->toBe('789000000002')
        ->and((float) $sale?->total_sale_value)->toBe(59.9)
        ->and($sale?->promotion)->toBe('N');
});
