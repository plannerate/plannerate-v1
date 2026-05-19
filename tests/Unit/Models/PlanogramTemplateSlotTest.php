<?php

use App\Models\PlanogramTemplateSlot;
use Illuminate\Support\Str;

test('grouping_normalized é derivado automaticamente ao setar grouping no slot', function (): void {
    $slot = new PlanogramTemplateSlot;
    $slot->grouping = 'CEREAIS | FARINÁCEOS | FAROFA DE MANDIOCA';
    $slot->syncGroupingNormalizedFromGrouping();

    expect($slot->grouping_normalized)->toBe(Str::slug('CEREAIS | FARINÁCEOS | FAROFA DE MANDIOCA'));
});

test('grouping vazio resulta em grouping_normalized null no slot', function (): void {
    $slot = new PlanogramTemplateSlot;
    $slot->grouping = '';
    $slot->syncGroupingNormalizedFromGrouping();

    expect($slot->grouping_normalized)->toBeNull();
});

test('grouping null resulta em grouping_normalized null no slot', function (): void {
    $slot = new PlanogramTemplateSlot;
    $slot->grouping = null;
    $slot->syncGroupingNormalizedFromGrouping();

    expect($slot->grouping_normalized)->toBeNull();
});
