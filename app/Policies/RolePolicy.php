<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\PermissionName;

class RolePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_ROLES_VIEW_ANY);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Role $role): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_ROLES_VIEW);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_ROLES_CREATE);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Role $role): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_ROLES_UPDATE);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Role $role): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_ROLES_DELETE);
    }

    private function allowByContext(User $user, string $permission): bool
    {
        if (! config('permission.rbac_enabled', false)) {
            return true;
        }

        if ($this->isLandlordContext()) {
            return true;
        }

        return $user->can($permission);
    }

    private function isLandlordContext(): bool
    {
        $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

        return ! app()->bound($containerKey) || app($containerKey) === null;
    }
}
