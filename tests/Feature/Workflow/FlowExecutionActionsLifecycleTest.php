<?php

use App\Models\User;
use Callcocam\LaravelRaptorFlow\Enums\FlowAction;
use Callcocam\LaravelRaptorFlow\Enums\FlowNotificationType;
use Callcocam\LaravelRaptorFlow\Enums\FlowStatus;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Models\FlowHistory;
use Callcocam\LaravelRaptorFlow\Models\FlowMetric;
use Callcocam\LaravelRaptorFlow\Models\FlowNotification;
use Callcocam\LaravelRaptorFlow\Models\FlowParticipant;
use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;
use Illuminate\Support\Str;

function createTemplateForLifecycleTest(int $order): FlowStepTemplate
{
    return FlowStepTemplate::query()->create([
        'name' => 'Etapa '.$order,
        'slug' => 'etapa-lifecycle-'.$order.'-'.Str::lower(Str::random(8)),
        'is_active' => true,
        'suggested_order' => $order,
        'estimated_duration_days' => 1,
    ]);
}

function createConfigStepForLifecycleTest(string $templateId, int $order, string $configurableId, string $userId): FlowConfigStep
{
    return FlowConfigStep::query()->create([
        'configurable_type' => 'tests.configurable',
        'configurable_id' => $configurableId,
        'flow_step_template_id' => $templateId,
        'name' => 'Config etapa '.$order,
        'order' => $order,
        'suggested_responsible_id' => $userId,
        'estimated_duration_days' => 1,
        'is_active' => true,
        'is_required' => true,
    ]);
}

function addParticipantForLifecycleTest(string $stepId, string $userId): void
{
    FlowParticipant::query()->create([
        'user_id' => $userId,
        'participable_type' => FlowConfigStep::class,
        'participable_id' => $stepId,
        'is_pre_assigned' => true,
        'assigned_at' => now(),
    ]);
}

it('executes start move pause resume notes and abandon with history metric and notifications', function () {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    $user = User::factory()->create();

    $configurableId = (string) Str::ulid();

    $templateOne = createTemplateForLifecycleTest(1);
    $templateTwo = createTemplateForLifecycleTest(2);

    $stepOne = createConfigStepForLifecycleTest((string) $templateOne->id, 1, $configurableId, (string) $user->id);
    $stepTwo = createConfigStepForLifecycleTest((string) $templateTwo->id, 2, $configurableId, (string) $user->id);

    addParticipantForLifecycleTest((string) $stepOne->id, (string) $user->id);
    addParticipantForLifecycleTest((string) $stepTwo->id, (string) $user->id);

    $execution = FlowExecution::query()->create([
        'workable_type' => 'tests.workable',
        'workable_id' => (string) Str::ulid(),
        'flow_config_step_id' => $stepOne->id,
        'flow_step_template_id' => $templateOne->id,
        'status' => FlowStatus::Pending,
        'estimated_duration_days' => 1,
    ]);

    $this->actingAs($user);

    $this->post(route('flow.execution.start', ['execution' => $execution->id]))
        ->assertRedirect();

    $execution->refresh();

    expect($execution->status)->toBe(FlowStatus::InProgress)
        ->and((string) $execution->current_responsible_id)->toBe((string) $user->id);

    $this->post(route('flow.execution.move', ['execution' => $execution->id]), [
        'to_step_id' => (string) $stepTwo->id,
        'notes' => 'Movendo para etapa 2',
    ])->assertRedirect();

    $execution->refresh();

    expect($execution->status)->toBe(FlowStatus::Pending)
        ->and((string) $execution->flow_config_step_id)->toBe((string) $stepTwo->id)
        ->and($execution->current_responsible_id)->toBeNull();

    $this->post(route('flow.execution.start', ['execution' => $execution->id]))
        ->assertRedirect();

    $execution->refresh();

    expect($execution->status)->toBe(FlowStatus::InProgress)
        ->and((string) $execution->current_responsible_id)->toBe((string) $user->id);

    $this->post(route('flow.execution.pause', ['execution' => $execution->id]))
        ->assertRedirect();

    $execution->refresh();

    expect($execution->status)->toBe(FlowStatus::Paused)
        ->and($execution->paused_at)->not->toBeNull();

    $this->post(route('flow.execution.resume', ['execution' => $execution->id]))
        ->assertRedirect();

    $execution->refresh();

    expect($execution->status)->toBe(FlowStatus::InProgress)
        ->and($execution->paused_at)->toBeNull();

    $this->post(route('flow.execution.notes', ['execution' => $execution->id]), [
        'notes' => 'Notas atualizadas no ciclo de execução',
    ])->assertRedirect();

    $execution->refresh();

    expect($execution->notes)->toBe('Notas atualizadas no ciclo de execução');

    $this->post(route('flow.execution.abandon', ['execution' => $execution->id]))
        ->assertRedirect();

    $execution->refresh();

    expect($execution->status)->toBe(FlowStatus::Pending)
        ->and($execution->current_responsible_id)->toBeNull();

    $historyActions = FlowHistory::query()
        ->where('workable_type', $execution->workable_type)
        ->where('workable_id', $execution->workable_id)
        ->pluck('action')
        ->map(fn ($action) => $action instanceof FlowAction ? $action->value : (string) $action)
        ->all();

    expect($historyActions)->toContain(
        FlowAction::Start->value,
        FlowAction::Move->value,
        FlowAction::Pause->value,
        FlowAction::Resume->value,
        FlowAction::Abandon->value,
    );

    $metric = FlowMetric::query()
        ->where('workable_type', $execution->workable_type)
        ->where('workable_id', $execution->workable_id)
        ->latest('created_at')
        ->first();

    expect($metric)->not->toBeNull()
        ->and((string) $metric->flow_config_step_id)->toBe((string) $stepOne->id)
        ->and((string) $metric->flow_step_template_id)->toBe((string) $templateOne->id);

    $notificationTypes = FlowNotification::query()
        ->where('user_id', $user->id)
        ->where('notifiable_type', $execution->workable_type)
        ->where('notifiable_id', $execution->workable_id)
        ->pluck('type')
        ->map(fn ($type) => $type instanceof FlowNotificationType ? $type->value : (string) $type)
        ->all();

    expect($notificationTypes)->toContain(
        FlowNotificationType::Assigned->value,
        FlowNotificationType::Moved->value,
    );
});
