<?php

namespace App\Support\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

class PermissionResolver
{
    /**
     * Resolve if a user is allowed for a given ability/subject.
     */
    public function allows(?Authenticatable $user, string $ability, mixed $subject = null): bool
    {
        if (! $user) {
            return false;
        }

        return $subject === null
            ? Gate::forUser($user)->check($ability)
            : Gate::forUser($user)->check($ability, $subject);
    }
}
