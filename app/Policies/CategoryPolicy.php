<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class CategoryPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_CATEGORIES_VIEW_ANY);
    }

    public function view(User $user, Category $category): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_CATEGORIES_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_CATEGORIES_CREATE);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_CATEGORIES_UPDATE);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_CATEGORIES_DELETE);
    }
}
