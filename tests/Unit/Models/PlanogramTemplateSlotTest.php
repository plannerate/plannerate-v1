<?php

use App\Models\PlanogramTemplateSlot;

test('grouping_normalized não é mais derivado de grouping no slot', function (): void {
    $slot = new PlanogramTemplateSlot;
    $slot->setAttribute('grouping', 'CEREAIS | FARINÁCEOS');

    expect($slot->grouping_normalized)->toBeNull();
});

test('grouping e grouping_normalized não estão no fillable do slot', function (): void {
    $fillable = (new PlanogramTemplateSlot)->getFillable();

    expect($fillable)->not->toContain('grouping')
        ->and($fillable)->not->toContain('grouping_normalized');
});

test('category_id está no fillable do slot', function (): void {
    $fillable = (new PlanogramTemplateSlot)->getFillable();

    expect($fillable)->toContain('category_id');
});
