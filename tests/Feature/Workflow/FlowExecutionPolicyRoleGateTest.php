<?php

use App\Policies\FlowExecutionPolicy as AppFlowExecutionPolicy;
use Callcocam\LaravelRaptorFlow\Enums\FlowStatus;
use Callcocam\LaravelRaptorFlow\Models\FlowConfigStep;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Models\FlowStepTemplate;
use Callcocam\LaravelRaptorFlow\Policies\FlowExecutionPolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

/**
 * Cria um mock de usuário autenticável com ID e roles controláveis.
 *
 * @param  string[]  $roleIds  IDs de roles que o usuário possui.
 */
function makeUserWithRoles(string $userId, array $roleIds = []): Authenticatable
{
    return new class($userId, $roleIds) implements Authenticatable
    {
        public function __construct(private string $id, private array $roleIds) {}

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): string
        {
            return $this->id;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }

        public function can($ability, $arguments = []): bool
        {
            return false;
        }

        /** Expõe collection de roles fake para o check_role callback. */
        public function getRoleIds(): array
        {
            return $this->roleIds;
        }
    };
}

/**
 * Cria um FlowExecution com configStep injetado (sem tocar no banco).
 */
function makeExecutionWithConfigStep(FlowStatus $status, ?string $roleId, ?string $responsibleId = null): FlowExecution
{
    $configStep = new FlowConfigStep;
    $configStep->id = 'config-step-1';
    $configStep->default_role_id = $roleId;
    $configStep->suggested_responsible_id = $responsibleId;
    $configStep->setRelation('participants', collect());

    $execution = new FlowExecution;
    $execution->id = 'exec-1';
    $execution->status = $status;
    $execution->current_responsible_id = $responsibleId;
    $execution->setRelation('configStep', $configStep);

    return $execution;
}

beforeEach(function () {
    // Registra check_role via ID direto (igual ao config/flow.php da app, mas usando array de ids do mock).
    config(['flow.policy.check_role' => function ($user, $roleId) {
        if (! $user || ! method_exists($user, 'getRoleIds')) {
            return true;
        }

        return in_array($roleId, $user->getRoleIds(), true);
    }]);
});

it('nega start quando o usuário não possui a role obrigatória da etapa', function () {
    $user = makeUserWithRoles('user-1', []);
    $execution = makeExecutionWithConfigStep(FlowStatus::Pending, 'role-required');

    $policy = new FlowExecutionPolicy;

    expect($policy->start($user, $execution))->toBeFalse();
});

it('permite start quando o usuário possui a role obrigatória e é participante', function () {
    $user = makeUserWithRoles('user-1', ['role-required']);

    $configStep = new FlowConfigStep;
    $configStep->id = 'config-step-1';
    $configStep->default_role_id = 'role-required';
    $configStep->suggested_responsible_id = null;

    $participant = (object) ['user_id' => 'user-1'];
    $configStep->setRelation('participants', collect([$participant]));

    $execution = new FlowExecution;
    $execution->id = 'exec-1';
    $execution->status = FlowStatus::Pending;
    $execution->setRelation('configStep', $configStep);

    $policy = new FlowExecutionPolicy;

    expect($policy->start($user, $execution))->toBeTrue();
});

it('permite start sem role configurada para any participante', function () {
    $user = makeUserWithRoles('user-1', []);

    $configStep = new FlowConfigStep;
    $configStep->id = 'config-step-1';
    $configStep->default_role_id = null;
    $configStep->suggested_responsible_id = null;

    $participant = (object) ['user_id' => 'user-1'];
    $configStep->setRelation('participants', collect([$participant]));

    $execution = new FlowExecution;
    $execution->id = 'exec-1';
    $execution->status = FlowStatus::Pending;
    $execution->setRelation('configStep', $configStep);

    $policy = new FlowExecutionPolicy;

    expect($policy->start($user, $execution))->toBeTrue();
});

it('nega move/pause/resume quando o usuário não possui a role obrigatória mesmo sendo responsável', function (string $method) {
    $user = makeUserWithRoles('user-1', []);
    $execution = makeExecutionWithConfigStep(FlowStatus::InProgress, 'role-required', 'user-1');

    $policy = new FlowExecutionPolicy;

    expect($policy->{$method}($user, $execution))->toBeFalse();
})->with(['move', 'pause', 'resume', 'assign', 'abandon', 'notes']);

it('nega ações operacionais quando o usuário não é participante da etapa mesmo sendo responsável', function (string $method) {
    $user = makeUserWithRoles('user-1', ['role-required']);

    $configStep = new FlowConfigStep;
    $configStep->id = 'config-step-1';
    $configStep->default_role_id = 'role-required';
    $configStep->suggested_responsible_id = null;
    $configStep->setRelation('participants', collect([(object) ['user_id' => 'other-user']]));

    $execution = new FlowExecution;
    $execution->id = 'exec-1';
    $execution->status = FlowStatus::InProgress;
    $execution->current_responsible_id = 'user-1';
    $execution->setRelation('configStep', $configStep);

    $policy = new FlowExecutionPolicy;

    expect($policy->{$method}($user, $execution))->toBeFalse();
})->with(['move', 'pause', 'resume', 'assign', 'abandon', 'notes']);

it('nega ações mesmo para usuário com permissão administrativa quando role obrigatória não bate', function () {
    $userWithAdmin = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): string
        {
            return 'admin-1';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }

        /** Usuário admin mas sem roles de domínio. */
        public function can($ability, $arguments = []): bool
        {
            return true;
        }

        public function getRoleIds(): array
        {
            return [];
        }
    };

    $execution = makeExecutionWithConfigStep(FlowStatus::Pending, 'role-required');

    $policy = new FlowExecutionPolicy;

    expect($policy->start($userWithAdmin, $execution))->toBeFalse()
        ->and($policy->move($userWithAdmin, $execution))->toBeFalse()
        ->and($policy->pause($userWithAdmin, $execution))->toBeFalse();
});

it('nega ações quando role gate não está configurado (callback ausente)', function () {
    config(['flow.policy.check_role' => null]);

    $configStep = new FlowConfigStep;
    $configStep->id = 'config-step-1';
    $configStep->default_role_id = 'alguma-role';
    $configStep->suggested_responsible_id = 'user-1';
    $configStep->setRelation('participants', collect());

    $execution = new FlowExecution;
    $execution->id = 'exec-1';
    $execution->status = FlowStatus::Pending;
    $execution->setRelation('configStep', $configStep);

    $user = makeUserWithRoles('user-1', []);

    $policy = new FlowExecutionPolicy;

    // Sem callback configurado, role gate falha em modo fail-closed quando há role obrigatória.
    expect($policy->start($user, $execution))->toBeFalse();
});

it('reports role gate failure through the app helper when the user lacks the default role', function () {
    $user = makeUserWithRoles('user-1', []);
    $execution = makeExecutionWithConfigStep(FlowStatus::Pending, 'role-required');

    expect(AppFlowExecutionPolicy::passesRoleGate($user, $execution))->toBeFalse();
});

it('reports role gate success through the app helper when the user has the default role', function () {
    $user = makeUserWithRoles('user-1', ['role-required']);
    $execution = makeExecutionWithConfigStep(FlowStatus::Pending, 'role-required');

    expect(AppFlowExecutionPolicy::passesRoleGate($user, $execution))->toBeTrue();
});

it('permite pause somente quando a execução está in_progress', function () {
    $user = makeUserWithRoles('user-1', []);
    $policy = new FlowExecutionPolicy;

    $inProgressExecution = makeExecutionWithConfigStep(FlowStatus::InProgress, null, 'user-1');
    $pausedExecution = makeExecutionWithConfigStep(FlowStatus::Paused, null, 'user-1');

    expect($policy->pause($user, $inProgressExecution))->toBeTrue()
        ->and($policy->pause($user, $pausedExecution))->toBeFalse();
});

it('permite resume somente quando a execução está paused', function () {
    $user = makeUserWithRoles('user-1', []);
    $policy = new FlowExecutionPolicy;

    $inProgressExecution = makeExecutionWithConfigStep(FlowStatus::InProgress, null, 'user-1');
    $pausedExecution = makeExecutionWithConfigStep(FlowStatus::Paused, null, 'user-1');

    expect($policy->resume($user, $pausedExecution))->toBeTrue()
        ->and($policy->resume($user, $inProgressExecution))->toBeFalse();
});

it('permite abandon somente quando a execução está in_progress', function () {
    $user = makeUserWithRoles('user-1', []);
    $policy = new FlowExecutionPolicy;

    $inProgressExecution = makeExecutionWithConfigStep(FlowStatus::InProgress, null, 'user-1');
    $pausedExecution = makeExecutionWithConfigStep(FlowStatus::Paused, null, 'user-1');

    expect($policy->abandon($user, $inProgressExecution))->toBeTrue()
        ->and($policy->abandon($user, $pausedExecution))->toBeFalse();
});

it('permite finish somente na última etapa do workflow', function () {
    $configurableId = (string) Str::ulid();
    $currentTemplate = FlowStepTemplate::query()->create([
        'name' => 'Template atual',
        'slug' => 'template-atual-'.Str::lower(Str::random(8)),
        'is_active' => true,
        'suggested_order' => 1,
    ]);

    $lastTemplate = FlowStepTemplate::query()->create([
        'name' => 'Template final',
        'slug' => 'template-final-'.Str::lower(Str::random(8)),
        'is_active' => true,
        'suggested_order' => 2,
    ]);

    $currentStep = FlowConfigStep::query()->create([
        'configurable_type' => 'tests.configurable',
        'configurable_id' => $configurableId,
        'flow_step_template_id' => (string) $currentTemplate->id,
        'name' => 'Etapa atual',
        'order' => 1,
        'suggested_responsible_id' => 'user-1',
        'is_active' => true,
        'is_required' => true,
    ]);

    $lastStep = FlowConfigStep::query()->create([
        'configurable_type' => 'tests.configurable',
        'configurable_id' => $configurableId,
        'flow_step_template_id' => (string) $lastTemplate->id,
        'name' => 'Etapa final',
        'order' => 2,
        'suggested_responsible_id' => 'user-1',
        'is_active' => true,
        'is_required' => true,
    ]);

    $user = makeUserWithRoles('user-1', []);
    $policy = new FlowExecutionPolicy;

    $executionBeforeLast = new FlowExecution;
    $executionBeforeLast->status = FlowStatus::InProgress;
    $executionBeforeLast->current_responsible_id = 'user-1';
    $executionBeforeLast->setRelation('configStep', $currentStep);

    $executionAtLast = new FlowExecution;
    $executionAtLast->status = FlowStatus::InProgress;
    $executionAtLast->current_responsible_id = 'user-1';
    $executionAtLast->setRelation('configStep', $lastStep);

    expect($policy->finish($user, $executionBeforeLast))->toBeFalse()
        ->and($policy->finish($user, $executionAtLast))->toBeTrue();
});
