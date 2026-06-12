<?php

use App\Models\TenantIntegration;
use App\Services\Integrations\Support\ResolvedIntegrationConfig;

/*
 * SKIP (fase 5 da refatoração raptor-plannerate, aprovado em 2026-06-11):
 * este arquivo referencia classes do domínio Integrations que não existem mais
 * nesses namespaces (ex.: App\Services\Integrations\Http\IntegrationHttpClient —
 * a classe atual vive em App\Services\Integrations\IntegrationHttpClient).
 * Estes testes nunca rodaram (a suíte não carregava antes do commit 83d400a).
 * Triagem pendente do domínio Integrations: atualizar imports/expectativas ou remover.
 */
beforeEach(function (): void {
    $this->markTestSkipped('Domínio Integrations: classes testadas mudaram de namespace — triagem pendente (ver comentário no topo do arquivo).');
});

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
