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
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_EXECUTIONS_MOVE);
    }

    public function pause(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $this->canManageExecution($user)
            && $execution->status === WorkflowExecutionStatus::Active;
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
            && $this->isAtLastWorkflowStep($execution);
    }

    public function abandon(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $this->canManageExecution($user)
            && $execution->status === WorkflowExecutionStatus::Active;
    }

    public function manage(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_EXECUTIONS_MANAGE);
    }

    public function restore(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_EXECUTIONS_RESTORE);
    }

    private function canManageExecution(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_EXECUTIONS_MANAGE);
    }

    private function userCanExecuteCurrentStep(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $execution->step()
            ->first()
            ?->availableUsers()
            ->whereKey($user->id)
            ->exists() ?? false;
    }

    private function isAtLastWorkflowStep(WorkflowGondolaExecution $execution): bool
    {
        $currentStep = $execution->step()->first();

        if (! $currentStep instanceof WorkflowPlanogramStep) {
            return false;
        }

        $lastStep = $currentStep->planogram
            ?->workflowSteps()
            ->with('template')
            ->where('is_skipped', false)
            ->get()
            ->sortBy('suggested_order')
            ->last();

        return $lastStep instanceof WorkflowPlanogramStep
            && (string) $lastStep->id === (string) $currentStep->id;
    }
}
