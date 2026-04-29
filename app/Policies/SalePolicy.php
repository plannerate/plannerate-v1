<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class SalePolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_SALES_VIEW_ANY);
    }

    public function view(User $user, Sale $sale): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_SALES_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_SALES_CREATE);
    }

    public function update(User $user, Sale $sale): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_SALES_UPDATE);
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_SALES_DELETE);
    }
}
