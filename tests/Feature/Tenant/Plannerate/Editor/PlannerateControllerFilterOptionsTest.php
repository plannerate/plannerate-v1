<?php

use App\Http\Controllers\Tenant\Plannerate\Editor\PlannerateController;

it('removes planogram filter config and keeps other filters', function () {
    $controller = app(PlannerateController::class);

    $method = new ReflectionMethod($controller, 'withoutPlanogramFilter');
    $method->setAccessible(true);

    $filters = [
        [
            'name' => 'planogram_id',
            'label' => 'Planograma',
            'options' => [['value' => 'p1', 'label' => 'P1']],
        ],
        [
            'name' => 'status',
            'label' => 'Status',
            'options' => [['value' => 'in_progress', 'label' => 'Em andamento']],
        ],
        [
            'name' => 'assigned_to',
            'label' => 'Atribuido para',
            'options' => [['value' => 'u1', 'label' => 'Usuario 1']],
        ],
    ];

    /** @var array<int, array{name: string, label: string, options: array<int, array{value: string, label: string}>}> $result */
    $result = $method->invoke($controller, $filters);

    expect($result)
        ->toHaveCount(2)
        ->and(collect($result)->pluck('name')->all())
        ->toBe(['status', 'assigned_to']);
});
