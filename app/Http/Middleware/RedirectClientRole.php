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
        if ($request->user()?->hasRole('client')) {
            return redirect()->route('tenant.editor.planograms.index', [
                'subdomain' => $request->route('subdomain'),
            ]);
        }

        return $next($request);
    }
}
