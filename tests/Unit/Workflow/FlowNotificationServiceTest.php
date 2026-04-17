<?php

use Callcocam\LaravelRaptorFlow\Enums\FlowNotificationPriority;
use Callcocam\LaravelRaptorFlow\Enums\FlowNotificationType;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Services\FlowNotificationService;
use Tests\TestCase;

uses(TestCase::class);

it('builds assigned payload with expected defaults', function () {
    $execution = new FlowExecution([
        'workable_type' => 'App\\Models\\Workflow\\PlanogramWorkflow',
        'workable_id' => '01HWORKABLE',
        'flow_config_step_id' => '01HSTEP',
    ]);

    $service = new FlowNotificationService;

    $payload = $service->buildAssignedPayload(
        $execution,
        '01HUSER',
        null,
        null,
        FlowNotificationPriority::High,
        ['source' => 'test'],
    );

    expect($payload['user_id'])->toBe('01HUSER')
        ->and($payload['type'])->toBe(FlowNotificationType::Assigned)
        ->and($payload['priority'])->toBe(FlowNotificationPriority::High)
        ->and($payload['title'])->toBe('Nova responsabilidade atribuída')
        ->and($payload['metadata'])->toBe(['source' => 'test']);
});

it('builds moved payload with transition metadata', function () {
    $execution = new FlowExecution([
        'workable_type' => 'App\\Models\\Workflow\\PlanogramWorkflow',
        'workable_id' => '01HWORKABLE2',
        'flow_config_step_id' => '01HTOSTEP',
    ]);

    $fromStep = new FlowConfigStep([
        'name' => 'Etapa 1',
    ]);
    $fromStep->setAttribute('id', '01HFROMSTEP');

    $toStep = new FlowConfigStep([
        'name' => 'Etapa 2',
    ]);
    $toStep->setAttribute('id', '01HTOSTEP');

    $service = new FlowNotificationService;

    $payload = $service->buildMovedPayload(
        $execution,
        '01HUSER2',
        $fromStep,
        $toStep,
        ['triggered_by' => 'system'],
    );

    expect($payload['type'])->toBe(FlowNotificationType::Moved)
        ->and($payload['flow_config_step_id'])->toBe('01HTOSTEP')
        ->and($payload['metadata'])->toBe([
            'from_step_id' => '01HFROMSTEP',
            'to_step_id' => '01HTOSTEP',
            'triggered_by' => 'system',
        ]);
});
