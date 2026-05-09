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
            endpoint: '/Produtos/Produtos',
            query: [
                'pagina' => 1,
                'registros_por_pagina' => 200,
                'api-version' => '1.0',
            ],
        );

        Log::info('GesCooper products import request completed.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
            'status' => $response->status(),
        ]);
    }
}
