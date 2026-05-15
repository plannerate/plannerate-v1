<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksRbacPermission
{
    protected function allowByContext(User $user, array|string $permissions): bool
    {
        if (! config('permission.rbac_enabled', false)) {
            return true;
        }

        if ($this->isLandlordContext()) {
            return true;
        }

        if (is_string($permissions)) {
            $permissions = [$permissions];
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    protected function isLandlordContext(): bool
    {
        $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

        return ! app()->bound($containerKey) || app($containerKey) === null;
    }
}
