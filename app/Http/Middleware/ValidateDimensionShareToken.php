<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantDimensionShareToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida o token de um link público de correção de dimensões. O tenant já foi
 * resolvido pelo subdomínio (NeedsTenant); aqui garantimos que o código confere,
 * pertence ao tenant atual e ainda está ativo. O token é injetado no request para
 * os controllers consumirem via {@see self::TOKEN_ATTRIBUTE}.
 */
class ValidateDimensionShareToken
{
    public const TOKEN_ATTRIBUTE = 'dimensionShareToken';

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::current();

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        $code = (string) $request->route('code');

        $token = TenantDimensionShareToken::query()
            ->where('code_hash', hash('sha256', $code))
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $token instanceof TenantDimensionShareToken) {
            abort(403);
        }

        if (! $token->isActive()) {
            abort(410);
        }

        $token->registerUse();
        $request->attributes->set(self::TOKEN_ATTRIBUTE, $token);

        return $next($request);
    }
}
