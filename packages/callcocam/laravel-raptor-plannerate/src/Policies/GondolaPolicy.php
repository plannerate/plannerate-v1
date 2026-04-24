<?php

namespace Callcocam\LaravelRaptorPlannerate\Policies;

use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;

class GondolaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('gondolas.viewAny');
    }

    public function view(User $user, Gondola $gondola): bool
    {
        return $user->can('gondolas.view');
    }

    public function create(User $user): bool
    {
        return $user->can('gondolas.create');
    }

    public function update(User $user, Gondola $gondola): bool
    {
        return $user->can('gondolas.update');
    }

    public function delete(User $user, Gondola $gondola): bool
    {
        return $user->can('gondolas.delete');
    }
}
