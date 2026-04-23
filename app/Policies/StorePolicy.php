<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class StorePolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_STORES_VIEW_ANY);
    }

    public function view(User $user, Store $store): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_STORES_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_STORES_CREATE);
    }

    public function update(User $user, Store $store): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_STORES_UPDATE);
    }

    public function delete(User $user, Store $store): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_STORES_DELETE);
    }
}
