<?php

use Callcocam\LaravelRaptorPlannerate\Sales\SalesSummary;

/**
 * Garante que as fórmulas derivadas de vendas (fonte única de verdade) estão
 * corretas e que os guards de divisão por zero não estouram.
 */
test('derives per-unit and percentage metrics from raw sums', function (): void {
    // qtd = 5, faturamento = 65, custo = 29, margem líquida = 18, 1 de 2 registros em promoção
    $summary = new SalesSummary(
        totalRecords: 2,
        totalQuantity: 5.0,
        totalValue: 65.0,
        totalAcquisitionCost: 29.0,
        totalMargemContribuicao: 18.0,
        promoRecords: 1,
    );

    expect(round($summary->avgPrice(), 2))->toBe(13.0)        // 65 / 5
        ->and(round($summary->avgCost(), 2))->toBe(5.8)        // 29 / 5
        ->and(round($summary->avgMargin(), 2))->toBe(3.6)      // 18 / 5
        ->and(round($summary->grossProfitTotal(), 2))->toBe(36.0)   // 65 − 29
        ->and(round($summary->grossProfitUnit(), 2))->toBe(7.2)     // 13 − 5,8
        ->and(round($summary->grossMarginPct(), 4))->toBe(round(36 / 65 * 100, 4))
        ->and(round($summary->netMarginPct(), 4))->toBe(round(18 / 65 * 100, 4))
        ->and($summary->promoPercent())->toBe(50.0);           // 1 / 2 × 100
});

test('guards against division by zero when there are no sales', function (): void {
    $summary = new SalesSummary;

    expect($summary->avgPrice())->toBe(0.0)
        ->and($summary->avgCost())->toBe(0.0)
        ->and($summary->avgMargin())->toBe(0.0)
        ->and($summary->grossMarginPct())->toBe(0.0)
        ->and($summary->netMarginPct())->toBe(0.0)
        ->and($summary->promoPercent())->toBe(0.0);
});

test('fromAggregate(null) produces an empty summary', function (): void {
    $summary = SalesSummary::fromAggregate(null);

    expect($summary->totalRecords)->toBe(0)
        ->and($summary->totalValue)->toBe(0.0)
        ->and($summary->avgPrice())->toBe(0.0);
});

test('toArray exposes both raw sums and derived metrics', function (): void {
    $array = (new SalesSummary(
        totalRecords: 2,
        totalQuantity: 5.0,
        totalValue: 65.0,
        totalAcquisitionCost: 29.0,
        totalMargemContribuicao: 18.0,
        promoRecords: 1,
    ))->toArray();

    expect($array)->toHaveKeys([
        'total_records', 'total_quantity', 'total_value',
        'avg_price', 'avg_cost', 'avg_margin',
        'gross_profit_unit', 'gross_profit_total',
        'gross_margin_pct', 'net_margin_pct', 'promo_percent',
    ])
        ->and(round($array['avg_price'], 2))->toBe(13.0)
        ->and($array['promo_percent'])->toBe(50.0);
});
