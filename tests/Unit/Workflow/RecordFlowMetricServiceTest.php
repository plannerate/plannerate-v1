<?php

use Callcocam\LaravelRaptorFlow\Enums\FlowStatus;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Services\RecordFlowMetricService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

it('builds metric payload with duration and deviation calculation', function () {
    Carbon::setTestNow('2026-03-16 12:00:00');

    $execution = new FlowExecution([
        'workable_type' => 'App\\Models\\Workflow\\PlanogramWorkflow',
        'workable_id' => '01HWORKABLE',
        'flow_config_step_id' => '01HSTEP',
        'flow_step_template_id' => '01HTEMPLATE',
        'status' => FlowStatus::InProgress,
        'started_at' => Carbon::parse('2026-03-16 10:00:00'),
        'sla_date' => Carbon::parse('2026-03-16 13:00:00'),
        'paused_duration_minutes' => 15,
        'estimated_duration_days' => 0,
    ]);

    $fromStep = new FlowConfigStep([
        'flow_step_template_id' => '01HTEMPLATE',
        'estimated_duration_days' => 1,
    ]);
    $fromStep->setAttribute('id', '01HSTEP');

    $toStep = new FlowConfigStep([
        'flow_step_template_id' => '01HNEXTTEMPLATE',
    ]);
    $toStep->setAttribute('id', '01HNEXTSTEP');

    $service = new RecordFlowMetricService;

    $payload = $service->buildStepTransitionPayload(
        $execution,
        $fromStep,
        $toStep,
        Carbon::parse('2026-03-16 12:00:00'),
    );

    expect($payload['total_duration_minutes'])->toBe(120)
        ->and($payload['effective_work_minutes'])->toBe(105)
        ->and($payload['estimated_duration_minutes'])->toBe(1440)
        ->and($payload['deviation_minutes'])->toBe(-1335)
        ->and($payload['is_on_time'])->toBeTrue()
        ->and($payload['metadata']['from_step_id'])->toBe('01HSTEP')
        ->and($payload['metadata']['to_step_id'])->toBe('01HNEXTSTEP');

    Carbon::setTestNow();
});
