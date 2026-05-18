<?php

namespace App\Policies;

use App\Models\PlanogramTemplate;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class PlanogramTemplatePolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PLANOGRAMS_VIEW_ANY);
    }

    public function view(User $user, PlanogramTemplate $planogramTemplate): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PLANOGRAMS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PLANOGRAMS_CREATE);
    }

    public function delete(User $user, PlanogramTemplate $planogramTemplate): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PLANOGRAMS_DELETE);
    }
}
