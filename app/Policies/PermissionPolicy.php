<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class PermissionPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_PERMISSIONS_VIEW_ANY);
    }

    public function view(User $user, Permission $permission): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_PERMISSIONS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_PERMISSIONS_CREATE);
    }

    public function update(User $user, Permission $permission): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_PERMISSIONS_UPDATE);
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_PERMISSIONS_DELETE);
    }
}
