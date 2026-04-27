<?php

namespace Callcocam\LaravelRaptorPlannerate\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Policies\Concerns\ChecksRbacPermission;
use App\Support\Authorization\PermissionName;
use App\Support\Modules\ModuleSlug;
use App\Support\Modules\TenantModuleService;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;

class GondolaPolicy
{
    use ChecksRbacPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_VIEW_ANY);
    }

    public function view(User $user, Gondola $gondola): bool
    {
        if (! $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_VIEW)) {
            return false;
        }

        $tenant = $this->resolveTenant();

        if (! $tenant instanceof Tenant) {
            return true;
        }

        $hasKanban = app(TenantModuleService::class)
            ->tenantHasActiveModule($tenant, ModuleSlug::KANBAN);

        if (! $hasKanban) {
            return true;
        }

        return WorkflowGondolaExecution::query()
            ->where('gondola_id', $gondola->id)
            ->where('current_responsible_id', $user->id)
            ->whereNotNull('execution_started_by')
            ->whereNotNull('started_at')
            ->exists();
    }

    public function create(User $user): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_CREATE);
    }

    public function update(User $user, Gondola $gondola): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_UPDATE);
    }

    public function delete(User $user, Gondola $gondola): bool
    {
        return $this->allowByContext($user, PermissionName::TENANT_GONDOLAS_DELETE);
    }

    private function resolveTenant(): ?Tenant
    {
        if (app()->bound('tenant')) {
            $tenant = app('tenant');

            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        $current = Tenant::current();

        if ($current instanceof Tenant) {
            return $current;
        }

        $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

        if (! app()->bound($containerKey)) {
            return null;
        }

        $resolved = app($containerKey);

        return $resolved instanceof Tenant ? $resolved : null;
    }
}
