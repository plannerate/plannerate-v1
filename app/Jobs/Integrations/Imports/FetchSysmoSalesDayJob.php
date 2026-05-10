<?php

namespace App\Jobs\Integrations\Imports;

use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\Http\IntegrationHttpClient;
use App\Services\Integrations\Support\ImportBatchPayloadStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class FetchSysmoSalesDayJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(
        public string $integrationId,
        public string $date,
        public ?string $storeId = null,
        public ?string $storeDocument = null,
    ) {
        $this->onQueue('imports');
    }

    public function handle(
        IntegrationHttpClient $httpClient,
        ImportBatchPayloadStore $importBatchPayloadStore,
    ): void {
        $integration = TenantIntegration::query()
            ->with('tenant')
            ->whereKey($this->integrationId)
            ->where('is_active', true)
            ->first();

        if (! $integration instanceof TenantIntegration) {
            Log::warning('Fetch diário de vendas Sysmo ignorado: integração ativa não encontrada.', [
                'integration_id' => $this->integrationId,
                'date' => $this->date,
                'store_id' => $this->storeId,
            ]);

            return;
        }

        $store = null;
        if ((is_string($this->storeId) && $this->storeId !== '') || (is_string($this->storeDocument) && $this->storeDocument !== '')) {
            $store = new Store;
            $store->id = $this->storeId;
            $store->document = $this->storeDocument;
        }

        $endpoint = $this->path($integration, 'sales', '/sysmo-integrador-api/api/integradorService/hubvendas.vendas_produtos');
        $currentPage = 1;
        $totalPages = 1;

        do {
            $body = [
                ...$this->connectionBody($integration),
                ...$this->storeBody($store),
                'data_inicial' => $this->date,
                'data_final' => $this->date,
                'pagina' => (string) $currentPage,
            ];

            Log::info('Sysmo sales day fetch request payload.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'store_id' => $store?->id,
                'store_document' => $store?->document,
                'date' => $this->date,
                'method' => 'POST',
                'endpoint' => $endpoint,
                'body' => $body,
            ]);

            try {
                $response = $httpClient->request(
                    integration: $integration,
                    method: 'POST',
                    endpoint: $endpoint,
                    body: $body,
                );
            } catch (RequestException $exception) {
                $status = $exception->response?->status();
                if (in_array($status, [404, 501], true)) {
                    Log::warning('Sysmo sales day fetch skipped due to provider response.', [
                        'integration_id' => (string) $integration->id,
                        'tenant_id' => (string) $integration->tenant_id,
                        'store_id' => $store?->id,
                        'date' => $this->date,
                        'status' => $status,
                    ]);

                    return;
                }

                throw $exception;
            }

            $payload = $response->json();
            $totalPages = $this->resolveTotalPages(is_array($payload) ? $payload : [], $currentPage);
            $items = $this->resolveItems(is_array($payload) ? $payload : []);

            $payloadKey = $importBatchPayloadStore->put((string) $integration->id, 'sales', $items);
            ProcessImportedSalesBatchJob::dispatch(
                integrationId: (string) $integration->id,
                provider: 'sysmo',
                payloadKey: $payloadKey,
                storeId: $store?->id,
                storeDocument: $store?->document,
            );

            Log::info('Sysmo sales day fetch page completed.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'store_id' => $store?->id,
                'date' => $this->date,
                'page' => $currentPage,
                'total_pages' => $totalPages,
                'items' => count($items),
                'status' => $response->status(),
            ]);

            $currentPage++;
            unset($payload, $items, $body);
            gc_collect_cycles();
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
     * @return array{empresa: string}|array{}
     */
    private function storeBody(?Store $store): array
    {
        $document = preg_replace('/\D+/', '', (string) $store?->document) ?? '';

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
