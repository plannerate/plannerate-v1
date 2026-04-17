<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceWorkflowEnabled
{
    /**
     * Block access to workflow routes when the tenant has not enabled the workflow module.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $tenant = app('current.tenant');
        } catch (\Throwable) {
            return $next($request);
        }

        if ($tenant === null) {
            return $next($request);
        }

        $enabled = (bool) data_get($tenant?->settings, 'features.use_workflow', false);

        if (! $enabled) {
            abort(403, 'O módulo de workflow não está habilitado para este tenant.');
        }

        return $next($request);
    }
}
