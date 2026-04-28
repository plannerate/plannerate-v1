<?php

namespace App\Services\Integrations\Auth;

use Illuminate\Http\Client\PendingRequest;

interface AuthStrategy
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function apply(PendingRequest $request, array $credentials): PendingRequest;

    /**
     * @param  array<string, string|int|float|bool>  $query
     * @param  array<string, mixed>  $credentials
     * @return array<string, string|int|float|bool>
     */
    public function appendQuery(array $query, array $credentials): array;
}
