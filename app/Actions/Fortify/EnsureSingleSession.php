<?php

namespace App\Actions\Fortify;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureSingleSession
{
    /**
     * Handle the incoming request.
     *
     * Terminates all other active sessions when single_session is enabled,
     * preventing concurrent logins across devices.
     */
    public function handle(Request $request, callable $next): mixed
    {
        if (config('fortify.single_session') && Auth::check()) {
            Auth::logoutOtherDevices((string) $request->input('password'));
        }

        return $next($request);
    }
}
