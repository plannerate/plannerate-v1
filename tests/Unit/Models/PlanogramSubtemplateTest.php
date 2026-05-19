<?php

use App\Models\PlanogramSubtemplate;

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
