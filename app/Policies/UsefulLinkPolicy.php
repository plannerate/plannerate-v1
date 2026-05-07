<?php

namespace App\Policies;

use App\Models\UsefulLink;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class UsefulLinkPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_USEFUL_LINKS_VIEW_ANY);
    }

    public function view(User $user, UsefulLink $usefulLink): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_USEFUL_LINKS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_USEFUL_LINKS_CREATE);
    }

    public function update(User $user, UsefulLink $usefulLink): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_USEFUL_LINKS_UPDATE);
    }

    public function delete(User $user, UsefulLink $usefulLink): bool
    {
        return $this->allowByContext($user, PermissionName::LANDLORD_USEFUL_LINKS_DELETE);
    }
}
