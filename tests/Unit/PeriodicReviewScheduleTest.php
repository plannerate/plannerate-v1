<?php

use App\Models\Planogram;
use App\Support\Workflow\PeriodicReviewSchedule;

it('calcula o vencimento como completed_at + (end_date - start_date)', function (): void {
    $planogram = new Planogram;
    $planogram->start_date = '2026-01-01';
    $planogram->end_date = '2026-01-31';
    $planogram->completed_at = '2026-02-10 12:00:00';

    $due = PeriodicReviewSchedule::computeDueAt($planogram);

    $expected = $planogram->completed_at->copy()->add(
        $planogram->start_date->diffAsCarbonInterval($planogram->end_date)
    );

    expect($due)->not->toBeNull();
    expect($due->equalTo($expected))->toBeTrue();
});

it('não agenda quando faltam datas', function (): void {
    $planogram = new Planogram;
    $planogram->start_date = '2026-01-01';
    $planogram->completed_at = '2026-02-10 12:00:00';

    expect(PeriodicReviewSchedule::computeDueAt($planogram))->toBeNull();
});

it('não agenda quando as datas estão invertidas', function (): void {
    $planogram = new Planogram;
    $planogram->start_date = '2026-01-31';
    $planogram->end_date = '2026-01-01';
    $planogram->completed_at = '2026-02-10 12:00:00';

    expect(PeriodicReviewSchedule::computeDueAt($planogram))->toBeNull();
});
