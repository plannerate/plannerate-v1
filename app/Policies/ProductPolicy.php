<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class ProductPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PRODUCTS_VIEW_ANY);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PRODUCTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PRODUCTS_CREATE);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PRODUCTS_UPDATE);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PRODUCTS_DELETE);
    }
}
