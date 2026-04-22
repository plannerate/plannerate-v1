<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PermissionName;

class TenantPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (! config('permission.rbac_enabled', false)) {
            return true;
        }

        if ($this->isLandlordContext()) {
            return true;
        }

        return $user->can(PermissionName::TENANT_DASHBOARD_VIEW);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $this->allowLandlordAction($user, PermissionName::LANDLORD_TENANTS_VIEW);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->allowLandlordAction($user, PermissionName::LANDLORD_TENANTS_CREATE);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $this->allowLandlordAction($user, PermissionName::LANDLORD_TENANTS_UPDATE);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $this->allowLandlordAction($user, PermissionName::LANDLORD_TENANTS_DELETE);
    }

    private function allowLandlordAction(User $user, string $permission): bool
    {
        if (! config('permission.rbac_enabled', false)) {
            return true;
        }

        if ($this->isLandlordContext()) {
            return true;
        }

        return $user->can($permission);
    }

    private function isLandlordContext(): bool
    {
        $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

        return ! app()->bound($containerKey) || app($containerKey) === null;
    }
}
