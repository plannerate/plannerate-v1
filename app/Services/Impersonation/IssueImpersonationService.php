<?php

namespace App\Services\Impersonation;

use App\Http\Controllers\Concerns\SwitchesTenantContext;
use App\Models\Tenant;
use App\Models\TenantImpersonationToken;
use App\Models\User;
use App\Support\Impersonation\ImpersonationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IssueImpersonationService
{
    use SwitchesTenantContext;

    /**
     * Emite um código de impersonation de uso único para um usuário de um tenant e monta a
     * URL de consumo no subdomínio do próprio tenant.
     *
     * @return array{token: TenantImpersonationToken, consumeUrl: string}
     */
    public function issue(User $issuer, Tenant $tenant, string $targetUserId, Request $request): array
    {
        if ($tenant->status !== 'active') {
            throw new ImpersonationException(__('app.impersonation.errors.tenant_unavailable'));
        }

        $target = $this->runInTenantContext($tenant, fn (): ?User => User::query()->find($targetUserId));

        if (! $target instanceof User || ! $target->is_active) {
            throw new ImpersonationException(__('app.impersonation.errors.target_unavailable'));
        }

        $plainCode = Str::random(48);

        $token = TenantImpersonationToken::query()->create([
            'tenant_id' => $tenant->id,
            'target_user_id' => $target->id,
            'target_user_name' => $target->name,
            'target_user_email' => $target->email,
            'issuer_id' => $issuer->id,
            'issuer_name' => $issuer->name,
            'issuer_email' => $issuer->email,
            'code_hash' => hash('sha256', $plainCode),
            'status' => TenantImpersonationToken::STATUS_PENDING,
            'expires_at' => now()->addMinutes((int) config('impersonation.code_ttl_minutes', 2)),
        ]);

        // route() não pode ser usado aqui: routes/tenant.php não declara Route::domain(),
        // então route() a partir do domínio landlord geraria o host errado.
        //
        // O host salvo em primaryDomain é dado agnóstico de ambiente (ex.: sufixo
        // .plannerate.com.br persiste igual em produção e local). Usar esse host cru
        // mandaria a impersonation de qualquer tenant .com.br para produção mesmo
        // rodando localmente. Por isso remontamos o host: subdomínio do tenant +
        // domínio landlord do ambiente atual (config), preservando esquema e porta
        // da requisição.
        $subdomain = Str::before($tenant->primaryDomain->host, '.');
        $host = $subdomain.'.'.config('app.landlord_domain');

        $port = $request->getPort();

        if ($port !== null && ! in_array($port, [80, 443], true)) {
            $host .= ':'.$port;
        }

        $consumeUrl = sprintf(
            '%s://%s/impersonation/consume/%s',
            $request->getScheme(),
            $host,
            $plainCode,
        );

        return ['token' => $token, 'consumeUrl' => $consumeUrl];
    }
}
