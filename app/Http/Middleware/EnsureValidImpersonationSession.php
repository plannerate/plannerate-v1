<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantImpersonationToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidImpersonationSession
{
    /**
     * A cada request de tenant com uma sessão de impersonation ativa, revalida se o token
     * ainda está ativo, dentro do prazo, e se tenant/usuário-alvo continuam disponíveis —
     * força logout e encerra o token se qualquer condição falhar (ex.: usuário desativado
     * ou tenant suspenso durante a sessão).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tokenId = $request->session()->get('impersonation.token_id');

        if (! is_string($tokenId)) {
            return $next($request);
        }

        $token = TenantImpersonationToken::query()->find($tokenId);
        $tenant = Tenant::current();
        $user = $request->user();

        $reason = match (true) {
            ! $token instanceof TenantImpersonationToken => 'revoked',
            $token->status !== TenantImpersonationToken::STATUS_ACTIVE => 'revoked',
            $token->isSessionExpired() => 'expired_session',
            ! $tenant instanceof Tenant || $tenant->getKey() !== $token->tenant_id => 'tenant_unavailable',
            $tenant->status !== 'active' => 'tenant_unavailable',
            ! $user instanceof User || $user->id !== $token->target_user_id => 'revoked',
            ! $user->is_active => 'target_unavailable',
            default => null,
        };

        if ($reason === null) {
            return $next($request);
        }

        $token?->markEnded($reason);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => __('app.impersonation.errors.session_ended'),
        ]);
    }
}
