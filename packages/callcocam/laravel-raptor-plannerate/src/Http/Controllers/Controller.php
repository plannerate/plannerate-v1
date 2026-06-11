<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /** @var array<int, int> Tamanhos de página permitidos na paginação */
    protected $allowedValues = [10, 25, 50, 100];

    /**
     * Resolve o per_page da request restrito aos valores permitidos.
     */
    protected function resolvePerPage(Request $request, int $default = 10): int
    {
        $requestedPerPage = (int) $request->integer('per_page', $default);

        return in_array($requestedPerPage, $this->allowedValues, true) ? $requestedPerPage : $default;
    }

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
