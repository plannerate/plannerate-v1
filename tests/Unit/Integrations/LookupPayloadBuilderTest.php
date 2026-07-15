<?php

use App\Services\Integrations\Lookup\LookupPayloadBuilder;

/**
 * Config mínimo de tenant (POST) com params base habilitados/desabilitados.
 *
 * @return array<string, mixed>
 */
function lookupTenantConfig(): array
{
    return [
        'connection' => [
            'base_url' => 'https://api.example.test',
            'body' => [
                ['key' => 'partner_key', 'value' => 'TESTE', 'enabled' => true],
                ['key' => 'ignorado', 'value' => 'x', 'enabled' => false],
            ],
        ],
    ];
}

/** @return array<string, mixed> */
function lookupRequests(): array
{
    return [
        'method' => 'POST',
        'page_field' => 'pagina',
        'page_size_field' => 'tamanho_pagina',
        'max_page_size' => 1000,
    ];
}

test('monta payload de vendas com código, loja, datas, extra_params e paginação', function (): void {
    $lookup = [
        'lookup_field' => 'produto',
        'store_field' => 'empresa',
        'extra_params' => ['tipo_consulta' => 'produto'],
        'date_fields' => ['start' => 'data_inicial', 'end' => 'data_final'],
    ];

    $payload = (new LookupPayloadBuilder)->build(
        lookupTenantConfig(),
        lookupRequests(),
        $lookup,
        '7891234567895',
        '12345678000199',
        '2026-01-01',
        '2026-07-15',
    );

    expect($payload)->toBe([
        'partner_key' => 'TESTE',
        'tipo_consulta' => 'produto',
        'produto' => '7891234567895',
        'empresa' => '12345678000199',
        'data_inicial' => '2026-01-01',
        'data_final' => '2026-07-15',
        'pagina' => 1,
        'tamanho_pagina' => 1000,
    ]);
});

test('omite loja e datas quando não configuradas ou nulas', function (): void {
    $lookup = [
        'lookup_field' => 'produto',
        'extra_params' => ['somente_precos' => 'N'],
    ];

    $payload = (new LookupPayloadBuilder)->build(
        lookupTenantConfig(),
        lookupRequests(),
        $lookup,
        '66526',
    );

    expect($payload)->toBe([
        'partner_key' => 'TESTE',
        'somente_precos' => 'N',
        'produto' => '66526',
        'pagina' => 1,
        'tamanho_pagina' => 1000,
    ]);
});

test('método do lookup sobrepõe o método base dos requests', function (): void {
    $builder = new LookupPayloadBuilder;

    expect($builder->method(['method' => 'GET'], ['method' => 'post']))->toBe('post')
        ->and($builder->method(['method' => 'POST'], []))->toBe('post');
});

test('GET usa connection.params em vez de connection.body', function (): void {
    $config = [
        'connection' => [
            'base_url' => 'https://api.example.test',
            'params' => [
                ['key' => 'token', 'value' => 'abc', 'enabled' => true],
            ],
            'body' => [
                ['key' => 'nao_usar', 'value' => 'y', 'enabled' => true],
            ],
        ],
    ];

    $payload = (new LookupPayloadBuilder)->build(
        $config,
        ['method' => 'GET', 'page_field' => 'page', 'page_size_field' => 'per_page', 'max_page_size' => 50],
        ['lookup_field' => 'ean'],
        '7891234567895',
    );

    expect($payload)->toBe([
        'token' => 'abc',
        'ean' => '7891234567895',
        'page' => 1,
        'per_page' => 50,
    ]);
});
