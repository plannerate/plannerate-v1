<?php

namespace App\Services\PasswordSetup;

use App\Concerns\PasswordValidationRules;
use App\Models\Tenant;
use App\Models\TenantPasswordSetupToken;
use App\Models\User;
use App\Support\PasswordSetup\PasswordSetupException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ConsumePasswordSetupService
{
    use PasswordValidationRules;

    /**
     * Localiza e valida o token de definição de senha, sem efeitos colaterais.
     * Usado tanto para renderizar o formulário (GET) quanto no início do envio (POST).
     */
    public function locate(Tenant $tenant, string $plainCode): TenantPasswordSetupToken
    {
        $token = TenantPasswordSetupToken::query()
            ->where('code_hash', hash('sha256', $plainCode))
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $token instanceof TenantPasswordSetupToken) {
            throw new PasswordSetupException(__('app.password_setup.errors.invalid_code'));
        }

        if ($token->isUsed()) {
            throw new PasswordSetupException(__('app.password_setup.errors.already_used'));
        }

        if ($token->isExpired()) {
            throw new PasswordSetupException(__('app.password_setup.errors.expired_code'));
        }

        if ($tenant->status !== 'active') {
            throw new PasswordSetupException(__('app.password_setup.errors.tenant_unavailable'));
        }

        $target = User::query()->find($token->target_user_id);

        if (! $target instanceof User || ! $target->is_active) {
            throw new PasswordSetupException(__('app.password_setup.errors.target_unavailable'));
        }

        return $token;
    }

    /**
     * Valida o token, define a nova senha do usuário-alvo (mesma regra forte usada pelo
     * reset de senha padrão do Fortify), marca o token como usado e autentica o usuário.
     *
     * @param  array<string, string>  $input
     */
    public function consume(Request $request, Tenant $tenant, string $plainCode, array $input): User
    {
        $token = $this->locate($tenant, $plainCode);

        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $target = User::query()->findOrFail($token->target_user_id);
        $target->forceFill(['password' => $input['password']])->save();

        $token->markUsed('consumed');

        Auth::login($target, remember: false);
        $request->session()->regenerate();

        return $target;
    }
}
