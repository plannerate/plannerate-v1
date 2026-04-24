<?php

namespace App\Policies;

use App\Models\Planogram;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class PlanogramPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PLANOGRAMS_VIEW_ANY);
    }

    public function view(User $user, Planogram $planogram): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PLANOGRAMS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PLANOGRAMS_CREATE);
    }

    public function update(User $user, Planogram $planogram): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PLANOGRAMS_UPDATE);
    }

    public function delete(User $user, Planogram $planogram): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PLANOGRAMS_DELETE);
    }
}
