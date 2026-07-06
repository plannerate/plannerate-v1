<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Impersonation\ConsumeImpersonationService;
use App\Services\Impersonation\LeaveImpersonationService;
use App\Support\Impersonation\ImpersonationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ImpersonationController extends Controller
{
    /**
     * Consome o código de impersonation vindo do link emitido pelo landlord e autentica
     * o usuário-alvo no host do próprio tenant. Rota pública (sem auth): a sessão ainda
     * não existe neste host quando o link é aberto.
     */
    public function consume(Request $request, string $code): RedirectResponse
    {
        $tenant = Tenant::current();

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        try {
            app(ConsumeImpersonationService::class)->consume($request, $tenant, $code);
        } catch (ImpersonationException $exception) {
            return redirect()->route('login')->withErrors([
                'email' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('tenant.dashboard');
    }

    /**
     * Encerra a sessão de impersonation e redireciona o navegador de volta ao painel
     * landlord (host diferente do atual, por isso Inertia::location() em vez de um
     * redirect comum).
     */
    public function leave(Request $request): RedirectResponse|SymfonyResponse
    {
        $returnUrl = app(LeaveImpersonationService::class)->leave($request);

        return Inertia::location($returnUrl);
    }
}
