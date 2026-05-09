<?php

namespace App\Services\Integrations\Importers;

use App\Models\TenantIntegration;
use App\Services\Integrations\Http\IntegrationHttpClient;
use Illuminate\Support\Facades\Log;

class GescooperImporter implements ClientApiImporter
{
    public function __construct(
        private readonly IntegrationHttpClient $httpClient,
    ) {}

    public function importSales(TenantIntegration $integration): void
    {
        $path = $this->path($integration, 'sales', '');

        if ($path !== '') {
            $response = $this->httpClient->request(
                integration: $integration,
                method: 'GET',
                endpoint: $path,
            );

            Log::info('GesCooper sales import request completed.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'status' => $response->status(),
            ]);

            return;
        }

        Log::info('GesCooper sales import skipped: endpoint ainda não definido.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
        ]);
    }

    public function importProducts(TenantIntegration $integration): void
    {
        $response = $this->httpClient->request(
            integration: $integration,
            method: 'GET',
            endpoint: $this->path($integration, 'products', '/Produtos/Produtos'),
            query: [
                'pagina' => 1,
                'registros_por_pagina' => 1000,
                'api-version' => '1.0',
            ],
        );

        Log::info('GesCooper products import request completed.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
            'status' => $response->status(),
        ]);
    }

    private function path(TenantIntegration $integration, string $key, string $fallback): string
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $paths = is_array($config['paths'] ?? null) ? $config['paths'] : [];
        $path = trim((string) ($paths[$key] ?? ''));

        return $path !== '' ? $path : $fallback;
    }
}
