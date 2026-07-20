<?php

namespace App\Services\DimensionShare;

use App\Models\Tenant;
use App\Models\TenantDimensionShareToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IssueDimensionShareService
{
    /**
     * Número de dias de validade do link de correção de dimensões.
     */
    private const TTL_DAYS = 7;

    /**
     * Emite um link público de correção de dimensões para um tenant, delimitado pelo
     * escopo informado (categoria, gôndola ou tenant inteiro). Guarda apenas o hash do
     * código; o código em texto puro só existe na URL retornada.
     *
     * @return array{token: TenantDimensionShareToken, shareUrl: string}
     */
    public function issue(Tenant $tenant, DimensionShareScope $scope, User $issuer, Request $request): array
    {
        $plainCode = Str::random(48);

        $token = TenantDimensionShareToken::query()->create([
            'tenant_id' => $tenant->id,
            ...$scope->toAttributes(),
            'issuer_id' => $issuer->id,
            'issuer_name' => $issuer->name,
            'issuer_email' => $issuer->email,
            'code_hash' => hash('sha256', $plainCode),
            'status' => TenantDimensionShareToken::STATUS_ACTIVE,
            'expires_at' => now()->addDays(self::TTL_DAYS),
        ]);

        // route() não pode ser usado aqui: routes/tenant.php não declara Route::domain(),
        // então gerar a URL a partir do domínio landlord produziria o host errado.
        // Mesmo motivo documentado em IssuePasswordSetupService.
        $shareUrl = sprintf(
            '%s://%s/dimensoes/%s',
            $request->getScheme(),
            $tenant->primaryDomain->host,
            $plainCode,
        );

        return ['token' => $token, 'shareUrl' => $shareUrl];
    }
}
