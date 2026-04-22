<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksRbacPermission
{
    protected function allowByContext(User $user, string $permission): bool
    {
        if (! config('permission.rbac_enabled', false)) {
            return true;
        }

        if ($this->isLandlordContext()) {
            return true;
        }

        return $user->can($permission);
    }

    protected function isLandlordContext(): bool
    {
        $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

        return ! app()->bound($containerKey) || app($containerKey) === null;
    }
}
