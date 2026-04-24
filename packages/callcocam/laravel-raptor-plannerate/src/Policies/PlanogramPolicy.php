<?php

namespace Callcocam\LaravelRaptorPlannerate\Policies;

use App\Models\User;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;

class PlanogramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('planograms.viewAny');
    }

    public function view(User $user, Planogram $planogram): bool
    {
        return $user->can('planograms.view');
    }

    public function create(User $user): bool
    {
        return $user->can('planograms.create');
    }

    public function update(User $user, Planogram $planogram): bool
    {
        return $user->can('planograms.update');
    }

    public function delete(User $user, Planogram $planogram): bool
    {
        return $user->can('planograms.delete');
    }
}
