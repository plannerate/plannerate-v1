<?php

use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;

test('cloneWithSlots lança InvalidArgumentException quando targetModules igual ao atual', function (): void {
    $sub = new PlanogramSubtemplate;
    $sub->num_modules = 2;

    expect(fn () => $sub->cloneWithSlots(2))
        ->toThrow(InvalidArgumentException::class);
});

test('cloneWithSlots lança InvalidArgumentException quando targetModules menor que o atual', function (): void {
    $sub = new PlanogramSubtemplate;
    $sub->num_modules = 3;

    expect(fn () => $sub->cloneWithSlots(2))
        ->toThrow(InvalidArgumentException::class);
});

test('slot_defaults é convertido para array pelo cast do model', function (): void {
    $sub = new PlanogramSubtemplate;
    $sub->slot_defaults = [
        'min_facings' => 2,
        'priority' => 4,
        'price_order' => 'desc',
    ];

    expect($sub->slot_defaults)->toBeArray()
        ->and($sub->slot_defaults['min_facings'])->toBe(2)
        ->and($sub->slot_defaults['priority'])->toBe(4)
        ->and($sub->slot_defaults['price_order'])->toBe('desc');
});
