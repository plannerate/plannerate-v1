<?php

namespace App\Policies;

use App\Models\GlobalPlanogramTemplate;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;

class GlobalPlanogramTemplatePolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->isLandlordContext();
    }

    public function view(User $user, GlobalPlanogramTemplate $template): bool
    {
        return $this->isLandlordContext();
    }

    public function create(User $user): bool
    {
        return $this->isLandlordContext();
    }

    public function update(User $user, GlobalPlanogramTemplate $template): bool
    {
        return $this->isLandlordContext();
    }

    public function delete(User $user, GlobalPlanogramTemplate $template): bool
    {
        return $this->isLandlordContext();
    }

    public function share(User $user, GlobalPlanogramTemplate $template): bool
    {
        return $this->isLandlordContext();
    }
}
