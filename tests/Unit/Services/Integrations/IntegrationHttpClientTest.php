<?php

use App\Jobs\Integrations\Imports\FetchIntegrationSalesDayJob;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Http\IntegrationHttpClient;
use App\Services\Integrations\Importers\GenericIntegrationImporter;
use App\Services\Integrations\Importers\IntegrationImporter;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use App\Services\Integrations\Support\ImportBatchPayloadStore;
use App\Services\Integrations\Support\IntegrationResponseReader;
use App\Services\Integrations\Support\ResolvedIntegrationConfig;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
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

test('client avoids duplicating base url last segment in request endpoint', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

    Http::fake([
        'https://web.example.test/GesCooper/Cadastro/Api/Produtos/Produtos*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'config' => [
            'connection' => [
                'base_url' => 'https://web.example.test/GesCooper/Cadastro/Api/',
            ],
        ],
    ]);

    (new IntegrationHttpClient)->request(
        integration: $integration,
        method: 'GET',
        endpoint: '/Api/Produtos/Produtos',
    );

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://web.example.test/GesCooper/Cadastro/Api/Produtos/Produtos');
});

test('client avoids duplicating base url last segment in token endpoint', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Cache::flush();

    Http::fake([
        'https://web.example.test/GesCooper/Cadastro/Api/Token' => Http::response([
            'token' => 'fetched-token',
        ]),
        'https://web.example.test/GesCooper/Cadastro/Api/Produtos/Produtos' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'id' => '01k-duplicate-api-token-test',
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
                    'path' => '/Api/Token',
                    'response_path' => 'token',
                    'username_field' => 'usuario',
                    'password_field' => 'senha',
                ],
            ],
            'connection' => [
                'base_url' => 'https://web.example.test/GesCooper/Cadastro/Api/',
            ],
        ],
    ]);

    (new IntegrationHttpClient)->request(
        integration: $integration,
        method: 'GET',
        endpoint: '/Api/Produtos/Produtos',
    );

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://web.example.test/GesCooper/Cadastro/Api/Token');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://web.example.test/GesCooper/Cadastro/Api/Produtos/Produtos');
});

test('response reader understands query-api pagination shape', function (): void {
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

test('response reader understands body-api pagination shape', function (): void {
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

test('response reader falls back to generic paths without provider config', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

    $integration = new TenantIntegration([
        'integration_type' => 'unknown',
        'config' => [],
    ]);
    $payload = [
        'data' => [
            ['sku' => 'A'],
            ['sku' => 'B'],
        ],
        'pagination' => [
            'last_page' => 4,
        ],
        'payload' => [
            'records' => [
                ['sku' => 'ignored'],
            ],
            'pages' => [
                'last' => 9,
            ],
        ],
    ];

    $reader = new IntegrationResponseReader;

    expect($reader->items($integration, 'products', $payload))->toHaveCount(2)
        ->and($reader->totalPages($integration, 'products', $payload, 1))->toBe(4);
});

test('response reader uses provider config by integration type', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

    $integration = new TenantIntegration([
        'integration_type' => 'acme',
        'config' => [
            'response' => [
                'items_path' => 'payload.items',
                'pagination' => [
                    'last_page_path' => 'payload.pagination.pages',
                ],
            ],
        ],
    ]);
    $payload = [
        'payload' => [
            'items' => [
                ['sku' => 'A'],
            ],
            'pagination' => [
                'pages' => 7,
            ],
        ],
    ];

    $reader = new IntegrationResponseReader;

    expect($reader->items($integration, 'products', $payload))->toHaveCount(1)
        ->and($reader->totalPages($integration, 'products', $payload, 1))->toBe(7);
});

test('response reader uses tenant response config from resolved configuration', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

    $integration = new TenantIntegration([
        'integration_type' => 'acme',
        'config' => [
            'response' => [
                'products' => [
                    'items_path' => 'custom.records',
                    'pagination' => [
                        'last_page_path' => 'custom.pages.last',
                    ],
                ],
            ],
        ],
    ]);
    $payload = [
        'payload' => [
            'items' => [],
            'pagination' => [
                'pages' => 7,
            ],
        ],
        'custom' => [
            'records' => [
                ['sku' => 'B'],
                ['sku' => 'C'],
            ],
            'pages' => [
                'last' => 3,
            ],
        ],
    ];

    $reader = new IntegrationResponseReader;

    expect($reader->items($integration, 'products', $payload))->toHaveCount(2)
        ->and($reader->totalPages($integration, 'products', $payload, 1))->toBe(3);
});

test('body-api importer uses configured products path when present', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Carbon::setTestNow('2026-05-09 12:00:00');

    Http::fake([
        'https://body-api.example.test/custom/products*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'integration_type' => 'body-api',
        'config' => [
            'connection' => [
                'base_url' => 'https://body-api.example.test',
                'body' => [
                    ['key' => 'partner_key', 'value' => 'abc123', 'enabled' => true],
                ],
            ],
            'processing' => [
                'products_initial_days' => 7,
            ],
            'requests' => bodyProductsRequests('/custom/products'),
        ],
    ]);

    $store = new Store([
        'document' => '12.345.678/0001-99',
    ]);
    $store->id = '01jts31n2rpz1tyy4n6xv4qdp1';

    genericIntegrationImporter()->importResource($integration, 'products', 'products', $store);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://body-api.example.test/custom/products'
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

test('body-api importer uses configured body page size', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Carbon::setTestNow('2026-05-09 12:00:00');

    Http::fake([
        'https://body-api.example.test/custom/products*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'integration_type' => 'body-api',
        'config' => [
            'connection' => [
                'base_url' => 'https://body-api.example.test',
                'body' => [
                    ['key' => 'tamanho_pagina', 'value' => '1800', 'enabled' => true],
                ],
            ],
            'requests' => bodyProductsRequests('/custom/products'),
        ],
    ]);

    genericIntegrationImporter()->importResource($integration, 'products', 'products', integrationTestStore());

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://body-api.example.test/custom/products'
            && $request->data()['tamanho_pagina'] === '1800';
    });

    Carbon::setTestNow();
});

test('sales importer sends yesterday to today when tenant already has sales', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Carbon::setTestNow('2026-05-09 12:00:00');
    Bus::fake();
    Http::fake([
        'https://body-api.example.test/hubvendas.vendas_produtos*' => Http::response([
            'dados' => [],
            'total_paginas' => 1,
        ]),
    ]);

    $integration = new TenantIntegration([
        'integration_type' => 'sales-date-api',
        'config' => [
            'connection' => [
                'base_url' => 'https://body-api.example.test',
            ],
            'processing' => [
                'sales_initial_days' => 2,
            ],
            'requests' => [
                'method' => 'POST',
                'payload' => 'body',
                'paths' => [
                    'sales' => [
                        'target_table' => 'sales',
                        'fallback_path' => '/hubvendas.vendas_produtos',
                        'dispatch_by_day' => false,
                        'date_strategy' => 'range_incremental',
                        'page_field' => 'pagina',
                        'page_value_type' => 'string',
                        'page_size_field' => 'tamanho_pagina',
                        'default_page_size' => 5000,
                        'max_page_size' => 5000,
                        'date_fields' => [
                            'start' => 'data_inicial',
                            'end' => 'data_final',
                        ],
                    ],
                ],
            ],
        ],
    ]);
    $integration->id = '01k-sales-daily-fetch-test';
    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->shouldReceive('execute')->once()->andReturn(true);
    $integration->setRelation('tenant', $tenant);

    genericIntegrationImporter()->importResource($integration, 'sales', 'sales', integrationTestStore());

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $payload = [...$query, ...$request->data()];

        return str_contains($request->url(), 'hubvendas.vendas_produtos')
            && ($payload['data_inicial'] ?? null) === '2026-05-08'
            && ($payload['data_final'] ?? null) === '2026-05-09';
    });
    Bus::assertNotDispatched(FetchIntegrationSalesDayJob::class);

    Carbon::setTestNow();
});

test('sales importer sends initial sales period when tenant has no sales', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Carbon::setTestNow('2026-05-09 12:00:00');
    Bus::fake();
    Http::fake([
        'https://body-api.example.test/hubvendas.vendas_produtos*' => Http::response([
            'dados' => [],
            'total_paginas' => 1,
        ]),
    ]);

    $integration = new TenantIntegration([
        'integration_type' => 'sales-date-api',
        'config' => [
            'connection' => [
                'base_url' => 'https://body-api.example.test',
            ],
            'processing' => [
                'sales_initial_days' => 5,
            ],
            'requests' => [
                'method' => 'POST',
                'payload' => 'body',
                'paths' => [
                    'sales' => [
                        'target_table' => 'sales',
                        'fallback_path' => '/hubvendas.vendas_produtos',
                        'dispatch_by_day' => false,
                        'date_strategy' => 'range_incremental',
                        'date_fields' => [
                            'start' => 'data_inicial',
                            'end' => 'data_final',
                        ],
                    ],
                ],
            ],
        ],
    ]);
    $integration->id = '01k-sales-initial-fetch-test';
    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->shouldReceive('execute')->once()->andReturn(false);
    $integration->setRelation('tenant', $tenant);

    genericIntegrationImporter()->importResource($integration, 'sales', 'sales', integrationTestStore());

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $payload = [...$query, ...$request->data()];

        return str_contains($request->url(), 'hubvendas.vendas_produtos')
            && ($payload['data_inicial'] ?? null) === '2026-05-05'
            && ($payload['data_final'] ?? null) === '2026-05-09';
    });
    Bus::assertNotDispatched(FetchIntegrationSalesDayJob::class);

    Carbon::setTestNow();
});

test('query-api importer sends store document as empresa query param for get requests', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Cache::flush();

    Http::fake([
        'https://query-api.example.test/v1/Token' => Http::response([
            'token' => 'test-jwt-token',
        ], 200),
        'https://query-api.example.test/Produtos/Produtos*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'integration_type' => 'query-api',
        'config' => [
            'auth' => [
                'type' => 'bearer_fetch',
                'token_request' => [
                    'path' => '/v1/Token',
                ],
                'credentials' => [
                    'username' => 'GOMARKAPI',
                    'password' => 'secret',
                ],
            ],
            'connection' => [
                'base_url' => 'https://query-api.example.test',
            ],
            'requests' => queryProductsRequests(),
        ],
    ]);

    $store = new Store([
        'document' => '98.765.432/0001-88',
    ]);
    $store->id = '01jts31n2rpz1tyy4n6xv4qdp2';

    genericIntegrationImporter()->importResource($integration, 'products', 'products', $store);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://query-api.example.test/Produtos/Produtos?empresa=98765432000188&pagina=1&registros_por_pagina=200&api-version=1.0';
    });

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://query-api.example.test/Produtos/Produtos?empresa=98765432000188&pagina=1&registros_por_pagina=200&api-version=1.0'
            && $request->hasHeader('Authorization', 'Bearer test-jwt-token');
    });
});

test('query-api importer uses configured params page size', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Cache::flush();

    Http::fake([
        'https://query-api.example.test/v1/Token' => Http::response([
            'token' => 'test-jwt-token',
        ], 200),
        'https://query-api.example.test/Produtos/Produtos*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'integration_type' => 'query-api',
        'config' => [
            'auth' => [
                'type' => 'bearer_fetch',
                'token_request' => [
                    'path' => '/v1/Token',
                ],
                'credentials' => [
                    'username' => 'GOMARKAPI',
                    'password' => 'secret',
                ],
            ],
            'connection' => [
                'base_url' => 'https://query-api.example.test',
                'params' => [
                    ['key' => 'registros_por_pagina', 'value' => '450', 'enabled' => true],
                ],
            ],
            'requests' => queryProductsRequests(),
        ],
    ]);

    genericIntegrationImporter()->importResource($integration, 'products', 'products', integrationTestStore());

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://query-api.example.test/Produtos/Produtos?registros_por_pagina=450&pagina=1&api-version=1.0';
    });
});

test('query-api importer caches token between requests', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Cache::flush();

    Http::fake([
        'https://query-api.example.test/v1/Token' => Http::response([
            'token' => 'test-jwt-token',
        ], 200),
        'https://query-api.example.test/Produtos/Produtos*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'id' => '01k-token-cache-test',
        'integration_type' => 'query-api',
        'config' => [
            'auth' => [
                'type' => 'bearer_fetch',
                'token_request' => [
                    'path' => '/v1/Token',
                ],
                'credentials' => [
                    'username' => 'GOMARKAPI',
                    'password' => 'secret',
                ],
            ],
            'connection' => [
                'base_url' => 'https://query-api.example.test',
            ],
            'requests' => queryProductsRequests(),
        ],
    ]);

    $importer = genericIntegrationImporter();
    $importer->importResource($integration, 'products', 'products', integrationTestStore());
    $importer->importResource($integration, 'products', 'products', integrationTestStore());

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://query-api.example.test/v1/Token');
});

test('body-api importer paginates products when total_paginas is returned', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Carbon::setTestNow('2026-05-09 12:00:00');

    Http::fake([
        'https://body-api.example.test/custom/products' => Http::sequence()
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
        'integration_type' => 'body-api',
        'config' => [
            'connection' => [
                'base_url' => 'https://body-api.example.test',
                'body' => [
                    ['key' => 'partner_key', 'value' => 'abc123', 'enabled' => true],
                ],
            ],
            'processing' => [
                'products_initial_days' => 7,
            ],
            'requests' => bodyProductsRequests('/custom/products'),
        ],
    ]);

    genericIntegrationImporter()->importResource($integration, 'products', 'products', integrationTestStore());

    Http::assertSentCount(2);
    Carbon::setTestNow();
});

test('query-api importer paginates products when last_page is returned', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Cache::flush();

    Http::fake([
        'https://query-api.example.test/v1/Token' => Http::response([
            'token' => 'test-jwt-token',
        ], 200),
        'https://query-api.example.test/Produtos/Produtos*' => Http::sequence()
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
        'integration_type' => 'query-api',
        'config' => [
            'auth' => [
                'type' => 'bearer_fetch',
                'token_request' => [
                    'path' => '/v1/Token',
                ],
                'credentials' => [
                    'username' => 'GOMARKAPI',
                    'password' => 'secret',
                ],
            ],
            'connection' => [
                'base_url' => 'https://query-api.example.test',
            ],
            'requests' => queryProductsRequests(),
        ],
    ]);

    genericIntegrationImporter()->importResource($integration, 'products', 'products', integrationTestStore());

    Http::assertSentCount(3);
});

test('query-api importer paginates products when pagination.last_page is returned', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Cache::flush();

    Http::fake([
        'https://query-api.example.test/v1/Token' => Http::response([
            'token' => 'test-jwt-token',
        ], 200),
        'https://query-api.example.test/Produtos/Produtos*' => Http::sequence()
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
        'integration_type' => 'query-api',
        'config' => [
            'auth' => [
                'type' => 'bearer_fetch',
                'token_request' => [
                    'path' => '/v1/Token',
                ],
                'credentials' => [
                    'username' => 'GOMARKAPI',
                    'password' => 'secret',
                ],
            ],
            'connection' => [
                'base_url' => 'https://query-api.example.test',
            ],
            'requests' => queryProductsRequests(),
        ],
    ]);

    genericIntegrationImporter()->importResource($integration, 'products', 'products', integrationTestStore());

    Http::assertSentCount(3);
});

function resolvedConfigForHttpClientTest(TenantIntegration $integration): ResolvedIntegrationConfig
{
    return app(ResolvedIntegrationConfigResolver::class)->resolve($integration);
}

function integrationImporter(): IntegrationImporter
{
    return new IntegrationImporter(genericIntegrationImporter());
}

function genericIntegrationImporter(): GenericIntegrationImporter
{
    return new GenericIntegrationImporter(
        new IntegrationHttpClient,
        new ImportBatchPayloadStore,
        new IntegrationResponseReader,
    );
}

function integrationTestStore(): Store
{
    $store = new Store;
    $store->id = '01jts31n2rpz1tyy4n6xv4qdp0';

    return $store;
}

/**
 * @return array<string, mixed>
 */
function bodyProductsRequests(string $path): array
{
    return [
        'method' => 'POST',
        'payload' => 'body',
        'paths' => [
            'products' => [
                'target_table' => 'products',
                'fallback_path' => $path,
                'store_document_field' => 'empresa',
                'initial_days' => 2,
                'date_fields' => [
                    'changed_since' => 'data_ultima_alteracao',
                ],
                'page_field' => 'pagina',
                'page_value_type' => 'string',
                'page_size_field' => 'tamanho_pagina',
                'default_page_size' => 500,
                'max_page_size' => 500,
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function queryProductsRequests(): array
{
    return [
        'method' => 'GET',
        'payload' => 'query',
        'paths' => [
            'products' => [
                'target_table' => 'products',
                'fallback_path' => '/Produtos/Produtos',
                'store_document_field' => 'empresa',
                'page_field' => 'pagina',
                'page_size_field' => 'registros_por_pagina',
                'default_page_size' => 200,
                'max_page_size' => 200,
                'fixed_query' => [
                    'api-version' => '1.0',
                ],
            ],
        ],
    ];
}
