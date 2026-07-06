<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockActionsWhileImpersonating
{
    /**
     * Bloqueia a ação (403) se a sessão atual estiver marcada como uma sessão de
     * impersonation ativa — usado nas rotas sensíveis (senha, email, 2FA, exclusão de
     * conta) para impedir que um acesso de suporte altere credenciais do cliente.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (is_string($request->session()->get('impersonation.token_id'))) {
            abort(403, __('app.impersonation.action_blocked'));
        }

        return $next($request);
    }
}
