<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Services\PlanLimitService;
use App\Support\Authorization\PermissionName;

class StorePolicy
{
    use ChecksRbacPermission;

    public function __construct(private readonly PlanLimitService $planLimitService) {}

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
        if (! $this->allowByContext($user, PermissionName::TENANT_STORES_CREATE)) {
            return false;
        }

        return $this->planLimitService->withinLimit('store_limit', Store::count());
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
