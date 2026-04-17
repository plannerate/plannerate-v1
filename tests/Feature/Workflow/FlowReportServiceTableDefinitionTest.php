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
use Tests\Feature\Workflow\CustomTableForFlowReportServiceTest;

it('allows custom table providers in flow report service', function () {
    $flow = Flow::query()->create([
        'name' => 'Flow Table',
        'slug' => 'flow-table-'.Str::lower(Str::random(5)),
        'status' => FlowStatus::Active->value,
    ]);

    $template = FlowStepTemplate::query()->create([
        'flow_id' => $flow->id,
        'name' => 'Etapa Table',
        'slug' => 'etapa-table-'.Str::lower(Str::random(5)),
        'suggested_order' => 1,
        'is_active' => true,
    ]);

    $configStep = FlowConfigStep::query()->create([
        'configurable_type' => 'tests.configurable',
        'configurable_id' => (string) Str::ulid(),
        'flow_step_template_id' => $template->id,
        'name' => 'Config table',
        'order' => 1,
        'is_active' => true,
        'is_required' => true,
    ]);

    $user = User::factory()->create();

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
        ->withCharts([StatusChart::class])
        ->withTables([
            CustomTableForFlowReportServiceTest::class,
        ])
        ->build([
            'flow_slug' => $flow->slug,
        ]);

    expect($report['tables'])->toHaveKey('custom_table')
        ->and($report['tables']['custom_table']['label'])->toBe('Tabela customizada')
        ->and($report['tables']['custom_table']['data'][0]['value'])->toBe(1);
});
