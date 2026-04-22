<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class TenantPolicy
{
    use ChecksRbacPermission;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_DASHBOARD_VIEW);
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
        return $this->allowByContext($user, $permission);
    }
}
