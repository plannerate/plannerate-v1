<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;
use Callcocam\LaravelIntegrations\Models\IntegrationApi;

class IntegrationApiPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_INTEGRATION_APIS_VIEW_ANY);
    }

    public function view(User $user, IntegrationApi $integrationApi): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_INTEGRATION_APIS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_INTEGRATION_APIS_CREATE);
    }

    public function update(User $user, IntegrationApi $integrationApi): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_INTEGRATION_APIS_UPDATE);
    }

    public function delete(User $user, IntegrationApi $integrationApi): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_INTEGRATION_APIS_DELETE);
    }
}
