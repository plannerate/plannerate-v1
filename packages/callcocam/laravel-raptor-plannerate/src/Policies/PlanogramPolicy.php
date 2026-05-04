<?php

namespace Callcocam\LaravelRaptorPlannerate\Policies;

use App\Models\User;
use App\Support\Authorization\PermissionName;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;

class PlanogramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::TENANT_PLANOGRAMS_VIEW_ANY);
    }

    public function view(User $user, Planogram $planogram): bool
    {
        return $user->can(PermissionName::TENANT_PLANOGRAMS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::TENANT_PLANOGRAMS_CREATE);
    }

    public function update(User $user, Planogram $planogram): bool
    {
        return $user->can(PermissionName::TENANT_PLANOGRAMS_UPDATE);
    }

    public function delete(User $user, Planogram $planogram): bool
    {
        return $user->can(PermissionName::TENANT_PLANOGRAMS_DELETE);
    }
}
