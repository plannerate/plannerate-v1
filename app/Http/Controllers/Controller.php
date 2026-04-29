<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    protected function resolvePerPage(Request $request, int $default = 10): int
    {
        $allowedValues = [10, 25, 50, 100];
        $requestedPerPage = (int) $request->integer('per_page', $default);

        return in_array($requestedPerPage, $allowedValues, true) ? $requestedPerPage : $default;
    }
}
