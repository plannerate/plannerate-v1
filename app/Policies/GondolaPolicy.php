<?php

namespace App\Policies;

use App\Models\Gondola;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class GondolaPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_VIEW_ANY);
    }

    public function view(User $user, Gondola $gondola): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_CREATE);
    }

    public function update(User $user, Gondola $gondola): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_UPDATE);
    }

    public function delete(User $user, Gondola $gondola): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_DELETE);
    }
}
