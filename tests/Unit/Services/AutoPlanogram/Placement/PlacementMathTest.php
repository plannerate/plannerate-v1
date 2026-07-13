<?php

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\PlacementMath;

/**
 * Fase 1 do plano de precisão (docs/gondola-precisao-automatica/): a aritmética de
 * encaixe roda em float exato e o arredondamento acontece só na persistência.
 */
test('produto que ocupa exatamente o espaço restante cabe (não é rejeitado por erro de float)', function (): void {
    // 0.1 + 0.2 > 0.3 em ponto flutuante. Sem tolerância, um produto que cabe
    // EXATAMENTE no espaço restante seria rejeitado — e a prateleira ficaria com sobra.
    expect(PlacementMath::fits(0.1, 0.2, 0.3))->toBeTrue();
});

test('produto maior que o espaço restante não cabe', function (): void {
    expect(PlacementMath::fits(90.0, 11.0, 100.0))->toBeFalse();
});

test('a tolerância não deixa passar produto que realmente não cabe', function (): void {
    // 1 milímetro de excesso já é excesso — a tolerância é de 1e-6 cm, não de folga real.
    expect(PlacementMath::fits(90.0, 10.1, 100.0))->toBeFalse();
});

test('segmentos ficam contíguos: o fim de um é exatamente o começo do próximo', function (): void {
    // Larguras fracionárias (3,4cm) que, arredondadas isoladamente, acumulariam erro.
    $widths = [3.4, 3.4, 3.4, 3.4, 3.4];
    $x = 0.0;
    $bounds = [];

    foreach ($widths as $w) {
        $bounds[] = PlacementMath::segmentBounds($x, $w);
        $x += $w;
    }

    // Contiguidade: posição + largura de cada segmento = posição do seguinte.
    foreach ($bounds as $i => [$position, $width]) {
        if (isset($bounds[$i + 1])) {
            expect($position + $width)->toBe($bounds[$i + 1][0]);
        }
    }
});

test('a largura total persistida não deriva do total exato (sem erro acumulado)', function (): void {
    // 5 produtos de 3,4cm = 17cm exatos. Arredondando cada um isoladamente (3cm),
    // o total viraria 15cm — 2cm de sobra fantasma na prateleira.
    $widths = [3.4, 3.4, 3.4, 3.4, 3.4];
    $x = 0.0;
    $totalPersisted = 0;

    foreach ($widths as $w) {
        [, $width] = PlacementMath::segmentBounds($x, $w);
        $totalPersisted += $width;
        $x += $w;
    }

    expect($totalPersisted)->toBe(17);
});

test('produto de largura irrisória vira segmento de 1cm em vez de zero (invisível)', function (): void {
    [, $width] = PlacementMath::segmentBounds(0.0, 0.2);

    expect($width)->toBe(1);
});

test('a folga entre produtos não é cobrada antes do primeiro produto', function (): void {
    // A folga é ENTRE produtos — cobrá-la no início desperdiçaria espaço na borda.
    expect(PlacementMath::gapBefore(0.0, 2.0))->toBe(0.0)
        ->and(PlacementMath::gapBefore(30.0, 2.0))->toBe(2.0);
});

test('espaçamento desligado (default) não consome largura nenhuma', function (): void {
    expect(PlacementMath::gapBefore(30.0, 0.0))->toBe(0.0)
        ->and(PlacementMath::productSpacingCm())->toBe(0.0);
});
