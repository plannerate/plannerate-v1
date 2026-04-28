<?php

namespace App\Services\Integrations\Auth\Strategies;

use App\Services\Integrations\Auth\AuthStrategy;
use Illuminate\Http\Client\PendingRequest;

class ApiKeyHeaderAuthStrategy implements AuthStrategy
{
    public function apply(PendingRequest $request, array $credentials): PendingRequest
    {
        $name = (string) ($credentials['name'] ?? '');
        $key = (string) ($credentials['key'] ?? '');
        $prefix = (string) ($credentials['prefix'] ?? '');

        if ($name === '' || $key === '') {
            return $request;
        }

        $value = $prefix !== '' ? trim($prefix.' '.$key) : $key;

        return $request->withHeaders([$name => $value]);
    }

    public function appendQuery(array $query, array $credentials): array
    {
        return $query;
    }
}
