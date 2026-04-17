<?php

use App\Models\User;
use Callcocam\LaravelRaptorFlow\Enums\FlowStatus;
use Callcocam\LaravelRaptorFlow\Models\Flow;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;
use Callcocam\LaravelRaptorFlow\Services\Reports\Charts\StatusChart;
use Callcocam\LaravelRaptorFlow\Services\Reports\FlowReportService;
use Illuminate\Support\Str;

it('allows chart classes and chart type override in flow report service', function () {
    $user = User::factory()->create();

    $flow = Flow::query()->create([
        'name' => 'Flow Report',
        'slug' => 'flow-report-'.Str::lower(Str::random(5)),
        'status' => FlowStatus::Active->value,
    ]);

    $template = FlowStepTemplate::query()->create([
        'flow_id' => $flow->id,
        'name' => 'Etapa Report',
        'slug' => 'etapa-report-'.Str::lower(Str::random(5)),
        'suggested_order' => 1,
        'is_active' => true,
    ]);

    $configStep = FlowConfigStep::query()->create([
        'configurable_type' => 'tests.configurable',
        'configurable_id' => (string) Str::ulid(),
        'flow_step_template_id' => $template->id,
        'name' => 'Config report',
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
        ->withCharts([
            [
                'chart' => StatusChart::class,
                'type' => 'bar',
                'label' => 'Status customizado',
            ],
        ])
        ->build([
            'flow_slug' => $flow->slug,
        ]);

    expect($report['charts']['status']['type'])->toBe('bar')
        ->and($report['charts']['status']['label'])->toBe('Status customizado')
        ->and($report['charts']['status']['data']['datasets'][0]['data'][1])->toBe(1);
});
