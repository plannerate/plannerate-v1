<?php

namespace App\Services\Integrations\Auth;

use App\Services\Integrations\Auth\Strategies\ApiKeyHeaderAuthStrategy;
use App\Services\Integrations\Auth\Strategies\ApiKeyQueryAuthStrategy;
use App\Services\Integrations\Auth\Strategies\BasicAuthStrategy;
use App\Services\Integrations\Auth\Strategies\BearerTokenAuthStrategy;
use App\Services\Integrations\Auth\Strategies\CustomHeadersAuthStrategy;
use App\Services\Integrations\Auth\Strategies\NoAuthStrategy;

class AuthStrategyResolver
{
    public function resolve(string $type): AuthStrategy
    {
        return match ($type) {
            AuthenticationType::Bearer->value => new BearerTokenAuthStrategy,
            AuthenticationType::Basic->value => new BasicAuthStrategy,
            AuthenticationType::ApiKeyHeader->value => new ApiKeyHeaderAuthStrategy,
            AuthenticationType::ApiKeyQuery->value => new ApiKeyQueryAuthStrategy,
            AuthenticationType::CustomHeaders->value => new CustomHeadersAuthStrategy,
            default => new NoAuthStrategy,
        };
    }
}
