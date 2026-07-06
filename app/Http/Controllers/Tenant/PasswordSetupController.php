<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PasswordSetup\ConsumePasswordSetupService;
use App\Support\PasswordSetup\PasswordSetupException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordSetupController extends Controller
{
    /**
     * Renderiza a página de definição de senha para um link válido, ou redireciona
     * para o login com o erro caso o código seja inválido/expirado/já usado.
     */
    public function edit(Request $request, string $code): Response|RedirectResponse
    {
        $tenant = Tenant::current();

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        try {
            $token = app(ConsumePasswordSetupService::class)->locate($tenant, $code);
        } catch (PasswordSetupException $exception) {
            return redirect()->route('login')->withErrors([
                'email' => $exception->getMessage(),
            ]);
        }

        return Inertia::render('auth/SetPassword', [
            'code' => $code,
            'email' => $token->target_user_email,
        ]);
    }

    /**
     * Consome o link, define a senha escolhida e autentica o usuário no dashboard
     * do próprio tenant.
     */
    public function update(Request $request, string $code): RedirectResponse
    {
        $tenant = Tenant::current();

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        try {
            app(ConsumePasswordSetupService::class)->consume(
                $request,
                $tenant,
                $code,
                $request->only(['password', 'password_confirmation']),
            );
        } catch (PasswordSetupException $exception) {
            return redirect()->route('login')->withErrors([
                'email' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.password_setup.page.success'),
        ]);

        return redirect()->route('tenant.dashboard');
    }
}
