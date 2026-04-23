<?php

namespace App\Policies;

use App\Models\Cluster;
use App\Models\User;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;

class ClusterPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_CLUSTERS_VIEW_ANY);
    }

    public function view(User $user, Cluster $cluster): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_CLUSTERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_CLUSTERS_CREATE);
    }

    public function update(User $user, Cluster $cluster): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_CLUSTERS_UPDATE);
    }

    public function delete(User $user, Cluster $cluster): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_CLUSTERS_DELETE);
    }
}
