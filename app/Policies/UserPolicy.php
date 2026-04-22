<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class UserPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_USERS_VIEW_ANY);
    }

    public function view(User $user, User $model): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_USERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_USERS_CREATE);
    }

    public function update(User $user, User $model): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_USERS_UPDATE);
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return false;
        }

        return $this->allowByContext($user, PermissionName::LANDLORD_USERS_DELETE);
    }
}
