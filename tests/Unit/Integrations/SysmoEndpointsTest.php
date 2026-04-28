<?php

use App\Services\Integrations\Sysmo\SysmoEndpoints;

test('sysmo endpoints include configured products and sales routes', function () {
    $endpoints = new SysmoEndpoints;
    $all = $endpoints->all();

    expect($all)->toHaveKey('products')
        ->and($all)->toHaveKey('sales')
        ->and($all['products'])->toBe('sysmo-integrador-api/api/integradorService/hubprodutos.listar_produtos')
        ->and($all['sales'])->toBe('sysmo-integrador-api/api/integradorService/hubvendas.vendas_produtos');
});

test('sysmo endpoint resolver returns endpoint by key', function () {
    $endpoints = new SysmoEndpoints;

    expect($endpoints->get('product'))
        ->toBe('sysmo-integrador-api/api/integradorService/hubprodutos.consultar_produto');
});
