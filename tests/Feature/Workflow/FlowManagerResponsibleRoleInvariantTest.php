<?php

use App\Models\User;
use Callcocam\LaravelRaptorFlow\Enums\FlowStatus;
use Callcocam\LaravelRaptorFlow\Events\FlowExecutionActionOccurred;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;
use Callcocam\LaravelRaptorFlow\Services\FlowManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function makeFlowStepTemplateForRoleInvariantTest(): FlowStepTemplate
{
    return FlowStepTemplate::query()->create([
        'name' => 'Etapa teste',
        'slug' => 'etapa-teste-'.Str::lower(Str::random(8)),
        'is_active' => true,
        'suggested_order' => 1,
    ]);
}

function makeFlowConfigStepForRoleInvariantTest(string $templateId, ?string $defaultRoleId): FlowConfigStep
{
    return FlowConfigStep::query()->create([
        'configurable_type' => 'tests.configurable',
        'configurable_id' => (string) Str::ulid(),
        'flow_step_template_id' => $templateId,
        'name' => 'Config etapa teste',
        'order' => 1,
        'default_role_id' => $defaultRoleId,
        'is_active' => true,
        'is_required' => true,
    ]);
}

function makeFlowExecutionForRoleInvariantTest(string $configStepId, string $templateId, FlowStatus $status): FlowExecution
{
    return FlowExecution::query()->create([
        'workable_type' => 'tests.workable',
        'workable_id' => (string) Str::ulid(),
        'flow_config_step_id' => $configStepId,
        'flow_step_template_id' => $templateId,
        'status' => $status,
        'estimated_duration_days' => 2,
    ]);
}

it('blocks start when the future responsible does not have the step default role', function () {
    $requiredRoleId = (string) Str::ulid();

    $starter = User::factory()->create();

    $roleMap = [
        (string) $starter->id => [],
    ];

    config(['flow.policy.check_role' => function ($user, $roleId) use ($roleMap): bool {
        return in_array((string) $roleId, $roleMap[(string) $user->getAuthIdentifier()] ?? [], true);
    }]);

    $template = makeFlowStepTemplateForRoleInvariantTest();
    $configStep = makeFlowConfigStepForRoleInvariantTest((string) $template->id, $requiredRoleId);
    $execution = makeFlowExecutionForRoleInvariantTest((string) $configStep->id, (string) $template->id, FlowStatus::Pending);

    $manager = new FlowManager;

    expect(fn () => $manager->startPendingExecution($execution, (string) $starter->id))
        ->toThrow(ValidationException::class, 'deve possuir a role padrão da etapa');

    $execution->refresh();

    expect($execution->status)->toBe(FlowStatus::Pending)
        ->and($execution->current_responsible_id)->toBeNull();
});

it('blocks assign when target user does not have the step default role', function () {
    $requiredRoleId = (string) Str::ulid();

    $assigner = User::factory()->create();
    $currentResponsible = User::factory()->create();
    $targetWithoutRole = User::factory()->create();

    $roleMap = [
        (string) $assigner->id => [$requiredRoleId],
        (string) $currentResponsible->id => [$requiredRoleId],
        (string) $targetWithoutRole->id => [],
    ];

    config(['flow.policy.check_role' => function ($user, $roleId) use ($roleMap): bool {
        return in_array((string) $roleId, $roleMap[(string) $user->getAuthIdentifier()] ?? [], true);
    }]);

    $template = makeFlowStepTemplateForRoleInvariantTest();
    $configStep = makeFlowConfigStepForRoleInvariantTest((string) $template->id, $requiredRoleId);

    $execution = makeFlowExecutionForRoleInvariantTest((string) $configStep->id, (string) $template->id, FlowStatus::InProgress);
    $execution->update([
        'current_responsible_id' => (string) $currentResponsible->id,
        'execution_started_by' => (string) $currentResponsible->id,
        'started_at' => now(),
    ]);

    $manager = new FlowManager;

    expect(fn () => $manager->assignExecution(
        $execution,
        (string) $assigner->id,
        (string) $targetWithoutRole->id,
        'Tentativa inválida de reatribuição',
    ))->toThrow(ValidationException::class, 'deve possuir a role padrão da etapa');

    $execution->refresh();

    expect($execution->current_responsible_id)->toBe((string) $currentResponsible->id)
        ->and($execution->status)->toBe(FlowStatus::InProgress);
});

it('blocks finish when the execution is not on the last workflow step', function () {
    Event::fake([FlowExecutionActionOccurred::class]);

    $responsible = User::factory()->create();

    $firstTemplate = FlowStepTemplate::query()->create([
        'name' => 'Primeira etapa',
        'slug' => 'primeira-etapa-'.Str::lower(Str::random(8)),
        'is_active' => true,
        'suggested_order' => 1,
    ]);

    $lastTemplate = FlowStepTemplate::query()->create([
        'name' => 'Última etapa',
        'slug' => 'ultima-etapa-'.Str::lower(Str::random(8)),
        'is_active' => true,
        'suggested_order' => 2,
    ]);

    $configurableId = (string) Str::ulid();

    $firstStep = FlowConfigStep::query()->create([
        'configurable_type' => 'tests.configurable',
        'configurable_id' => $configurableId,
        'flow_step_template_id' => (string) $firstTemplate->id,
        'name' => 'Etapa 1',
        'order' => 1,
        'is_active' => true,
        'is_required' => true,
    ]);

    FlowConfigStep::query()->create([
        'configurable_type' => 'tests.configurable',
        'configurable_id' => $configurableId,
        'flow_step_template_id' => (string) $lastTemplate->id,
        'name' => 'Etapa 2',
        'order' => 2,
        'is_active' => true,
        'is_required' => true,
    ]);

    $execution = makeFlowExecutionForRoleInvariantTest((string) $firstStep->id, (string) $firstTemplate->id, FlowStatus::InProgress);
    $execution->update([
        'current_responsible_id' => (string) $responsible->id,
        'execution_started_by' => (string) $responsible->id,
        'started_at' => now(),
    ]);

    $manager = new FlowManager;

    expect(fn () => $manager->finishExecution($execution, (string) $responsible->id))
        ->toThrow(ValidationException::class, 'última etapa do workflow');

    $execution->refresh();

    expect($execution->status)->toBe(FlowStatus::InProgress)
        ->and($execution->completed_at)->toBeNull();

    Event::assertNotDispatched(FlowExecutionActionOccurred::class);
});

it('dispatches a domain event when finishing the last workflow step', function () {
    Event::fake([FlowExecutionActionOccurred::class]);

    $responsible = User::factory()->create();

    $template = FlowStepTemplate::query()->create([
        'name' => 'Etapa final única',
        'slug' => 'etapa-final-unica-'.Str::lower(Str::random(8)),
        'is_active' => true,
        'suggested_order' => 1,
    ]);

    $step = FlowConfigStep::query()->create([
        'configurable_type' => 'tests.configurable',
        'configurable_id' => (string) Str::ulid(),
        'flow_step_template_id' => (string) $template->id,
        'name' => 'Etapa final',
        'order' => 1,
        'is_active' => true,
        'is_required' => true,
    ]);

    $execution = makeFlowExecutionForRoleInvariantTest((string) $step->id, (string) $template->id, FlowStatus::InProgress);
    $execution->update([
        'current_responsible_id' => (string) $responsible->id,
        'execution_started_by' => (string) $responsible->id,
        'started_at' => now(),
    ]);

    $manager = new FlowManager;
    $manager->finishExecution($execution, (string) $responsible->id);

    Event::assertDispatched(FlowExecutionActionOccurred::class, function (FlowExecutionActionOccurred $event) use ($execution, $responsible) {
        return (string) $event->execution->id === (string) $execution->id
            && $event->action === 'complete'
            && $event->actorId === (string) $responsible->id;
    });
});
