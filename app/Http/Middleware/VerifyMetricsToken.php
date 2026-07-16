<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege o endpoint /metrics (Prometheus) com um bearer token estático.
 * Sem sessão/CSRF — é uma rota interna raspada pelo Prometheus via Traefik.
 * Nega o acesso se o token não estiver configurado, evitando expor o endpoint
 * por engano quando METRICS_TOKEN estiver ausente no ambiente.
 */
class VerifyMetricsToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.metrics.token');
        $provided = (string) $request->bearerToken();

        abort_if($expected === '' || ! hash_equals($expected, $provided), 401);

        return $next($request);
    }
}
