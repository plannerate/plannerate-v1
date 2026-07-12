<?php

use App\Http\Middleware\BlockActionsWhileImpersonating;
use App\Http\Middleware\EnsureValidImpersonationSession;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InjectTenantUrlDefaults;
use App\Http\Middleware\Modules\RequireActiveTenantModule;
use App\Http\Middleware\RedirectClientRole;
use App\Http\Middleware\RequireLandlordDomain;
use App\Http\Middleware\SetPermissionTeamContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Inertia\Inertia;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Traefik/Cloudflare we must trust forwarded proto/host to avoid HTTP asset URLs on HTTPS pages.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        /*
         * NeedsTenant antes de SubstituteBindings: o binding implícito (ex.: Product nas rotas api do editor)
         * precisa do tenant atual e da conexão já trocada; caso contrário o modelo não é encontrado (404).
         * SetPermissionTeamContext continua imediatamente antes de SubstituteBindings (após NeedsTenant).
         */
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: NeedsTenant::class,
        );

        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetPermissionTeamContext::class,
        );

        /*
         * EnsureValidImpersonationSession precisa rodar depois de NeedsTenant/SetPermissionTeamContext
         * (depende de Tenant::current() e do usuário autenticado já resolvidos) mas ainda antes de
         * SubstituteBindings.
         */
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: EnsureValidImpersonationSession::class,
        );

        /*
         * InjectTenantUrlDefaults extrai o subdomain do host e chama URL::defaults() para que
         * route('tenant.xxx') funcione sem passar o parâmetro subdomain manualmente.
         * É adicionado ao web group antes de HandleInertiaRequests, que constrói a navegação
         * usando route() e precisa que os defaults já estejam definidos.
         * É seguro rodar em todas as requests: no-op quando não é uma rota de tenant.
         */
        $middleware->web(append: [
            HandleAppearance::class,
            InjectTenantUrlDefaults::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            AuthenticateSession::class,
        ]);

        $middleware->alias([
            'tenant.module.active' => RequireActiveTenantModule::class,
            'tenant.client.redirect' => RedirectClientRole::class,
            'tenant.url.defaults' => InjectTenantUrlDefaults::class,
            'impersonation.block' => BlockActionsWhileImpersonating::class,
            'landlord.only' => RequireLandlordDomain::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Renderiza páginas de erro personalizadas do Plannerate (Inertia) no lugar
         * das telas padrão do Laravel/Symfony.
         *
         * - 419 (sessão/CSRF expirado): volta à página anterior com uma flash message,
         *   comportamento recomendado pelo Inertia (evita prender o usuário numa tela morta).
         * - 500 em modo debug: preserva a página de debug do Laravel (stack trace) para
         *   desenvolvimento; os demais status sempre usam a página de erro estilizada.
         * - Só intercepta requisições que esperam HTML (navegação); JSON/API seguem o
         *   fluxo padrão para não quebrar respostas de mutação/endpoints.
         */
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response|RedirectResponse {
            $status = $response->getStatusCode();

            $handledStatuses = [403, 404, 419, 429, 500, 503];

            if ($request->expectsJson() || ! in_array($status, $handledStatuses, true)) {
                return $response;
            }

            if ($status === 419) {
                return back()->with('message', trans('errors.419.description'));
            }

            if ($status === 500 && config('app.debug')) {
                return $response;
            }

            return Inertia::render('Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
