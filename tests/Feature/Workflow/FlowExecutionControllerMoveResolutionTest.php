<?php

use Callcocam\LaravelRaptorFlow\Enums\FlowStatus;
use Callcocam\LaravelRaptorFlow\Http\Controllers\FlowExecutionController;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;
use Callcocam\LaravelRaptorFlow\Services\FlowManager;
use Illuminate\Support\Str;

it('resolves destination step by flow config step id', function () {
    $flowManager = app(FlowManager::class);

    $controller = new class($flowManager) extends FlowExecutionController
    {
        public function resolveForTest(FlowExecution $execution, string $toStepId): ?FlowConfigStep
        {
            return $this->resolveDestinationStep($execution, $toStepId);
        }
    };

    $templateFrom = FlowStepTemplate::create([
        'name' => 'From Config ID',
        'slug' => Str::slug('From Config ID '.Str::ulid()),
        'suggested_order' => 1,
        'estimated_duration_days' => 1,
        'is_required_by_default' => true,
        'is_active' => true,
    ]);

    $templateTo = FlowStepTemplate::create([
        'name' => 'To Config ID',
        'slug' => Str::slug('To Config ID '.Str::ulid()),
        'suggested_order' => 2,
        'estimated_duration_days' => 1,
        'is_required_by_default' => true,
        'is_active' => true,
    ]);

    $configurableType = 'tests.configurable';
    $configurableId = (string) Str::ulid();

    $fromStep = FlowConfigStep::create([
        'configurable_type' => $configurableType,
        'configurable_id' => $configurableId,
        'flow_step_template_id' => $templateFrom->id,
        'name' => 'From',
        'order' => 1,
        'is_active' => true,
        'is_required' => true,
    ]);

    $toStep = FlowConfigStep::create([
        'configurable_type' => $configurableType,
        'configurable_id' => $configurableId,
        'flow_step_template_id' => $templateTo->id,
        'name' => 'To',
        'order' => 2,
        'is_active' => true,
        'is_required' => true,
    ]);

    $execution = FlowExecution::create([
        'workable_type' => 'tests.workable',
        'workable_id' => (string) Str::ulid(),
        'flow_config_step_id' => $fromStep->id,
        'flow_step_template_id' => $templateFrom->id,
        'status' => FlowStatus::InProgress,
        'current_responsible_id' => (string) Str::ulid(),
        'estimated_duration_days' => 1,
    ]);

    $resolved = $controller->resolveForTest($execution, (string) $toStep->id);

    expect($resolved?->id)->toBe((string) $toStep->id);
});

it('resolves destination step by flow step template id within same configurable', function () {
    $flowManager = app(FlowManager::class);

    $controller = new class($flowManager) extends FlowExecutionController
    {
        public function resolveForTest(FlowExecution $execution, string $toStepId): ?FlowConfigStep
        {
            return $this->resolveDestinationStep($execution, $toStepId);
        }
    };

    $templateFrom = FlowStepTemplate::create([
        'name' => 'From Template',
        'slug' => Str::slug('From Template '.Str::ulid()),
        'suggested_order' => 1,
        'estimated_duration_days' => 1,
        'is_required_by_default' => true,
        'is_active' => true,
    ]);

    $templateTo = FlowStepTemplate::create([
        'name' => 'To Template',
        'slug' => Str::slug('To Template '.Str::ulid()),
        'suggested_order' => 2,
        'estimated_duration_days' => 1,
        'is_required_by_default' => true,
        'is_active' => true,
    ]);

    $configurableType = 'tests.configurable';
    $configurableId = (string) Str::ulid();

    $fromStep = FlowConfigStep::create([
        'configurable_type' => $configurableType,
        'configurable_id' => $configurableId,
        'flow_step_template_id' => $templateFrom->id,
        'name' => 'From',
        'order' => 1,
        'is_active' => true,
        'is_required' => true,
    ]);

    $toStep = FlowConfigStep::create([
        'configurable_type' => $configurableType,
        'configurable_id' => $configurableId,
        'flow_step_template_id' => $templateTo->id,
        'name' => 'To',
        'order' => 2,
        'is_active' => true,
        'is_required' => true,
    ]);

    FlowConfigStep::create([
        'configurable_type' => 'tests.other',
        'configurable_id' => (string) Str::ulid(),
        'flow_step_template_id' => $templateTo->id,
        'name' => 'Other Configurable',
        'order' => 2,
        'is_active' => true,
        'is_required' => true,
    ]);

    $execution = FlowExecution::create([
        'workable_type' => 'tests.workable',
        'workable_id' => (string) Str::ulid(),
        'flow_config_step_id' => $fromStep->id,
        'flow_step_template_id' => $templateFrom->id,
        'status' => FlowStatus::InProgress,
        'current_responsible_id' => (string) Str::ulid(),
        'estimated_duration_days' => 1,
    ]);

    $resolved = $controller->resolveForTest($execution, (string) $templateTo->id);

    expect($resolved?->id)->toBe((string) $toStep->id);
});
