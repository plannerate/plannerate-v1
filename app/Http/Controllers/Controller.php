<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    protected $allowedValues = [10, 25, 50, 100];

    protected function resolvePerPage(Request $request, int $default = 10): int
    {

        $requestedPerPage = (int) $request->integer('per_page', $default);

        return in_array($requestedPerPage, $this->allowedValues, true) ? $requestedPerPage : $default;
    }

    /**
     * Redireciona para uma rota de tenant.
     * Ponto central para redirects de tenant — facilita suporte a domínio próprio no futuro,
     * bastando alterar a geração de URL aqui sem tocar nos controllers.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function toTenantRoute(string $name, array $parameters = [], int $status = 302): RedirectResponse
    {
        return to_route($name, $parameters, $status);
    }

    /**
     * Redireciona para uma rota de landlord.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function toLandlordRoute(string $name, array $parameters = [], int $status = 302): RedirectResponse
    {
        return to_route($name, $parameters, $status);
    }
}
