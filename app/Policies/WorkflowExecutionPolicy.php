<?php

namespace App\Policies;

use App\Enums\WorkflowExecutionStatus;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowPlanogramStep;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class WorkflowExecutionPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_VIEW_ANY);
    }

    public function start(User $user, ?WorkflowGondolaExecution $execution = null): bool
    {
        if (! $this->allowByContext($user, PermissionName::TENANT_KANBAN_EXECUTIONS_START)) {
            return false;
        }

        if (! $execution instanceof WorkflowGondolaExecution) {
            return true;
        }

        return $execution->status === WorkflowExecutionStatus::Pending
            && $execution->started_at === null
            && $execution->execution_started_by === null
            && $this->userCanExecuteCurrentStep($user, $execution);
    }

    public function move(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_EXECUTIONS_MOVE)
            && $execution->status === WorkflowExecutionStatus::Active;
    }

    public function pause(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $this->canManageExecution($user)
            && $execution->status === WorkflowExecutionStatus::Active
            && $this->wasStartedByUser($user, $execution);
    }

    public function resume(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $this->canManageExecution($user)
            && $execution->status === WorkflowExecutionStatus::Paused;
    }

    public function complete(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $this->canManageExecution($user)
            && $execution->status === WorkflowExecutionStatus::Active
            && $this->isAtFinalFlowStep($execution);
    }

    public function abandon(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $this->canManageExecution($user)
            && $execution->status === WorkflowExecutionStatus::Active
            && $this->wasStartedByUser($user, $execution);
    }

    public function requestAbandonment(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $this->canManageExecution($user)
            && $execution->status === WorkflowExecutionStatus::Active
            && $execution->execution_started_by !== null
            && ! $this->wasStartedByUser($user, $execution);
    }

    public function manage(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_EXECUTIONS_MANAGE);
    }

    /**
     * Decide se o usuário pode operar a camada de Execução em Loja desta
     * execução (registrar evidências/divergências e concluir).
     *
     * Gate barato e reutilizado pela tela de print: exige que a execução esteja
     * na etapa final de fluxo (Execução em Loja), em estado operável
     * (pending/active/paused) e que o usuário seja o responsável atual, um
     * usuário permitido da etapa (para o início automático) ou um gestor.
     */
    public function execute(User $user, WorkflowGondolaExecution $execution): bool
    {
        if (! $this->isAtFinalFlowStep($execution)) {
            return false;
        }

        if (! in_array($execution->status, [
            WorkflowExecutionStatus::Pending,
            WorkflowExecutionStatus::Active,
            WorkflowExecutionStatus::Paused,
        ], true)) {
            return false;
        }

        if ((string) $execution->current_responsible_id === (string) $user->id) {
            return true;
        }

        if ($this->userCanExecuteCurrentStep($user, $execution)) {
            return true;
        }

        return $this->canManageExecution($user);
    }

    public function restore(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_EXECUTIONS_RESTORE);
    }

    private function canManageExecution(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_EXECUTIONS_MANAGE);
    }

    private function wasStartedByUser(User $user, WorkflowGondolaExecution $execution): bool
    {
        return (string) $execution->execution_started_by === (string) $user->id;
    }

    private function userCanExecuteCurrentStep(User $user, WorkflowGondolaExecution $execution): bool
    {
        $step = $this->resolveCurrentStep($execution);

        if (! $step instanceof WorkflowPlanogramStep) {
            return false;
        }

        // Reaproveita a relação já carregada (board) para evitar N+1; cai para
        // consulta direta quando a etapa não foi pré-carregada.
        if ($step->relationLoaded('availableUsers')) {
            return $step->availableUsers->contains(
                fn (User $available): bool => (string) $available->id === (string) $user->id
            );
        }

        return $step->availableUsers()->whereKey($user->id)->exists();
    }

    /**
     * Indica se a execução está na última etapa de fluxo (`stage_type = flow`)
     * não pulada do planograma — ponto onde o fluxo pode ser concluído.
     *
     * A etapa de Revisão Periódica deixa de ser concluível manualmente: ela é
     * pós-conclusão e disparada automaticamente. Se o planograma não tiver
     * nenhuma etapa de fluxo (config atípica), cai para a última não pulada.
     */
    private function isAtFinalFlowStep(WorkflowGondolaExecution $execution): bool
    {
        $currentStep = $this->resolveCurrentStep($execution);

        if (! $currentStep instanceof WorkflowPlanogramStep) {
            return false;
        }

        $planogram = $currentStep->relationLoaded('planogram')
            ? $currentStep->planogram
            : $currentStep->planogram()->first();

        // Reaproveita as etapas já carregadas (board) quando disponíveis para
        // evitar N+1; senão consulta uma vez com o template (ordem/stage_type).
        $steps = $planogram?->relationLoaded('workflowSteps')
            ? $planogram->workflowSteps
            : $planogram?->workflowSteps()->with('template')->get();

        $activeSteps = $steps?->where('is_skipped', false) ?? collect();

        // Considera apenas etapas de fluxo; se não houver nenhuma (fallback),
        // usa todas as não puladas para não travar a conclusão.
        $flowSteps = $activeSteps->filter(
            fn (WorkflowPlanogramStep $step): bool => ! $step->stage_type->isPeriodicReview()
        );

        $candidateSteps = $flowSteps->isNotEmpty() ? $flowSteps : $activeSteps;

        $lastStep = $candidateSteps->sortBy('suggested_order')->last();

        return $lastStep instanceof WorkflowPlanogramStep
            && (string) $lastStep->id === (string) $currentStep->id;
    }

    /**
     * Resolve a etapa atual da execução reaproveitando a relação carregada
     * (board) quando possível, evitando consultas repetidas por execução.
     */
    private function resolveCurrentStep(WorkflowGondolaExecution $execution): ?WorkflowPlanogramStep
    {
        return $execution->relationLoaded('step')
            ? $execution->step
            : $execution->step()->first();
    }
}
