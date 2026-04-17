<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\Auth\LoginAsTokenBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoginAsController
{
    public function __construct(
        protected LoginAsTokenBroker $tokenBroker
    ) {}

    public function loginAs(Request $request): RedirectResponse
    {
        $token = trim((string) $request->query('token', ''));

        if ($token === '') {
            abort(403);
        }

        $tokenData = $this->tokenBroker->consume(
            plainToken: $token,
            expectedTenantId: config('app.current_tenant_id'),
            expectedClientId: config('app.current_client_id')
        );

        if (! $tokenData) {
            abort(403);
        }

        $user = User::query()->find($tokenData->actorUserId);

        if (! $user || ! $user->hasRole('super-admin') || (string) $user->tenant_id !== $tokenData->tenantId) {
            abort(403);
        }

        auth()->login($user);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('dashboard');
    }
}

