<?php

use Callcocam\LaravelRaptorPlannerate\Services\Analysis\TargetStockService;

/*
 * Testes da configuração de parâmetros do Estoque Alvo.
 *
 * Os defaults (planilha VBA: A 2d/70%, B 5d/80%, C 7d/90%) podem ser
 * sobrescritos via config('plannerate.auto_planogram.target_stock') e,
 * pontualmente, pelos setters do service.
 */

/**
 * Expõe os parâmetros privados do service para asserção.
 */
function targetStockParams(TargetStockService $service): array
{
    $reflection = new ReflectionClass($service);

    return [
        'niveis' => $reflection->getProperty('niveisServico')->getValue($service),
        'dias' => $reflection->getProperty('coberturaDias')->getValue($service),
    ];
}

it('usa os defaults da planilha VBA quando não há config', function (): void {
    config()->set('plannerate.auto_planogram.target_stock', []);

    $params = targetStockParams(new TargetStockService);

    expect($params['niveis'])->toBe(['A' => 0.7, 'B' => 0.8, 'C' => 0.9])
        ->and($params['dias'])->toBe(['A' => 2, 'B' => 5, 'C' => 7]);
});

it('config sobrescreve os defaults de nível de serviço e cobertura', function (): void {
    config()->set('plannerate.auto_planogram.target_stock', [
        'service_levels' => ['A' => 0.95, 'B' => 0.9, 'C' => 0.85],
        'coverage_days' => ['A' => 3, 'B' => 6, 'C' => 10],
    ]);

    $params = targetStockParams(new TargetStockService);

    expect($params['niveis'])->toBe(['A' => 0.95, 'B' => 0.9, 'C' => 0.85])
        ->and($params['dias'])->toBe(['A' => 3, 'B' => 6, 'C' => 10]);
});

it('config parcial sobrescreve apenas as classes informadas', function (): void {
    config()->set('plannerate.auto_planogram.target_stock', [
        'service_levels' => ['A' => 0.95],
    ]);

    $params = targetStockParams(new TargetStockService);

    expect($params['niveis'])->toBe(['A' => 0.95, 'B' => 0.8, 'C' => 0.9])
        ->and($params['dias'])->toBe(['A' => 2, 'B' => 5, 'C' => 7]);
});

it('setters sobrescrevem a config em tempo de execução', function (): void {
    config()->set('plannerate.auto_planogram.target_stock', [
        'service_levels' => ['A' => 0.95, 'B' => 0.9, 'C' => 0.85],
    ]);

    $service = (new TargetStockService)
        ->setServiceLevels(0.6, 0.7, 0.8)
        ->setCoverageDays(1, 2, 3);

    $params = targetStockParams($service);

    expect($params['niveis'])->toBe(['A' => 0.6, 'B' => 0.7, 'C' => 0.8])
        ->and($params['dias'])->toBe(['A' => 1, 'B' => 2, 'C' => 3]);
});
