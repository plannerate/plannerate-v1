<?php

namespace App\Policies;

use App\Models\Provider;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class ProviderPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PROVIDERS_VIEW_ANY);
    }

    public function view(User $user, Provider $provider): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PROVIDERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PROVIDERS_CREATE);
    }

    public function update(User $user, Provider $provider): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PROVIDERS_UPDATE);
    }

    public function delete(User $user, Provider $provider): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PROVIDERS_DELETE);
    }
}
