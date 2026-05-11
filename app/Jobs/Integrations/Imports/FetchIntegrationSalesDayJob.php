<?php

namespace App\Jobs\Integrations\Imports;

use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\Http\IntegrationHttpClient;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use App\Services\Integrations\Support\ImportBatchPayloadStore;
use App\Services\Integrations\Support\IntegrationResponseReader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class FetchIntegrationSalesDayJob implements NotTenantAware, ShouldQueue
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
        IntegrationResponseReader $responseReader,
        ResolvedIntegrationConfigResolver $configResolver,
    ): void {
        $integration = TenantIntegration::query()
            ->with('tenant')
            ->whereKey($this->integrationId)
            ->where('is_active', true)
            ->first();

        if (! $integration instanceof TenantIntegration) {
            Log::warning('Fetch diário de vendas da integração ignorado: integração ativa não encontrada.', [
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

        $resolvedConfig = $configResolver->resolve($integration);
        $requestConfig = $resolvedConfig->request('sales');
        $endpoint = $resolvedConfig->path('sales');
        if ($endpoint === '') {
            Log::warning('Fetch diário de vendas da integração ignorado: endpoint não configurado.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'store_id' => $store?->id,
                'provider' => (string) $integration->integration_type,
                'date' => $this->date,
            ]);

            return;
        }
        $currentPage = 1;
        $totalPages = 1;

        do {
            [$query, $body] = $this->payload($requestConfig, $store, $currentPage);
            $method = strtoupper((string) ($requestConfig['method'] ?? 'POST'));

            Log::info('Integration sales day fetch request payload.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'store_id' => $store?->id,
                'store_document' => $store?->document,
                'provider' => (string) $integration->integration_type,
                'date' => $this->date,
                'method' => $method,
                'endpoint' => $endpoint,
                'query' => $query,
                'body' => $body,
            ]);

            try {
                $response = $httpClient->request(
                    integration: $integration,
                    method: $method,
                    endpoint: $endpoint,
                    query: $query,
                    body: $body,
                );
            } catch (RequestException $exception) {
                $status = $exception->response?->status();
                if ($this->shouldSkipStatus($requestConfig, $status)) {
                    Log::warning('Integration sales day fetch skipped due to provider response.', [
                        'integration_id' => (string) $integration->id,
                        'tenant_id' => (string) $integration->tenant_id,
                        'store_id' => $store?->id,
                        'provider' => (string) $integration->integration_type,
                        'date' => $this->date,
                        'status' => $status,
                    ]);

                    return;
                }

                throw $exception;
            }

            $payload = $response->json();
            $payload = is_array($payload) ? $payload : [];
            $totalPages = $responseReader->totalPages($integration, 'sales', $payload, $currentPage);
            $items = $responseReader->items($integration, 'sales', $payload);

            $payloadKey = $importBatchPayloadStore->put((string) $integration->id, 'sales', $items);
            ProcessImportedSalesBatchJob::dispatch(
                integrationId: (string) $integration->id,
                provider: (string) $integration->integration_type,
                payloadKey: $payloadKey,
                storeId: $store?->id,
                storeDocument: $store?->document,
            );

            Log::info('Integration sales day fetch page completed.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'store_id' => $store?->id,
                'provider' => (string) $integration->integration_type,
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
     * @param  array<string, mixed>  $request
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function payload(array $request, ?Store $store, int $page): array
    {
        $query = is_array($request['fixed_query'] ?? null) ? $request['fixed_query'] : [];
        $body = is_array($request['fixed_body'] ?? null) ? $request['fixed_body'] : [];
        $values = [
            ...$this->storePayload($request, $store),
            ...$this->datePayload($request),
            ...$this->pagePayload($request, $page),
        ];

        if ((string) ($request['payload'] ?? 'body') === 'body') {
            $body = [...$body, ...$values];
        } else {
            $query = [...$values, ...$query];
        }

        return [$query, $body];
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, string>
     */
    private function storePayload(array $request, ?Store $store): array
    {
        $field = (string) ($request['store_document_field'] ?? 'empresa');
        $document = preg_replace('/\D+/', '', (string) $store?->document) ?? '';

        return $field !== '' && $document !== '' ? [$field => $document] : [];
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, string>
     */
    private function datePayload(array $request): array
    {
        $fields = is_array($request['date_fields'] ?? null) ? $request['date_fields'] : [];
        $startField = (string) ($fields['start'] ?? 'data_inicial');
        $endField = (string) ($fields['end'] ?? 'data_final');

        return array_filter([
            $startField => $this->date,
            $endField => $this->date,
        ], fn (string $value, string $key): bool => $key !== '', ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, int|string>
     */
    private function pagePayload(array $request, int $page): array
    {
        $field = (string) ($request['page_field'] ?? 'pagina');
        $payload = [];

        if ($field !== '') {
            $payload[$field] = (string) ($request['page_value_type'] ?? 'string') === 'string' ? (string) $page : $page;
        }

        $pageSizeField = (string) ($request['page_size_field'] ?? '');
        if ($pageSizeField !== '') {
            $pageSize = (int) ($request['default_page_size'] ?? $request['max_page_size'] ?? 200);
            $min = (int) ($request['min_page_size'] ?? 1);
            $max = (int) ($request['max_page_size'] ?? max($min, $pageSize));
            $payload[$pageSizeField] = max($min, min($max, $pageSize));
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function shouldSkipStatus(array $request, ?int $status): bool
    {
        $statuses = is_array($request['skip_statuses'] ?? null) ? $request['skip_statuses'] : [404, 501];

        return is_int($status) && in_array($status, array_map('intval', $statuses), true);
    }
}
