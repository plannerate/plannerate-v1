<?php

namespace App\Support\Navigation\Menu;

use App\Support\Authorization\PermissionResolver;
use App\Support\Navigation\Menu\Contracts\ResolvesMenuAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class MenuAuthorizationResolver implements ResolvesMenuAuthorization
{
    public function __construct(
        private PermissionResolver $permissionResolver,
    ) {}

    public function allows(?Authenticatable $user, ?string $ability = null, mixed $subject = null): bool
    {
        if ($ability === null) {
            return $user !== null;
        }

        return $this->permissionResolver->allows($user, $ability, $subject);
    }
}
