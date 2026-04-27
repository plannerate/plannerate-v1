<?php

namespace App\Policies;

use App\Models\Module;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class ModulePolicy
{
    use ChecksRbacPermission;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_MODULES_VIEW_ANY);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Module $module): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_MODULES_VIEW);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_MODULES_CREATE);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Module $module): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_MODULES_UPDATE);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Module $module): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_MODULES_DELETE);
    }
}
