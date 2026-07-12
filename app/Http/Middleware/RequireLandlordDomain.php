<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia o acesso a rotas que só fazem sentido no domínio raiz (landlord),
 * nunca num subdomínio de tenant — ex.: o pacote WhatsApp Cloud, que opera
 * numa única WABA global e não tem noção de tenant.
 */
class RequireLandlordDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $landlordDomain = strtolower((string) config('app.landlord_domain'));
        $host = strtolower($request->getHost());

        abort_unless($landlordDomain !== '' && $host === $landlordDomain, 403);

        return $next($request);
    }
}
