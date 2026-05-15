<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectClientRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->hasRole('client')) {
            $clientRoute = 'tenant.editor.planograms.index';

            if ($request->route()?->getName() !== $clientRoute) {
                return redirect()->route($clientRoute, [
                    'subdomain' => $request->route('subdomain'),
                ]);
            }
        }

        return $next($request);
    }
}
