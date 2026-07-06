<?php

namespace App\Services\PasswordSetup;

use App\Http\Controllers\Concerns\SwitchesTenantContext;
use App\Models\Tenant;
use App\Models\TenantPasswordSetupToken;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use App\Support\PasswordSetup\PasswordSetupException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IssuePasswordSetupService
{
    use SwitchesTenantContext;

    /**
     * Emite (ou reemite) um link de definição de senha de uso único para um usuário do
     * tenant, invalidando qualquer link pendente anterior. Usado tanto logo após a
     * criação do usuário quanto no reenvio manual disparado por um admin.
     *
     * @return array{token: TenantPasswordSetupToken, setupUrl: string}
     */
    public function issue(Tenant $tenant, string $targetUserId, User $issuer, Request $request, bool $isResend = false): array
    {
        if ($tenant->status !== 'active') {
            throw new PasswordSetupException(__('app.password_setup.errors.tenant_unavailable'));
        }

        return $this->runInTenantContext($tenant, function () use ($tenant, $targetUserId, $issuer, $request, $isResend): array {
            $target = User::query()->find($targetUserId);

            if (! $target instanceof User || ! $target->is_active) {
                throw new PasswordSetupException(__('app.password_setup.errors.target_unavailable'));
            }

            TenantPasswordSetupToken::invalidateOutstandingFor($tenant->id, $target->id);

            $plainCode = Str::random(48);

            $token = TenantPasswordSetupToken::query()->create([
                'tenant_id' => $tenant->id,
                'target_user_id' => $target->id,
                'target_user_name' => $target->name,
                'target_user_email' => $target->email,
                'issuer_id' => $issuer->id,
                'issuer_name' => $issuer->name,
                'issuer_email' => $issuer->email,
                'code_hash' => hash('sha256', $plainCode),
                'status' => TenantPasswordSetupToken::STATUS_PENDING,
                'expires_at' => now()->addDays((int) config('password_setup.code_ttl_days', 7)),
            ]);

            // route() não pode ser usado aqui: mesmo motivo documentado em
            // IssueImpersonationService — routes/tenant.php não declara Route::domain(),
            // então route() a partir do domínio landlord geraria o host errado.
            $setupUrl = sprintf(
                '%s://%s/password/setup/%s',
                $request->getScheme(),
                $tenant->primaryDomain->host,
                $plainCode,
            );

            $target->notify(new SetPasswordNotification($setupUrl, $isResend));

            return ['token' => $token, 'setupUrl' => $setupUrl];
        });
    }
}
