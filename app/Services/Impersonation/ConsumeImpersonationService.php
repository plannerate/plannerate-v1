<?php

namespace App\Services\Impersonation;

use App\Models\Tenant;
use App\Models\TenantImpersonationToken;
use App\Models\User;
use App\Support\Impersonation\ImpersonationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConsumeImpersonationService
{
    /**
     * Valida e consome o código de impersonation, autenticando o usuário-alvo e iniciando
     * a sessão de impersonation. Já roda dentro do contexto de tenant trocado pelo
     * middleware NeedsTenant na rota de consumo, então User::query() resolve contra o
     * banco físico do próprio tenant.
     */
    public function consume(Request $request, Tenant $tenant, string $plainCode): TenantImpersonationToken
    {
        $token = TenantImpersonationToken::query()
            ->where('code_hash', hash('sha256', $plainCode))
            ->where('tenant_id', $tenant->id)
            ->where('status', TenantImpersonationToken::STATUS_PENDING)
            ->first();

        if (! $token instanceof TenantImpersonationToken) {
            throw new ImpersonationException(__('app.impersonation.errors.invalid_code'));
        }

        if ($token->isCodeExpired()) {
            $token->markEnded('expired_code');

            throw new ImpersonationException(__('app.impersonation.errors.expired_code'));
        }

        if ($tenant->status !== 'active') {
            $token->markEnded('tenant_unavailable');

            throw new ImpersonationException(__('app.impersonation.errors.tenant_unavailable'));
        }

        $targetUser = User::query()->find($token->target_user_id);

        if (! $targetUser instanceof User || ! $targetUser->is_active) {
            $token->markEnded('target_unavailable');

            throw new ImpersonationException(__('app.impersonation.errors.target_unavailable'));
        }

        Auth::login($targetUser, remember: false);
        $request->session()->regenerate();
        $request->session()->put('impersonation.token_id', $token->id);

        $token->markActive((int) config('impersonation.session_ttl_minutes', 30));

        return $token;
    }
}
