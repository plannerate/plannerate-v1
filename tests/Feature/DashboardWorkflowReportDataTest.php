<?php

use App\Models\User;
use Callcocam\LaravelRaptorFlow\Enums\FlowAction;
use Callcocam\LaravelRaptorFlow\Enums\FlowStatus;
use Callcocam\LaravelRaptorFlow\Models\Flow;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Models\FlowHistory;
use Callcocam\LaravelRaptorFlow\Models\FlowMetric;
use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;
use Illuminate\Support\Str;

it('returns workflow report payload filtered by flow slug', function () {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $flowA = Flow::query()->create([
        'name' => 'Flow A',
        'slug' => 'flow-a-'.Str::lower(Str::random(5)),
        'status' => FlowStatus::Active->value,
    ]);

    $flowB = Flow::query()->create([
        'name' => 'Flow B',
        'slug' => 'flow-b-'.Str::lower(Str::random(5)),
        'status' => FlowStatus::Active->value,
    ]);

    $templateA = FlowStepTemplate::query()->create([
        'flow_id' => $flowA->id,
        'name' => 'Etapa A',
        'slug' => 'etapa-a-'.Str::lower(Str::random(5)),
        'suggested_order' => 1,
        'is_active' => true,
        'estimated_duration_days' => 1,
    ]);

    $templateB = FlowStepTemplate::query()->create([
        'flow_id' => $flowB->id,
        'name' => 'Etapa B',
        'slug' => 'etapa-b-'.Str::lower(Str::random(5)),
        'suggested_order' => 1,
        'is_active' => true,
        'estimated_duration_days' => 1,
    ]);

    $configStepA = FlowConfigStep::query()->create([
        'configurable_type' => 'tests.configurable',
        'configurable_id' => (string) Str::ulid(),
        'flow_step_template_id' => $templateA->id,
        'name' => 'Config A',
        'order' => 1,
        'is_active' => true,
        'is_required' => true,
        'estimated_duration_days' => 1,
    ]);

    $configStepB = FlowConfigStep::query()->create([
        'configurable_type' => 'tests.configurable',
        'configurable_id' => (string) Str::ulid(),
        'flow_step_template_id' => $templateB->id,
        'name' => 'Config B',
        'order' => 1,
        'is_active' => true,
        'is_required' => true,
        'estimated_duration_days' => 1,
    ]);

    $executionA = FlowExecution::query()->create([
        'workable_type' => 'tests.workable',
        'workable_id' => (string) Str::ulid(),
        'flow_config_step_id' => $configStepA->id,
        'flow_step_template_id' => $templateA->id,
        'status' => FlowStatus::InProgress,
        'current_responsible_id' => $user->id,
        'execution_started_by' => $user->id,
        'started_at' => now()->subHours(2),
        'estimated_duration_days' => 1,
    ]);

    FlowExecution::query()->create([
        'workable_type' => 'tests.workable',
        'workable_id' => (string) Str::ulid(),
        'flow_config_step_id' => $configStepB->id,
        'flow_step_template_id' => $templateB->id,
        'status' => FlowStatus::Pending,
        'estimated_duration_days' => 1,
    ]);

    FlowMetric::query()->create([
        'workable_type' => $executionA->workable_type,
        'workable_id' => $executionA->workable_id,
        'flow_config_step_id' => $configStepA->id,
        'flow_step_template_id' => $templateA->id,
        'total_duration_minutes' => 120,
        'effective_work_minutes' => 100,
        'estimated_duration_minutes' => 1440,
        'deviation_minutes' => -1340,
        'is_on_time' => true,
        'is_rework' => false,
        'rework_count' => 0,
        'started_at' => now()->subHours(2),
        'completed_at' => now()->subHour(),
        'calculated_at' => now(),
    ]);

    FlowHistory::query()->create([
        'workable_type' => $executionA->workable_type,
        'workable_id' => $executionA->workable_id,
        'flow_config_step_id' => $configStepA->id,
        'action' => FlowAction::Start,
        'user_id' => $user->id,
        'performed_at' => now(),
    ]);

    $response = $this->get(route('dashboard.workflow-report', [
        'flow_slug' => $flowA->slug,
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('summary.total_executions', 1)
        ->assertJsonPath('summary.total_metrics', 1)
        ->assertJsonPath('summary.total_history_events', 1)
        ->assertJsonPath('filters.values.flow_slug', $flowA->slug)
        ->assertJsonPath('charts.status.type', 'doughnut')
        ->assertJsonPath('charts.status.data.labels.1', 'Em andamento')
        ->assertJsonPath('charts.status.data.datasets.0.data.1', 1)
        ->assertJsonPath('charts.sla.data.datasets.0.data.0', 1);
});
