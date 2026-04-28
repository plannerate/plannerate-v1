<?php

namespace App\Services\Integrations\Auth\Strategies;

use App\Services\Integrations\Auth\AuthStrategy;
use Illuminate\Http\Client\PendingRequest;

class ApiKeyQueryAuthStrategy implements AuthStrategy
{
    public function apply(PendingRequest $request, array $credentials): PendingRequest
    {
        return $request;
    }

    public function appendQuery(array $query, array $credentials): array
    {
        $name = (string) ($credentials['name'] ?? '');
        $key = (string) ($credentials['key'] ?? '');

        if ($name === '' || $key === '') {
            return $query;
        }

        $query[$name] = $key;

        return $query;
    }
}
