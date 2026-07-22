<?php

use App\Services\Integrations\FieldValueResolver;
use App\Services\Integrations\IntegrationPayloadBuilder;

/*
 * Datas em formato brasileiro, nas duas pontas:
 *   - entrada: transform `date_dmy` (a RP Info devolve "15/07/2026")
 *   - saída:   `date_query_format` (a RP Info só aceita "15-07-2026" na query;
 *              com ISO ela responde HTTP 200 e status "error")
 */

test('date_dmy converte dd/mm/yyyy, que o transform date não consegue parsear', function (): void {
    $resolver = new FieldValueResolver;

    expect($resolver->resolve(['data' => '15/07/2026'], 'data', ['date_dmy']))->toBe('2026-07-15')
        // O `date` legado interpreta a barra como m/d/Y: mês 15 não existe.
        ->and($resolver->resolve(['data' => '15/07/2026'], 'data', ['date']))->toBeNull();
});

test('date_dmy aceita data com hora e descarta o resto', function (): void {
    $resolver = new FieldValueResolver;

    expect($resolver->resolve(['d' => '21/08/2023 08:20:54.215'], 'd', ['date_dmy']))->toBe('2023-08-21');
});

test('date_dmy devolve null em valor inválido, vazio ou com overflow', function (?string $input): void {
    expect((new FieldValueResolver)->resolve(['d' => $input], 'd', ['date_dmy']))->toBeNull();
})->with([
    'vazio' => '',
    'nulo' => null,
    'texto' => 'ontem',
    'iso' => '2026-07-15',
    'overflow de mês' => '15/13/2026',
    'overflow de dia' => '32/07/2026',
]);

test('o transform date continua parseando dd-mm-yyyy — formato dos produtos', function (): void {
    expect((new FieldValueResolver)->resolve(['d' => '13-03-2026'], 'd', ['date']))->toBe('2026-03-13');
});

test('date_query_format converte a data da query para o formato da API', function (): void {
    $payload = (new IntegrationPayloadBuilder(
        config: ['connection' => ['params' => []]],
        requests: ['method' => 'GET', 'pagination_mode' => 'cursor'],
        pathConfig: [
            'date_fields' => ['start' => 'datainicial', 'end' => 'datafinal'],
            'date_query_format' => 'd-m-Y',
        ],
    ))->build('2026-07-15', '2026-07-15');

    expect($payload)->toBe(['datainicial' => '15-07-2026', 'datafinal' => '15-07-2026']);
});

test('sem date_query_format a data segue em ISO — nada muda para as integrações antigas', function (): void {
    $payload = (new IntegrationPayloadBuilder(
        config: ['connection' => ['params' => []]],
        requests: ['method' => 'GET', 'page_field' => 'pagina', 'page_size_field' => 'por_pagina'],
        pathConfig: [
            'date_fields' => ['start' => 'data_inicial', 'end' => 'data_final'],
            'max_page_size' => 500,
        ],
    ))->build('2026-07-15', '2026-07-16');

    expect($payload)->toBe([
        'pagina' => 1,
        'por_pagina' => 500,
        'data_inicial' => '2026-07-15',
        'data_final' => '2026-07-16',
    ]);
});
