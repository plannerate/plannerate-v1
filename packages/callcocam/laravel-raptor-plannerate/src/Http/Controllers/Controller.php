<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Redireciona para uma rota de tenant.
     * Ponto central para redirects de tenant — facilita suporte a domínio próprio no futuro.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function toTenantRoute(string $name, array $parameters = [], int $status = 302): RedirectResponse
    {
        return to_route($name, $parameters, $status);
    }
}
