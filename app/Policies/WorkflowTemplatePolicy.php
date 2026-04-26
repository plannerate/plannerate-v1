<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class WorkflowTemplatePolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_TEMPLATES_VIEW_ANY);
    }

    public function view(User $user, WorkflowTemplate $template): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_TEMPLATES_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_TEMPLATES_CREATE);
    }

    public function update(User $user, WorkflowTemplate $template): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_TEMPLATES_UPDATE);
    }

    public function delete(User $user, WorkflowTemplate $template): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_KANBAN_TEMPLATES_DELETE);
    }
}
