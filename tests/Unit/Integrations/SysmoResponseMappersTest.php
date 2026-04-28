<?php

use App\Services\Integrations\Sysmo\SysmoProductsResponseMapper;
use App\Services\Integrations\Sysmo\SysmoSalesResponseMapper;

test('sysmo products mapper normalizes product fields', function () {
    $mapper = new SysmoProductsResponseMapper;

    $mapped = $mapper->mapMany([
        [
            'produto' => '66526',
            'descricao' => 'REFRIGERANTE COCA COLA 1.5L',
            'marca' => 'Marca X',
            'departamento' => '500',
            'departamento_descricao' => 'MERCEARIA',
            'categoria' => '5',
            'categoria_descricao' => 'BEBIDAS NAO ALCOOLICAS',
            'gtins' => [
                'completo' => [
                    ['gtin' => '789000000001', 'principal' => 'N'],
                    ['gtin' => '789000000002', 'principal' => 'S'],
                ],
            ],
            'fornecedores' => [
                ['codigo' => '1', 'razao_social' => 'Fornecedor A', 'cpf_cnpj' => '111', 'principal' => 'N'],
                ['codigo' => '2', 'razao_social' => 'Fornecedor Principal', 'cpf_cnpj' => '222', 'principal' => 'S'],
            ],
            'preco' => '8.99',
            'status' => 'ATIVO',
            'unidade' => 'UN',
        ],
    ]);

    expect($mapped[0])->toMatchArray([
        'external_id' => '66526',
        'ean' => '789000000002',
        'name' => 'REFRIGERANTE COCA COLA 1.5L',
        'brand' => 'Marca X',
        'department_code' => '500',
        'department_description' => 'MERCEARIA',
        'category_code' => '5',
        'category_description' => 'BEBIDAS NAO ALCOOLICAS',
        'supplier_code' => '2',
        'supplier_name' => 'Fornecedor Principal',
        'supplier_document' => '222',
        'preco_normal' => 8.99,
        'status' => 'ATIVO',
        'unit' => 'UN',
    ]);
});

test('sysmo sales mapper normalizes sales fields', function () {
    $mapper = new SysmoSalesResponseMapper;

    $mapped = $mapper->mapMany([
        [
            'produto' => '10022',
            'codigo_venda' => 'V-100',
            'promocao' => 'N',
            'empresa' => '7',
            'data_venda' => '2026-04-28 10:00:00',
            'quantidade' => '2',
            'valor_liquido' => '59.9',
            'valor_impostos' => '14.78',
            'preco_efetivo' => '29.198',
            'custo_aquisicao' => '23.3175',
            'custo_medio_loja' => '23.226',
            'custo_medio_geral' => '23.307',
            'custo_comercial' => '23.3175',
            'cnpj' => '12345678000199',
        ],
    ]);

    expect($mapped[0])->toMatchArray([
        'external_id' => 'V-100',
        'product_code' => '10022',
        'codigo_erp' => '10022',
        'promocao' => 'N',
        'empresa' => '7',
        'sold_at' => '2026-04-28 10:00:00',
        'quantity' => 2.0,
        'valor_liquido' => 59.9,
        'valor_impostos' => 14.78,
        'unit_price' => 29.198,
        'total_price' => 59.9,
        'custo_aquisicao' => 23.3175,
        'custo_medio_loja' => 23.226,
        'custo_medio_geral' => 23.307,
        'custo_comercial' => 23.3175,
        'store_identifier' => '12345678000199',
    ]);
});
