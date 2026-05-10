<?php

namespace App\Services\Integrations\Importers;

use App\Jobs\Integrations\Imports\ProcessImportedProductsBatchJob;
use App\Jobs\Integrations\Imports\ProcessImportedSalesBatchJob;
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
        $endpoint = $this->path($integration, 'sales', '/sysmo-integrador-api/api/integradorService/hubvendas.vendas_produtos');
        $body = [
            ...$this->connectionBody($integration),
            ...$this->storeBody($store),
            ...$this->salesDatePayload($integration),
            'pagina' => '1',
        ];

        $this->logRequestPayload('sales', $integration, $store, $endpoint, $body);

        $response = $this->httpClient->request(
            integration: $integration,
            method: 'POST',
            endpoint: $endpoint,
            body: $body,
        );

        $payload = $response->json();
        $items = $this->resolveItems(is_array($payload) ? $payload : []);

        ProcessImportedSalesBatchJob::dispatch(
            integrationId: (string) $integration->id,
            provider: 'sysmo',
            items: $items,
            storeId: $store?->id,
            storeDocument: $store?->document,
        );

        Log::info('Sysmo sales import request completed.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
            'store_id' => $store?->id,
            'items' => count($items),
            'status' => $response->status(),
        ]);
    }

    public function importProducts(TenantIntegration $integration, ?Store $store = null): void
    {
        $endpoint = $this->path($integration, 'products', '/sysmo-integrador-api/api/integradorService/hubprodutos.listar_produtos');
        $currentPage = 1;
        $totalPages = 1;

        do {
            $body = [
                ...$this->connectionBody($integration),
                ...$this->storeBody($store),
                ...$this->productsDatePayload($integration),
                'pagina' => (string) $currentPage,
            ];

            $this->logRequestPayload('products', $integration, $store, $endpoint, $body);

            $response = $this->httpClient->request(
                integration: $integration,
                method: 'POST',
                endpoint: $endpoint,
                body: $body,
            );

            $payload = $response->json();
            $totalPages = $this->resolveTotalPages(is_array($payload) ? $payload : [], $currentPage);
            $items = $this->resolveItems(is_array($payload) ? $payload : []);

            ProcessImportedProductsBatchJob::dispatch(
                integrationId: (string) $integration->id,
                provider: 'sysmo',
                items: $items,
                storeId: $store?->id,
            );

            Log::info('Sysmo products import page fetched.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'store_id' => $store?->id,
                'page' => $currentPage,
                'total_pages' => $totalPages,
                'items' => count($items),
                'status' => $response->status(),
            ]);

            $currentPage++;
        } while ($currentPage <= $totalPages);
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
     * @param  array<string, string>  $body
     */
    private function logRequestPayload(
        string $resource,
        TenantIntegration $integration,
        ?Store $store,
        string $endpoint,
        array $body,
    ): void {
        Log::info('Sysmo import request payload.', [
            'resource' => $resource,
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
            'store_id' => $store?->id,
            'store_document' => $store?->document,
            'store_document_normalized' => $this->storeDocument($store),
            'method' => 'POST',
            'endpoint' => $endpoint,
            'body' => $body,
        ]);
    }

    /**
     * @return array{empresa: string}|array{}
     */
    private function storeBody(?Store $store): array
    {
        $document = $this->storeDocument($store);

        return $document !== '' ? ['empresa' => $document] : [];
    }

    private function path(TenantIntegration $integration, string $key, string $fallback): string
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $paths = is_array($config['paths'] ?? null) ? $config['paths'] : [];
        $path = trim((string) ($paths[$key] ?? ''));

        return $path !== '' ? $path : $fallback;
    }

    private function storeDocument(?Store $store): string
    {
        return preg_replace('/\D+/', '', (string) $store?->document) ?? '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveTotalPages(array $payload, int $currentPage): int
    {
        $candidates = [
            $payload['total_paginas'] ?? null,
            $payload['totalPaginas'] ?? null,
            $payload['total_pages'] ?? null,
            is_array($payload['pagination'] ?? null) ? ($payload['pagination']['total_pages'] ?? null) : null,
            is_array($payload['meta'] ?? null) ? ($payload['meta']['last_page'] ?? null) : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                return max($currentPage, (int) $candidate);
            }
        }

        return $currentPage;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveItemCount(array $payload): int
    {
        $items = $payload['dados'] ?? null;

        return is_array($items) ? count($items) : 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function resolveItems(array $payload): array
    {
        $items = $payload['dados'] ?? null;
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @return array{data_ultima_alteracao: string}
     */
    private function productsDatePayload(TenantIntegration $integration): array
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : [];
        $days = max(1, (int) ($processing['products_initial_days'] ?? 120));

        return [
            'data_ultima_alteracao' => Carbon::yesterday()->subDays($days - 1)->toDateString(),
        ];
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
