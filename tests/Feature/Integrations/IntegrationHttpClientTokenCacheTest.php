<?php

use App\Services\Integrations\IntegrationHttpClient;
use Illuminate\Support\Facades\Http;

function tokenCacheClientConfig(string $host): array
{
    return [
        'connection' => ['base_url' => "https://{$host}"],
        'auth' => [
            'type' => 'bearer',
            'token_mode' => 'fetch',
            'credentials' => ['username' => 'user', 'password' => 'secret'],
            'token_request' => ['path' => '/auth', 'method' => 'post', 'response_path' => 'token'],
        ],
    ];
}

test('bearer token via fetch é cacheado entre chamadas', function (): void {
    Http::fake([
        'erp.tokencache.test/auth*' => Http::response(['token' => 'tok-123']),
        'erp.tokencache.test/*' => Http::response(['ok' => true]),
    ]);

    $config = tokenCacheClientConfig('erp.tokencache.test');

    (new IntegrationHttpClient($config))->call('get', 'https://erp.tokencache.test/products', []);
    (new IntegrationHttpClient($config))->call('get', 'https://erp.tokencache.test/products', []);

    // 1 request de token + 2 chamadas de dados (antes: 1 token POR chamada)
    Http::assertSentCount(3);
});

test('resposta 401 invalida o cache e a chamada seguinte busca token novo', function (): void {
    Http::fake([
        'erp.tokenbust.test/auth*' => Http::sequence()
            ->push(['token' => 'tok-velho'])
            ->push(['token' => 'tok-novo']),
        'erp.tokenbust.test/products*' => Http::sequence()
            ->push(null, 401)
            ->push(['ok' => true]),
    ]);

    $config = tokenCacheClientConfig('erp.tokenbust.test');

    $first = (new IntegrationHttpClient($config))->call('get', 'https://erp.tokenbust.test/products', []);
    $second = (new IntegrationHttpClient($config))->call('get', 'https://erp.tokenbust.test/products', []);

    expect($first->status())->toBe(401)
        ->and($second->successful())->toBeTrue();

    // 2 tokens (o cache foi invalidado pelo 401) + 2 chamadas de dados
    Http::assertSentCount(4);
});
