<?php

use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\Http\IntegrationHttpClient;
use App\Services\Integrations\Importers\GescooperImporter;
use App\Services\Integrations\Importers\SysmoImporter;
use App\Services\Integrations\Support\ImportBatchPayloadStore;
use App\Services\Integrations\Support\IntegrationResponseReader;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('client builds request with base url auth headers and enabled params', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

    Http::fake([
        'https://api.example.test/products*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'config' => [
            'auth' => [
                'type' => 'basic',
                'credentials' => [
                    'username' => 'client-user',
                    'password' => 'client-secret',
                ],
            ],
            'connection' => [
                'base_url' => 'https://api.example.test/',
                'headers' => [
                    ['key' => 'X-Client', 'value' => 'plannerate', 'enabled' => true],
                    ['key' => 'X-Skip', 'value' => 'nope', 'enabled' => 'false'],
                ],
                'params' => [
                    ['key' => 'api-version', 'value' => '1.0', 'enabled' => true],
                    ['key' => 'debug', 'value' => '1', 'enabled' => false],
                ],
            ],
        ],
    ]);

    $response = (new IntegrationHttpClient)->request(
        integration: $integration,
        method: 'GET',
        endpoint: '/products',
        query: ['pagina' => 1],
    );

    expect($response->json('ok'))->toBeTrue();

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.example.test/products?api-version=1.0&pagina=1'
            && $request->hasHeader('X-Client', 'plannerate')
            && ! $request->hasHeader('X-Skip')
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-user:client-secret'));
    });
});

test('client merges configured body rows for write requests', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

    Http::fake([
        'https://api.example.test/products*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'config' => [
            'connection' => [
                'base_url' => 'https://api.example.test',
                'body' => [
                    ['key' => 'partner_key', 'value' => 'abc123', 'enabled' => true],
                    ['key' => 'disabled_key', 'value' => 'nope', 'enabled' => false],
                ],
            ],
        ],
    ]);

    (new IntegrationHttpClient)->request(
        integration: $integration,
        method: 'POST',
        endpoint: '/products',
        body: ['pagina' => '1'],
    );

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.example.test/products'
            && $request->data() === [
                'partner_key' => 'abc123',
                'pagina' => '1',
            ];
    });
});

test('client sends manual bearer token from integration config', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

    Http::fake([
        'https://api.example.test/protected*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'config' => [
            'auth' => [
                'type' => 'bearer',
                'token_mode' => 'manual',
                'credentials' => [
                    'token' => 'manual-token',
                ],
            ],
            'connection' => [
                'base_url' => 'https://api.example.test',
            ],
        ],
    ]);

    (new IntegrationHttpClient)->request(
        integration: $integration,
        method: 'GET',
        endpoint: '/protected',
    );

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer manual-token'));
});

test('client fetches bearer token from configured endpoint and caches it', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Cache::flush();

    Http::fake([
        'https://api.example.test/oauth/token*' => Http::response([
            'data' => ['access_token' => 'fetched-token'],
        ]),
        'https://api.example.test/protected*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'id' => '01k-generic-token-test',
        'config' => [
            'auth' => [
                'type' => 'bearer',
                'token_mode' => 'fetch',
                'credentials' => [
                    'username' => 'client-user',
                    'password' => 'client-secret',
                ],
                'token_request' => [
                    'method' => 'POST',
                    'path' => '/oauth/token',
                    'response_path' => 'data.access_token',
                    'username_field' => 'usuario',
                    'password_field' => 'senha',
                    'body' => [
                        ['key' => 'grant_type', 'value' => 'password', 'enabled' => true],
                    ],
                ],
            ],
            'connection' => [
                'base_url' => 'https://api.example.test',
            ],
        ],
    ]);

    $client = new IntegrationHttpClient;
    $client->request($integration, 'GET', '/protected');
    $client->request($integration, 'GET', '/protected');

    Http::assertSentCount(3);
    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.example.test/oauth/token'
            && $request->data() === [
                'grant_type' => 'password',
                'usuario' => 'client-user',
                'senha' => 'client-secret',
            ];
    });
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.example.test/protected'
        && $request->hasHeader('Authorization', 'Bearer fetched-token'));
});

test('response reader understands gescooper pagination shape', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

    $integration = new TenantIntegration(['config' => []]);
    $payload = [
        'data' => [
            ['id_produto' => 1],
            ['id_produto' => 2],
        ],
        'pagination' => [
            'current_page' => 1,
            'per_page' => 200,
            'total' => 21754,
            'last_page' => 109,
        ],
        'success' => true,
    ];

    $reader = new IntegrationResponseReader;

    expect($reader->items($integration, 'products', $payload))->toHaveCount(2)
        ->and($reader->totalPages($integration, 'products', $payload, 1))->toBe(109);
});

test('response reader understands sysmo pagination shape', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

    $integration = new TenantIntegration(['config' => []]);
    $payload = [
        'pagina' => 1,
        'dados' => [
            ['codigo' => 1],
        ],
        'total_paginas' => '30',
    ];

    $reader = new IntegrationResponseReader;

    expect($reader->items($integration, 'products', $payload))->toHaveCount(1)
        ->and($reader->totalPages($integration, 'products', $payload, 1))->toBe(30);
});

test('response reader prefers configured response paths', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

    $integration = new TenantIntegration([
        'config' => [
            'response' => [
                'products' => [
                    'items_path' => 'payload.records',
                    'pagination' => [
                        'last_page_path' => 'payload.pages.last',
                    ],
                ],
            ],
        ],
    ]);
    $payload = [
        'data' => [],
        'payload' => [
            'records' => [
                ['sku' => 'A'],
                ['sku' => 'B'],
            ],
            'pages' => [
                'last' => 4,
            ],
        ],
    ];

    $reader = new IntegrationResponseReader;

    expect($reader->items($integration, 'products', $payload))->toHaveCount(2)
        ->and($reader->totalPages($integration, 'products', $payload, 1))->toBe(4);
});

test('sysmo importer uses configured products path when present', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Carbon::setTestNow('2026-05-09 12:00:00');

    Http::fake([
        'https://sysmo.example.test/custom/products*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'config' => [
            'connection' => [
                'base_url' => 'https://sysmo.example.test',
                'body' => [
                    ['key' => 'partner_key', 'value' => 'abc123', 'enabled' => true],
                ],
            ],
            'processing' => [
                'products_initial_days' => 7,
            ],
            'paths' => [
                'products' => '/custom/products',
                'sales' => '/custom/sales',
            ],
        ],
    ]);

    $store = new Store([
        'document' => '12.345.678/0001-99',
    ]);
    $store->id = '01jts31n2rpz1tyy4n6xv4qdp1';

    (new SysmoImporter(new IntegrationHttpClient, new ImportBatchPayloadStore))->importProducts($integration, $store);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://sysmo.example.test/custom/products'
            && $request->data() === [
                'partner_key' => 'abc123',
                'empresa' => '12345678000199',
                'data_ultima_alteracao' => '2026-05-08',
                'pagina' => '1',
                'tamanho_pagina' => '500',
            ];
    });

    Carbon::setTestNow();
});

test('sysmo importer uses configured body page size', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Carbon::setTestNow('2026-05-09 12:00:00');

    Http::fake([
        'https://sysmo.example.test/custom/products*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'config' => [
            'connection' => [
                'base_url' => 'https://sysmo.example.test',
                'body' => [
                    ['key' => 'tamanho_pagina', 'value' => '1800', 'enabled' => true],
                ],
            ],
            'paths' => [
                'products' => '/custom/products',
            ],
        ],
    ]);

    (new SysmoImporter(new IntegrationHttpClient, new ImportBatchPayloadStore))->importProducts($integration);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://sysmo.example.test/custom/products'
            && $request->data()['tamanho_pagina'] === '1800';
    });

    Carbon::setTestNow();
});

test('gescooper importer sends store document as empresa query param for get requests', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Cache::flush();

    Http::fake([
        'https://gescooper.example.test/v1/Token' => Http::response([
            'token' => 'test-jwt-token',
        ], 200),
        'https://gescooper.example.test/Produtos/Produtos*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'config' => [
            'auth' => [
                'credentials' => [
                    'username' => 'GOMARKAPI',
                    'password' => 'secret',
                ],
            ],
            'connection' => [
                'base_url' => 'https://gescooper.example.test',
            ],
        ],
    ]);

    $store = new Store([
        'document' => '98.765.432/0001-88',
    ]);
    $store->id = '01jts31n2rpz1tyy4n6xv4qdp2';

    (new GescooperImporter(new IntegrationHttpClient, new ImportBatchPayloadStore))->importProducts($integration, $store);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://gescooper.example.test/Produtos/Produtos?empresa=98765432000188&pagina=1&registros_por_pagina=200&api-version=1.0';
    });

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://gescooper.example.test/Produtos/Produtos?empresa=98765432000188&pagina=1&registros_por_pagina=200&api-version=1.0'
            && $request->hasHeader('Authorization', 'Bearer test-jwt-token');
    });
});

test('gescooper importer uses configured params page size', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Cache::flush();

    Http::fake([
        'https://gescooper.example.test/v1/Token' => Http::response([
            'token' => 'test-jwt-token',
        ], 200),
        'https://gescooper.example.test/Produtos/Produtos*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'config' => [
            'auth' => [
                'credentials' => [
                    'username' => 'GOMARKAPI',
                    'password' => 'secret',
                ],
            ],
            'connection' => [
                'base_url' => 'https://gescooper.example.test',
                'params' => [
                    ['key' => 'registros_por_pagina', 'value' => '450', 'enabled' => true],
                ],
            ],
        ],
    ]);

    (new GescooperImporter(new IntegrationHttpClient, new ImportBatchPayloadStore))->importProducts($integration);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://gescooper.example.test/Produtos/Produtos?registros_por_pagina=450&pagina=1&api-version=1.0';
    });
});

test('gescooper importer caches token between requests', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Cache::flush();

    Http::fake([
        'https://gescooper.example.test/v1/Token' => Http::response([
            'token' => 'test-jwt-token',
        ], 200),
        'https://gescooper.example.test/Produtos/Produtos*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'id' => '01k-token-cache-test',
        'config' => [
            'auth' => [
                'credentials' => [
                    'username' => 'GOMARKAPI',
                    'password' => 'secret',
                ],
            ],
            'connection' => [
                'base_url' => 'https://gescooper.example.test',
            ],
        ],
    ]);

    $importer = new GescooperImporter(new IntegrationHttpClient, new ImportBatchPayloadStore);
    $importer->importProducts($integration);
    $importer->importProducts($integration);

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://gescooper.example.test/v1/Token');
});

test('sysmo importer paginates products when total_paginas is returned', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Carbon::setTestNow('2026-05-09 12:00:00');

    Http::fake([
        'https://sysmo.example.test/custom/products' => Http::sequence()
            ->push([
                'pagina' => 1,
                'total_paginas' => 2,
                'dados' => [],
            ], 200)
            ->push([
                'pagina' => 2,
                'total_paginas' => 2,
                'dados' => [],
            ], 200),
    ]);

    $integration = new TenantIntegration([
        'config' => [
            'connection' => [
                'base_url' => 'https://sysmo.example.test',
                'body' => [
                    ['key' => 'partner_key', 'value' => 'abc123', 'enabled' => true],
                ],
            ],
            'processing' => [
                'products_initial_days' => 7,
            ],
            'paths' => [
                'products' => '/custom/products',
            ],
        ],
    ]);

    (new SysmoImporter(new IntegrationHttpClient, new ImportBatchPayloadStore))->importProducts($integration);

    Http::assertSentCount(2);
    Carbon::setTestNow();
});

test('gescooper importer paginates products when last_page is returned', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Cache::flush();

    Http::fake([
        'https://gescooper.example.test/v1/Token' => Http::response([
            'token' => 'test-jwt-token',
        ], 200),
        'https://gescooper.example.test/Produtos/Produtos*' => Http::sequence()
            ->push([
                'data' => [],
                'last_page' => 2,
            ], 200)
            ->push([
                'data' => [],
                'last_page' => 2,
            ], 200),
    ]);

    $integration = new TenantIntegration([
        'config' => [
            'auth' => [
                'credentials' => [
                    'username' => 'GOMARKAPI',
                    'password' => 'secret',
                ],
            ],
            'connection' => [
                'base_url' => 'https://gescooper.example.test',
            ],
        ],
    ]);

    (new GescooperImporter(new IntegrationHttpClient, new ImportBatchPayloadStore))->importProducts($integration);

    Http::assertSentCount(3);
});

test('gescooper importer paginates products when pagination.last_page is returned', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Cache::flush();

    Http::fake([
        'https://gescooper.example.test/v1/Token' => Http::response([
            'token' => 'test-jwt-token',
        ], 200),
        'https://gescooper.example.test/Produtos/Produtos*' => Http::sequence()
            ->push([
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 2,
                ],
            ], 200)
            ->push([
                'data' => [],
                'pagination' => [
                    'current_page' => 2,
                    'last_page' => 2,
                ],
            ], 200),
    ]);

    $integration = new TenantIntegration([
        'config' => [
            'auth' => [
                'credentials' => [
                    'username' => 'GOMARKAPI',
                    'password' => 'secret',
                ],
            ],
            'connection' => [
                'base_url' => 'https://gescooper.example.test',
            ],
        ],
    ]);

    (new GescooperImporter(new IntegrationHttpClient, new ImportBatchPayloadStore))->importProducts($integration);

    Http::assertSentCount(3);
});
