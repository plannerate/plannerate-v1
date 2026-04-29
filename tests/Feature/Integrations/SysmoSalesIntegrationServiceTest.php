<?php

use App\Models\Product;
use App\Services\Integrations\ExternalApiBaseService;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use App\Services\Integrations\Sysmo\SysmoEndpoints;
use App\Services\Integrations\Sysmo\SysmoSalesIntegrationService;
use App\Services\Integrations\Sysmo\SysmoSalesResponseMapper;
use Illuminate\Support\Facades\DB;

test('persist mapped sales does not link products before the sales day finishes', function () {
    config(['multitenancy.tenant_database_connection_name' => null]);

    $tenantId = (string) str()->ulid();

    Product::query()->create([
        'tenant_id' => $tenantId,
        'codigo_erp' => '10022',
        'ean' => '789000000002',
        'name' => 'Produto Vinculado',
    ]);

    $service = new SysmoSalesIntegrationService(
        app(ExternalApiBaseService::class),
        app(SysmoEndpoints::class),
        new SysmoSalesResponseMapper,
        new DeterministicIdGenerator,
    );
    $integrationId = (string) str()->ulid();

    $service->persistMappedSales($tenantId, $integrationId, [
        [
            'codigo_erp' => '10022',
            'store_identifier' => '81342172000145',
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
        ->and($sale?->product_id)->toBeNull()
        ->and($sale?->ean)->toBeNull()
        ->and((string) $sale?->id)->toStartWith('S1')
        ->and((float) $sale?->total_sale_value)->toBe(59.9)
        ->and((float) $sale?->total_profit_margin)->toBe(23.3175)
        ->and((float) $sale?->margem_contribuicao)->toBe(21.89)
        ->and($sale?->promotion)->toBe('N');
});

test('persist mapped sales chunks upserts to avoid mysql placeholder limits', function () {
    config(['multitenancy.tenant_database_connection_name' => null]);

    $tenantId = (string) str()->ulid();
    $insertStatements = 0;

    DB::listen(function ($query) use (&$insertStatements): void {
        $sql = mb_strtolower($query->sql);

        if (str_contains($sql, 'insert into "sales"') || str_contains($sql, 'insert into `sales`')) {
            $insertStatements++;
        }
    });

    $service = new SysmoSalesIntegrationService(
        app(ExternalApiBaseService::class),
        app(SysmoEndpoints::class),
        new SysmoSalesResponseMapper,
        new DeterministicIdGenerator,
    );

    $mappedSales = [];
    for ($index = 1; $index <= 1001; $index++) {
        $mappedSales[] = [
            'codigo_erp' => (string) (20000 + $index),
            'store_identifier' => '81342172000145',
            'promocao' => 'N',
            'sold_at' => '2025-01-22 10:00:00',
            'quantity' => 1.0,
            'unit_price' => 10.0,
            'total_price' => 10.0,
            'custo_aquisicao' => 8.0,
            'empresa' => '7',
            'valor_liquido' => 10.0,
            'valor_impostos' => 1.0,
            'custo_medio_loja' => 8.0,
            'custo_medio_geral' => 8.0,
            'custo_comercial' => 8.0,
        ];
    }

    $service->persistMappedSales(
        tenantId: $tenantId,
        integrationId: (string) str()->ulid(),
        mappedItems: $mappedSales,
    );

    expect($insertStatements)->toBe(2)
        ->and(DB::table('sales')->where('tenant_id', $tenantId)->count())->toBe(1001);
});
