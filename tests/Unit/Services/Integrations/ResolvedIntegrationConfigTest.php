<?php

use App\Models\TenantIntegration;
use App\Services\Integrations\Support\ResolvedIntegrationConfig;

test('resolved integration config reads resource requests from paths', function (): void {
    $config = new ResolvedIntegrationConfig(
        integration: new TenantIntegration(['integration_type' => 'sysmo']),
        apiConfig: [
            'requests' => [
                'method' => 'POST',
                'payload' => 'body',
                'paths' => [
                    'products' => [
                        'target_table' => 'products',
                        'fallback_path' => '/hubprodutos.listar_produtos',
                        'field_map' => [
                            [
                                'target' => 'codigo_erp',
                                'source' => 'produto',
                                'transforms' => ['string'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        tenantConfig: [],
    );

    expect($config->resourceRequests())->toHaveKey('products')
        ->and($config->request('products')['method'])->toBe('POST')
        ->and($config->path('products'))->toBe('/hubprodutos.listar_produtos')
        ->and($config->fieldMapRows('products')[0]['target'])->toBe('codigo_erp');
});

test('resolved integration config keeps legacy top level resource requests compatible', function (): void {
    $config = new ResolvedIntegrationConfig(
        integration: new TenantIntegration(['integration_type' => 'legacy']),
        apiConfig: [
            'requests' => [
                'method' => 'GET',
                'products' => [
                    'target_table' => 'products',
                    'fallback_path' => '/products',
                ],
            ],
        ],
        tenantConfig: [],
    );

    expect($config->resourceRequests())->toHaveKey('products')
        ->and($config->request('products')['method'])->toBe('GET')
        ->and($config->path('products'))->toBe('/products');
});

test('path is not enabled when endpoint is blank', function (): void {
    $config = new ResolvedIntegrationConfig(
        integration: new TenantIntegration(['integration_type' => 'blank-path']),
        apiConfig: [
            'requests' => [
                'paths' => [
                    'products' => [
                        'target_table' => 'products',
                        'fallback_path' => '',
                    ],
                ],
            ],
        ],
        tenantConfig: [],
    );

    expect($config->pathIsEnabled('products'))->toBeFalse();
});
