<?php

/**
 * Aviso na TELA quando a blocagem vertical é pedida mas não pode ser aplicada.
 *
 * O usuário liga o modo vertical, mas ele só forma coluna quando uma categoria ocupa 2+
 * prateleiras EXCLUSIVAS consecutivas no mesmo módulo. Sob compressão isso quase nunca
 * acontece e a geração cai no horizontal — antes, sem nenhum aviso na tela, o que dava a
 * impressão de que o vertical tinha funcionado.
 */

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\GreedyShelfPlacer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\TemplatePlacementEngine;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductOrderingService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductSizeResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;

function layoutAlerts(bool $verticalRequested, int $groupsFormed): array
{
    $engine = new TemplatePlacementEngine(
        new ProductWidthResolver,
        new ProductSizeResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
        new ProductOrderingService(new ProductSizeResolver),
    );

    $method = new ReflectionMethod($engine, 'buildLayoutAlerts');
    $method->setAccessible(true);

    return $method->invoke($engine, $verticalRequested, $groupsFormed);
}

test('vertical pedido e nenhum grupo formado gera o alerta vertical_nao_aplicado', function (): void {
    $alerts = layoutAlerts(verticalRequested: true, groupsFormed: 0);

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]['type'])->toBe('vertical_nao_aplicado')
        ->and($alerts[0]['message'])->toContain('horizontal');
});

test('vertical pedido e ao menos um grupo formado não gera alerta (funcionou)', function (): void {
    expect(layoutAlerts(verticalRequested: true, groupsFormed: 3))->toBe([]);
});

test('layout horizontal nunca gera o alerta vertical', function (): void {
    expect(layoutAlerts(verticalRequested: false, groupsFormed: 0))->toBe([]);
});
