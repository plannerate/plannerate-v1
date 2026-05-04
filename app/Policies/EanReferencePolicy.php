<?php

namespace App\Policies;

use App\Models\EanReference;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class EanReferencePolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_EAN_REFERENCES_VIEW_ANY);
    }

    public function view(User $user, EanReference $eanReference): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_EAN_REFERENCES_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_EAN_REFERENCES_CREATE);
    }

    public function update(User $user, EanReference $eanReference): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_EAN_REFERENCES_UPDATE);
    }

    public function delete(User $user, EanReference $eanReference): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_EAN_REFERENCES_DELETE);
    }
}
