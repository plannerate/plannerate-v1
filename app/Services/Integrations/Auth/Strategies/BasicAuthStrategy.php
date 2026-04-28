<?php

namespace App\Services\Integrations\Auth\Strategies;

use App\Services\Integrations\Auth\AuthStrategy;
use Illuminate\Http\Client\PendingRequest;

class BasicAuthStrategy implements AuthStrategy
{
    public function apply(PendingRequest $request, array $credentials): PendingRequest
    {
        $username = (string) ($credentials['username'] ?? '');
        $password = (string) ($credentials['password'] ?? '');

        if ($username === '' && $password === '') {
            return $request;
        }

        return $request->withBasicAuth($username, $password);
    }

    public function appendQuery(array $query, array $credentials): array
    {
        return $query;
    }
}
