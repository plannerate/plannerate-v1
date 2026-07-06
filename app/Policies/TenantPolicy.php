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

    /**
     * Determine whether the user can impersonate a user of this tenant.
     *
     * Deliberately does NOT go through allowByContext()/allowLandlordAction(): those bypass
     * any check whenever there's no tenant currently bound (isLandlordContext()), which is
     * always true for requests on the landlord domain — unacceptable for granting a live
     * session as a customer's user. This checks the role explicitly and unconditionally.
     *
     * Uses system_name (not hasRole(), which matches the human-readable `name` column, e.g.
     * "Landlord Admin") — same pattern as AppServiceProvider's super-admin Gate::before check.
     */
    public function impersonate(User $user, Tenant $tenant): bool
    {
        return $user->roles()->whereIn('system_name', ['super-admin', 'landlord-admin'])->exists();
    }

    private function allowLandlordAction(User $user, string $permission): bool
    {
        return $this->allowByContext($user, $permission);
    }
}
