<?php

namespace App\Services\Integrations\Auth\Strategies;

use App\Services\Integrations\Auth\AuthStrategy;
use Illuminate\Http\Client\PendingRequest;

class BearerTokenAuthStrategy implements AuthStrategy
{
    public function apply(PendingRequest $request, array $credentials): PendingRequest
    {
        $token = (string) ($credentials['token'] ?? '');

        if ($token === '') {
            return $request;
        }

        return $request->withToken($token);
    }

    public function appendQuery(array $query, array $credentials): array
    {
        return $query;
    }
}
