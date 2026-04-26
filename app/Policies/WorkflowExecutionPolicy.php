<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class WorkflowExecutionPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_VIEW_ANY);
    }

    public function start(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_EXECUTIONS_START);
    }

    public function move(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_EXECUTIONS_MOVE);
    }

    public function manage(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_EXECUTIONS_MANAGE);
    }

    public function restore(User $user, WorkflowGondolaExecution $execution): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_EXECUTIONS_RESTORE);
    }
}
