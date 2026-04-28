<?php

use App\Services\Integrations\Auth\AuthStrategyResolver;
use App\Services\Integrations\Auth\Strategies\ApiKeyHeaderAuthStrategy;
use App\Services\Integrations\Auth\Strategies\ApiKeyQueryAuthStrategy;
use App\Services\Integrations\Auth\Strategies\BasicAuthStrategy;
use App\Services\Integrations\Auth\Strategies\BearerTokenAuthStrategy;
use App\Services\Integrations\Auth\Strategies\CustomHeadersAuthStrategy;
use App\Services\Integrations\Auth\Strategies\NoAuthStrategy;

test('resolver returns expected strategy for each auth type', function () {
    $resolver = new AuthStrategyResolver;

    expect($resolver->resolve('bearer'))->toBeInstanceOf(BearerTokenAuthStrategy::class)
        ->and($resolver->resolve('basic'))->toBeInstanceOf(BasicAuthStrategy::class)
        ->and($resolver->resolve('api_key_header'))->toBeInstanceOf(ApiKeyHeaderAuthStrategy::class)
        ->and($resolver->resolve('api_key_query'))->toBeInstanceOf(ApiKeyQueryAuthStrategy::class)
        ->and($resolver->resolve('custom_headers'))->toBeInstanceOf(CustomHeadersAuthStrategy::class)
        ->and($resolver->resolve('unknown_type'))->toBeInstanceOf(NoAuthStrategy::class);
});

test('api key query strategy appends query parameter', function () {
    $strategy = (new AuthStrategyResolver)->resolve('api_key_query');

    $query = $strategy->appendQuery(['page' => 1], [
        'name' => 'api_key',
        'key' => 'abc123',
    ]);

    expect($query)->toBe([
        'page' => 1,
        'api_key' => 'abc123',
    ]);
});

test('custom headers strategy keeps query untouched', function () {
    $strategy = (new AuthStrategyResolver)->resolve('custom_headers');
    $query = $strategy->appendQuery(['page' => 1], [
        'headers' => [
            'X-Tenant' => 'tenant-01',
        ],
    ]);

    expect($query)->toBe(['page' => 1]);
});
