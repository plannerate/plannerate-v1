<?php

namespace App\Policies;

use App\Models\SimilarGroup;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class SimilarGroupPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_SIMILAR_GROUPS_VIEW_ANY);
    }

    public function view(User $user, SimilarGroup $similarGroup): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_SIMILAR_GROUPS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_SIMILAR_GROUPS_CREATE);
    }

    public function update(User $user, SimilarGroup $similarGroup): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_SIMILAR_GROUPS_UPDATE);
    }

    public function delete(User $user, SimilarGroup $similarGroup): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_SIMILAR_GROUPS_DELETE);
    }
}
