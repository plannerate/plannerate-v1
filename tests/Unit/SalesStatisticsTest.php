<?php

use Callcocam\LaravelRaptorPlannerate\Sales\SalesStatistics;

/**
 * Cobertura determinística das fórmulas estatísticas de vendas (ABC, Paper,
 * Estoque-Alvo) centralizadas em SalesStatistics. Os valores esperados replicam
 * o comportamento original dos services antes da extração.
 */
test('weightedAverage divides the weighted sum by the sum of active weights', function (): void {
    // (10*0.5 + 100*0.3 + 20*0.2) / (0.5+0.3+0.2) = 39 / 1.0 = 39
    expect(SalesStatistics::weightedAverage(10, 100, 20, 0.5, 0.3, 0.2))->toBe(39.0);
});

test('weightedAverage excludes the weight of a zero metric', function (): void {
    // qtde = 0 → só valor e margem entram: (100*0.3 + 20*0.2) / (0.3+0.2) = 34 / 0.5 = 68
    expect(SalesStatistics::weightedAverage(0, 100, 20, 0.5, 0.3, 0.2))->toBe(68.0);
});

test('weightedAverage returns 0 when no metric contributes', function (): void {
    expect(SalesStatistics::weightedAverage(0, 0, 0, 0.5, 0.3, 0.2))->toBe(0.0);
});

test('marketShare is the percentage of the category total', function (): void {
    expect(SalesStatistics::marketShare(25, 100))->toBe(25.0)
        ->and(SalesStatistics::marketShare(10, 0))->toBe(0.0); // guarda divisão por zero
});

test('growthRate compares current to previous, null without a base', function (): void {
    expect(SalesStatistics::growthRate(120, 100))->toBe(20.0)
        ->and(SalesStatistics::growthRate(80, 100))->toBe(-20.0)
        ->and(SalesStatistics::growthRate(50, 0))->toBeNull(); // produto novo / sem histórico
});

test('median handles odd, even and empty sequences', function (): void {
    expect(SalesStatistics::median([3, 1, 2]))->toBe(2.0)          // ímpar (ordenado: 1,2,3)
        ->and(SalesStatistics::median([1, 2, 3, 4]))->toBe(2.5)    // par → média dos centrais
        ->and(SalesStatistics::median([]))->toBeNull();
});

test('zScore returns tabulated values for common service levels', function (): void {
    expect(SalesStatistics::zScore(0.90))->toBe(1.2816)
        ->and(SalesStatistics::zScore(0.95))->toBe(1.6449)
        ->and(SalesStatistics::zScore(0))->toBe(0.0)     // fora de (0,1)
        ->and(SalesStatistics::zScore(1))->toBe(0.0);
});

test('zScore approximates non-tabulated probabilities', function (): void {
    $z = SalesStatistics::zScore(0.97);

    // 0.97 não está na tabela → usa aproximação; deve cair entre 0.95 e 0.99.
    expect($z)->toBeGreaterThan(1.6449)->toBeLessThan(2.3263);
});

test('variability is stddev over mean, zero-safe', function (): void {
    expect(SalesStatistics::variability(10, 2))->toBe(0.2)
        ->and(SalesStatistics::variability(0, 5))->toBe(0.0);
});

test('stock formulas round to whole units', function (): void {
    // segurança = z * desvio = 1.2816 * 10 = 12.816 → 13
    expect(SalesStatistics::safetyStock(1.2816, 10))->toBe(13.0)
        // mínimo = média * cobertura = 2 * 5 = 10
        ->and(SalesStatistics::minimumStock(2.0, 5))->toBe(10.0)
        // alvo = mínimo + segurança = 10 + 13 = 23
        ->and(SalesStatistics::targetStock(10, 13))->toBe(23.0);
});

test('mean is the arithmetic average, null when empty', function (): void {
    // (10 + 20 + 30 + 940) / 4 = 250 — o outlier puxa a média para longe da mediana (25)
    expect(SalesStatistics::mean([10, 20, 30, 940]))->toBe(250.0)
        ->and(SalesStatistics::median([10, 20, 30, 940]))->toBe(25.0)
        ->and(SalesStatistics::mean([]))->toBeNull();
});

test('percentileRank is the share of items at or below the value', function (): void {
    // [10, 10, 100, 100]: até 100 → 4 de 4 = 100%; até 10 → 2 de 4 = 50%
    expect(SalesStatistics::percentileRank(100, [10, 10, 100, 100]))->toBe(100.0)
        ->and(SalesStatistics::percentileRank(10, [10, 10, 100, 100]))->toBe(50.0)
        // valor abaixo de toda a população → 0%; população vazia → 0% (guarda de divisão)
        ->and(SalesStatistics::percentileRank(5, [10, 20]))->toBe(0.0)
        ->and(SalesStatistics::percentileRank(5, []))->toBe(0.0);
});

test('range is max minus min, zero-safe', function (): void {
    expect(SalesStatistics::range([10, 50, 90]))->toBe(80.0)
        // grupo sem dispersão: não há "quase acima" do limiar
        ->and(SalesStatistics::range([7, 7, 7]))->toBe(0.0)
        ->and(SalesStatistics::range([]))->toBe(0.0);
});
