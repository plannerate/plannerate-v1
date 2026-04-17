<?php

namespace App\Support\Workflow;

use Illuminate\Contracts\Auth\Authenticatable;

class FlowRoleChecker
{
    public static function check(Authenticatable $user, ?string $roleId): bool
    {
        if ($roleId === null) {
            return true;
        }

        if (! method_exists($user, 'roles')) {
            return false;
        }

        if (! $user->relationLoaded('roles')) {
            $user->load('roles');
        }

        return $user->roles->contains('id', $roleId);
    }
}
