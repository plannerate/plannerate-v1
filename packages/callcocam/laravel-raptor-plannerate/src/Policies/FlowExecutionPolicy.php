<?php

namespace Callcocam\LaravelRaptorPlannerate\Policies;

use Callcocam\LaravelRaptorFlow\Contracts\FlowExecutionPolicyContract;
use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorFlow\Policies\FlowExecutionPolicy as PackageFlowExecutionPolicy;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy de flow para casos específicos do app (ex.: regras extras de negócio).
 * Por padrão a policy do pacote já faz a validação context-aware (role, suggested_responsible, participants).
 * Esta policy delega ao pacote; sobrescreva os métodos aqui apenas quando precisar de lógica adicional.
 */
class FlowExecutionPolicy implements FlowExecutionPolicyContract
{
    protected PackageFlowExecutionPolicy $packagePolicy;

    public function __construct(PackageFlowExecutionPolicy $packagePolicy)
    {
        $this->packagePolicy = $packagePolicy;
    }

    public function start(Authenticatable $user, FlowExecution $execution): bool
    {
        return $this->packagePolicy->start($user, $execution);
    }

    public function move(Authenticatable $user, FlowExecution $execution): bool
    {
        return $this->packagePolicy->move($user, $execution);
    }

    public function pause(Authenticatable $user, FlowExecution $execution): bool
    {
        return $this->packagePolicy->pause($user, $execution);
    }

    public function resume(Authenticatable $user, FlowExecution $execution): bool
    {
        return $this->packagePolicy->resume($user, $execution);
    }

    public function assign(Authenticatable $user, FlowExecution $execution): bool
    {
        return $this->packagePolicy->assign($user, $execution);
    }

    public function abandon(Authenticatable $user, FlowExecution $execution): bool
    {
        return $this->packagePolicy->abandon($user, $execution);
    }

    public function notes(Authenticatable $user, FlowExecution $execution): bool
    {
        return $this->packagePolicy->notes($user, $execution);
    }

    public function finish(Authenticatable $user, FlowExecution $execution): bool
    {
        return $this->packagePolicy->finish($user, $execution);
    }

    /**
     * Retorna as abilities por execução para o frontend (ex.: payload do Kanban).
     *
     * @return array{can_start: bool, can_move: bool, can_pause: bool, can_resume: bool, can_assign: bool, can_abandon: bool, can_notes: bool, can_finish: bool}
     */
    public static function abilities(Authenticatable $user, FlowExecution $execution): array
    {
        return PackageFlowExecutionPolicy::abilities($user, $execution);
    }

    public static function passesRoleGate(Authenticatable $user, FlowExecution $execution): bool
    {
        $execution->loadMissing('configStep');
        $step = $execution->configStep;

        if (! $step || ! $step->default_role_id) {
            return true;
        }

        $checkRole = config('flow.policy.check_role');

        if (! is_callable($checkRole)) {
            return false;
        }

        return (bool) $checkRole($user, $step->default_role_id);
    }
}
