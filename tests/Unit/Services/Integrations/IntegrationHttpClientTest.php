<?php

use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\Http\IntegrationHttpClient;
use App\Services\Integrations\Importers\GescooperImporter;
use App\Services\Integrations\Importers\SysmoImporter;
use Illuminate\Http\Client\Request;
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

test('sysmo importer uses configured products path when present', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

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
            'paths' => [
                'products' => '/custom/products',
                'sales' => '/custom/sales',
            ],
        ],
    ]);

    $store = new Store([
        'document' => '12345678000199',
    ]);
    $store->id = '01jts31n2rpz1tyy4n6xv4qdp1';

    (new SysmoImporter(new IntegrationHttpClient))->importProducts($integration, $store);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://sysmo.example.test/custom/products'
            && $request->data() === [
                'partner_key' => 'abc123',
                'empresa' => '12345678000199',
                'pagina' => '1',
            ];
    });
});

test('gescooper importer sends store document as empresa query param for get requests', function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

    Http::fake([
        'https://gescooper.example.test/Produtos/Produtos*' => Http::response(['ok' => true]),
    ]);

    $integration = new TenantIntegration([
        'config' => [
            'connection' => [
                'base_url' => 'https://gescooper.example.test',
            ],
        ],
    ]);

    $store = new Store([
        'document' => '98765432000188',
    ]);
    $store->id = '01jts31n2rpz1tyy4n6xv4qdp2';

    (new GescooperImporter(new IntegrationHttpClient))->importProducts($integration, $store);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://gescooper.example.test/Produtos/Produtos?empresa=98765432000188&pagina=1&registros_por_pagina=1000&api-version=1.0';
    });
});
