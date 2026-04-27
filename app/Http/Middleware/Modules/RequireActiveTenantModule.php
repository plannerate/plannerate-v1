<?php

namespace App\Http\Middleware\Modules;

use App\Models\Tenant;
use App\Support\Modules\TenantModuleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveTenantModule
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant === null) {
            abort(403);
        }

        $hasModule = app(TenantModuleService::class)
            ->tenantHasActiveModule($tenant, $slug);

        abort_unless($hasModule, 403);

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $routeTenant = $request->route('tenant');

        if ($routeTenant instanceof Tenant) {
            return $routeTenant;
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
