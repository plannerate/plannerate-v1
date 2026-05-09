<?php

namespace App\Services\Integrations\Importers;

use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\Http\IntegrationHttpClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SysmoImporter implements ClientApiImporter
{
    public function __construct(
        private readonly IntegrationHttpClient $httpClient,
    ) {}

    public function importSales(TenantIntegration $integration, ?Store $store = null): void
    {
        $response = $this->httpClient->request(
            integration: $integration,
            method: 'POST',
            endpoint: $this->path($integration, 'sales', '/sysmo-integrador-api/api/integradorService/hubvendas.vendas_produtos'),
            body: [
                ...$this->connectionBody($integration),
                ...$this->storeBody($store),
                ...$this->salesDatePayload($integration),
                'pagina' => '1',
            ],
        );

        Log::info('Sysmo sales import request completed.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
            'store_id' => $store?->id,
            'status' => $response->status(),
        ]);
    }

    public function importProducts(TenantIntegration $integration, ?Store $store = null): void
    {
        $response = $this->httpClient->request(
            integration: $integration,
            method: 'POST',
            endpoint: $this->path($integration, 'products', '/sysmo-integrador-api/api/integradorService/hubprodutos.listar_produtos'),
            body: [
                ...$this->connectionBody($integration),
                ...$this->storeBody($store),
                'pagina' => '1',
            ],
        );

        Log::info('Sysmo products import request completed.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
            'store_id' => $store?->id,
            'status' => $response->status(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function connectionBody(TenantIntegration $integration): array
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $connection = is_array($config['connection'] ?? null) ? $config['connection'] : [];
        $bodyRows = is_array($connection['body'] ?? null) ? $connection['body'] : [];

        $body = [];
        foreach ($bodyRows as $row) {
            if (! is_array($row) || ! $this->rowIsEnabled($row)) {
                continue;
            }

            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $body[$key] = (string) ($row['value'] ?? '');
        }

        return $body;
    }

    /**
     * @return array{empresa: string}|array{}
     */
    private function storeBody(?Store $store): array
    {
        $document = trim((string) $store?->document);

        return $document !== '' ? ['empresa' => $document] : [];
    }

    private function path(TenantIntegration $integration, string $key, string $fallback): string
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $paths = is_array($config['paths'] ?? null) ? $config['paths'] : [];
        $path = trim((string) ($paths[$key] ?? ''));

        return $path !== '' ? $path : $fallback;
    }

    /**
     * @return array{data_inicial: string, data_final: string}
     */
    private function salesDatePayload(TenantIntegration $integration): array
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : [];
        $days = max(1, (int) ($processing['sales_initial_days'] ?? 120));
        $endDate = Carbon::yesterday();

        return [
            'data_inicial' => $endDate->copy()->subDays($days - 1)->toDateString(),
            'data_final' => $endDate->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowIsEnabled(array $row): bool
    {
        if (! array_key_exists('enabled', $row)) {
            return true;
        }

        $enabled = $row['enabled'];
        if (is_bool($enabled)) {
            return $enabled;
        }

        if (is_string($enabled) || is_int($enabled)) {
            return filter_var($enabled, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
        }

        return true;
    }
}
