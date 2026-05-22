<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class ProductDimensionPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PRODUCTS_VIEW_ANY);
    }

    public function approve(User $user, Product $product): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PRODUCTS_UPDATE);
    }

    public function reject(User $user, Product $product): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PRODUCTS_UPDATE);
    }

    public function research(User $user, Product $product): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_PRODUCTS_UPDATE);
    }
}
