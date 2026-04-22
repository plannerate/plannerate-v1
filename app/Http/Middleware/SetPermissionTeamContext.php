<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionTeamContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $teamId = null;
        $containerKey = (string) config('multitenancy.current_tenant_container_key', 'currentTenant');

        if (app()->bound($containerKey)) {
            $tenant = app($containerKey);
            $teamId = $tenant?->getKey();
        }

        setPermissionsTeamId($teamId);

        $user = $request->user();

        if ($user !== null) {
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');
        }

        return $next($request);
    }
}
