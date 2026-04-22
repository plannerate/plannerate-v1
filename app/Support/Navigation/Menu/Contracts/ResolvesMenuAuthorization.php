<?php

namespace App\Support\Navigation\Menu\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface ResolvesMenuAuthorization
{
    public function allows(?Authenticatable $user, ?string $ability = null, mixed $subject = null): bool;
}
