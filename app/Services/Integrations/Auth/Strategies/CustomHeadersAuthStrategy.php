<?php

namespace App\Services\Integrations\Auth\Strategies;

use App\Services\Integrations\Auth\AuthStrategy;
use Illuminate\Http\Client\PendingRequest;

class CustomHeadersAuthStrategy implements AuthStrategy
{
    public function apply(PendingRequest $request, array $credentials): PendingRequest
    {
        $headers = $credentials['headers'] ?? [];

        if (! is_array($headers)) {
            return $request;
        }

        $normalized = [];

        foreach ($headers as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }

            $normalized[$key] = (string) $value;
        }

        if ($normalized === []) {
            return $request;
        }

        return $request->withHeaders($normalized);
    }

    public function appendQuery(array $query, array $credentials): array
    {
        return $query;
    }
}
