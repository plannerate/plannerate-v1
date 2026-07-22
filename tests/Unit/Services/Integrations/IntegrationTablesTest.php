<?php

use App\Services\Integrations\Support\IntegrationTables;

test('resolve o nome configurado para o papel', function (): void {
    config(['integrations.tables.products' => 'catalog_products']);

    expect(IntegrationTables::name('products'))->toBe('catalog_products');
});

test('cai no próprio papel quando não há nome configurado', function (): void {
    config(['integrations.tables' => []]);

    expect(IntegrationTables::name('products'))->toBe('products')
        ->and(IntegrationTables::name('tabela_desconhecida'))->toBe('tabela_desconhecida');
});

test('ignora configuração inválida e usa o default', function (): void {
    config(['integrations.tables.sales' => '']);

    expect(IntegrationTables::name('sales'))->toBe('sales');
});

test('compara um target do blueprint com o papel do motor', function (): void {
    config(['integrations.tables.product_store' => 'produto_loja']);

    expect(IntegrationTables::is('produto_loja', 'product_store'))->toBeTrue()
        ->and(IntegrationTables::is('product_store', 'product_store'))->toBeFalse();
});
