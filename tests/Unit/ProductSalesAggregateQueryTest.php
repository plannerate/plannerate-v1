<?php

use Callcocam\LaravelRaptorPlannerate\Sales\ProductSalesAggregateQuery;

/**
 * monthPeriod() é o tradutor entre as duas convenções de período mensal que convivem
 * no sistema: month_from/month_to (auto-planograma, já em data) e start_month/end_month
 * (a UI, em Y-m). É função pura — testável sem banco.
 */
test('monthPeriod aceita a convenção do auto-planograma (month_from/month_to) sem alterar', function (): void {
    [$from, $to] = ProductSalesAggregateQuery::monthPeriod([
        'month_from' => '2026-03-01',
        'month_to' => '2026-04-30',
    ]);

    expect($from)->toBe('2026-03-01')
        ->and($to)->toBe('2026-04-30');
});

test('monthPeriod converte a convenção da UI (Y-m) para o intervalo fechado do mês', function (): void {
    [$from, $to] = ProductSalesAggregateQuery::monthPeriod([
        'start_month' => '2026-03',
        'end_month' => '2026-04',
    ]);

    // O fim vai até o ÚLTIMO dia do mês pedido — senão abril inteiro ficaria de fora,
    // já que sale_month é uma coluna date gravada no dia 1º.
    expect($from)->toBe('2026-03-01')
        ->and($to)->toBe('2026-04-30');
});

test('monthPeriod resolve o último dia de fevereiro em ano bissexto', function (): void {
    [, $to] = ProductSalesAggregateQuery::monthPeriod(['end_month' => '2024-02']);

    expect($to)->toBe('2024-02-29');
});

test('monthPeriod dá precedência à convenção explícita quando as duas chegam', function (): void {
    [$from, $to] = ProductSalesAggregateQuery::monthPeriod([
        'month_from' => '2026-01-01',
        'month_to' => '2026-01-31',
        'start_month' => '2026-06',
        'end_month' => '2026-06',
    ]);

    expect($from)->toBe('2026-01-01')
        ->and($to)->toBe('2026-01-31');
});

test('monthPeriod devolve null sem filtro de período — a análise soma a base inteira', function (): void {
    expect(ProductSalesAggregateQuery::monthPeriod([]))->toBe([null, null]);
});

test('monthPeriod trata string vazia como ausência de filtro', function (): void {
    // O controller usa filled(), mas um chamador desatento poderia mandar ''. Concatenar
    // '' com '-01' daria a data lixo '-01' e o where mataria todos os resultados.
    expect(ProductSalesAggregateQuery::monthPeriod([
        'start_month' => '',
        'end_month' => '   ',
    ]))->toBe([null, null]);
});

test('monthPeriod aceita um limite só (período aberto de um lado)', function (): void {
    [$from, $to] = ProductSalesAggregateQuery::monthPeriod(['start_month' => '2026-03']);

    expect($from)->toBe('2026-03-01')
        ->and($to)->toBeNull();
});
