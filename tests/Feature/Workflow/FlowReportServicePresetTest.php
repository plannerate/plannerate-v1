<?php

use App\Models\User;
use Callcocam\LaravelRaptorFlow\Enums\FlowStatus;
use Callcocam\LaravelRaptorFlow\Models\Flow;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;
use Callcocam\LaravelRaptorFlow\Services\Reports\FlowReportService;
use Callcocam\LaravelRaptorFlow\Services\Reports\Presets\OverviewFlowReportPreset;
use Illuminate\Support\Str;

it('builds flow report using package preset definitions', function () {
    $user = User::factory()->create();

    $flow = Flow::query()->create([
        'name' => 'Flow Preset',
        'slug' => 'flow-preset-'.Str::lower(Str::random(5)),
        'status' => FlowStatus::Active->value,
    ]);

    $template = FlowStepTemplate::query()->create([
        'flow_id' => $flow->id,
        'name' => 'Etapa Preset',
        'slug' => 'etapa-preset-'.Str::lower(Str::random(5)),
        'suggested_order' => 1,
        'is_active' => true,
    ]);

    $configStep = FlowConfigStep::query()->create([
        'configurable_type' => 'tests.configurable',
        'configurable_id' => (string) Str::ulid(),
        'flow_step_template_id' => $template->id,
        'name' => 'Config preset',
        'order' => 1,
        'is_active' => true,
        'is_required' => true,
    ]);

    FlowExecution::query()->create([
        'workable_type' => 'tests.workable',
        'workable_id' => (string) Str::ulid(),
        'flow_config_step_id' => $configStep->id,
        'flow_step_template_id' => $template->id,
        'status' => FlowStatus::InProgress,
        'current_responsible_id' => $user->id,
        'estimated_duration_days' => 1,
    ]);

    $report = app(FlowReportService::class)
        ->withPreset(OverviewFlowReportPreset::class)
        ->build([
            'flow_slug' => $flow->slug,
        ]);

    expect($report['charts'])->toHaveKeys([
        'status',
        'responsible',
        'sla',
        'step_avg_effective_minutes',
    ])->and($report['charts']['status']['type'])->toBe('doughnut')
        ->and($report['tables'])->toHaveKey('responsible_activity')
        ->and($report['tables']['responsible_activity']['label'])->toBe('Detalhamento por responsável')
        ->and($report['tables']['responsible_activity']['data'])->toBeArray();
});
