<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injeta o subdomain do tenant atual como parâmetro padrão de URL.
 * Isso elimina a necessidade de passar ['subdomain' => ...] manualmente
 * em todas as chamadas route() e to_route() de rotas de tenant.
 */
class InjectTenantUrlDefaults
{
    public function handle(Request $request, Closure $next): Response
    {
        $landlordDomain = strtolower((string) config('app.landlord_domain'));
        $host = strtolower($request->getHost());

        if ($landlordDomain !== '' && $host !== '' && str_ends_with($host, '.'.$landlordDomain)) {
            $subdomain = substr($host, 0, -1 * (strlen($landlordDomain) + 1));

            if ($subdomain !== '') {
                URL::defaults(['subdomain' => $subdomain]);
            }
        }

        return $next($request);
    }
}
