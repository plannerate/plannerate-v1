<?php

namespace App\Services\Impersonation;

use App\Models\Tenant;
use App\Models\TenantImpersonationToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveImpersonationService
{
    /**
     * Encerra a sessão de impersonation ativa (token + sessão do usuário impersonado) e
     * devolve a URL de retorno para o painel landlord.
     */
    public function leave(Request $request, string $reason = 'left'): string
    {
        $tokenId = $request->session()->get('impersonation.token_id');
        $token = is_string($tokenId) ? TenantImpersonationToken::query()->find($tokenId) : null;

        $tenantId = $token?->tenant_id ?? Tenant::current()?->getKey();

        $token?->markEnded($reason);

        // Montada manualmente (não via route()) pelo mesmo motivo do IssueImpersonationService:
        // a URL de retorno precisa apontar para o host raiz do landlord, diferente do host atual.
        $returnUrl = sprintf(
            '%s://%s/tenants/%s/access',
            $request->getScheme(),
            config('app.landlord_domain'),
            $tenantId,
        );

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $returnUrl;
    }
}
