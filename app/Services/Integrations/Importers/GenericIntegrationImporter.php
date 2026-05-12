<?php

namespace App\Services\Integrations\Importers;

use App\Jobs\Integrations\Imports\ProcessImportedResourceBatchJob;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Concerns\HasIntegrationHelpers;
use App\Services\Integrations\Http\IntegrationHttpClient;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use App\Services\Integrations\Support\ImportBatchPayloadStore;
use App\Services\Integrations\Support\IntegrationResponseReader;
use App\Services\Integrations\Support\ResolvedIntegrationConfig;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GenericIntegrationImporter
{
    use HasIntegrationHelpers;

    public function __construct(
        private readonly IntegrationHttpClient $httpClient,
        private readonly ImportBatchPayloadStore $importBatchPayloadStore,
        private readonly IntegrationResponseReader $responseReader,
        private readonly ?ResolvedIntegrationConfigResolver $configResolver = null,
    ) {}

    public function importResource(ResolvedIntegrationConfig|TenantIntegration $config, string $resource, string $targetTable, Store $store): void
    {
        $config = $this->resolveConfig($config);
        $request = $config->request($resource);
        $endpoint = $config->path($resource, (string) ($request['fallback_path'] ?? ''));
        $isSales = $targetTable === 'sales';
        $hasData = $this->targetHasRows($config->integration, $targetTable, $store);

        if ($isSales && ! $hasData) {
            foreach ($this->salesInitialDates($config, $targetTable, $request) as $date) {
                $this->importPages($config, $resource, $targetTable, $request, $endpoint, $store,
                    startDate: $date, endDate: $date);
            }

            return;
        }

        if ($isSales) {
            $this->importPages($config, $resource, $targetTable, $request, $endpoint, $store,
                startDate: Carbon::yesterday()->toDateString(),
                endDate: Carbon::today()->toDateString());

            return;
        }

        $changedSince = $hasData ? Carbon::yesterday()->toDateString() : null;
        $this->importPages($config, $resource, $targetTable, $request, $endpoint, $store, changedSince: $changedSince);
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function importPages(
        ResolvedIntegrationConfig $config,
        string $resource,
        string $targetTable,
        array $request,
        string $endpoint,
        Store $store,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $changedSince = null,
    ): void {
        $integration = $config->integration;
        $currentPage = 1;
        $totalPages = 1;

        do {
            [$query, $body] = $this->payload($config, $store, $resource, $request, $currentPage, $startDate, $endDate, $changedSince);

            try {
                $response = $this->httpClient->request(
                    integration: $config,
                    method: (string) ($request['method'] ?? 'GET'),
                    endpoint: $endpoint,
                    query: $query,
                    body: $body,
                );
            } catch (RequestException $exception) {
                if ($this->shouldSkipStatus($request, $exception->response?->status())) {
                    Log::info('Integração import skipped por status do provider.', [
                        'integration_id' => (string) $integration->id,
                        'tenant_id' => (string) $integration->tenant_id,
                        'resource' => $resource,
                        'target_table' => $targetTable,
                        'store_id' => $store->id,
                        'provider' => $config->provider(),
                        'endpoint' => $endpoint,
                        'status' => $exception->response?->status(),
                    ]);

                    return;
                }

                throw $exception;
            }

            $payload = $response->json();
            $payload = is_array($payload) ? $payload : [];
            $totalPages = $this->responseReader->totalPages($config, $resource, $payload, $currentPage);
            $items = $this->responseReader->items($config, $resource, $payload);

            $payloadKey = $this->importBatchPayloadStore->put((string) $integration->id, $resource, $items);
            ProcessImportedResourceBatchJob::dispatch(
                integrationId: (string) $integration->id,
                provider: $config->provider(),
                resource: $resource,
                targetTable: $targetTable,
                payloadKey: $payloadKey,
                storeId: $store->id,
                storeDocument: $store->document,
            );

            Log::info('Integração import page fetched.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'resource' => $resource,
                'target_table' => $targetTable,
                'store_id' => $store->id,
                'provider' => $config->provider(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'changed_since' => $changedSince,
                'page' => $currentPage,
                'total_pages' => $totalPages,
                'items' => count($items),
                'status' => $response->status(),
            ]);

            $currentPage++;
            unset($payload, $items, $query, $body);
            gc_collect_cycles();
        } while ($currentPage <= $totalPages);
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function payload(
        ResolvedIntegrationConfig $config,
        Store $store,
        string $resource,
        array $request,
        ?int $page = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $changedSince = null,
    ): array {
        $query = is_array($request['fixed_query'] ?? null) ? $request['fixed_query'] : [];
        $body = is_array($request['fixed_body'] ?? null) ? $request['fixed_body'] : [];
        $values = [
            ...$this->storePayload($store, $request),
            ...$this->datePayload($request, $startDate, $endDate, $changedSince),
            ...$this->pagePayload($config, $request, $page),
        ];

        if ((string) ($request['payload'] ?? 'query') === 'body') {
            $body = [...$body, ...$values];
        } else {
            $query = [...$values, ...$query];
        }

        return [$query, $body];
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function storePayload(Store $store, array $request): array
    {
        $field = (string) ($request['store_document_field'] ?? '');
        $document = preg_replace('/\D+/', '', (string) $store->document) ?? '';

        return $field !== '' && $document !== '' ? [$field => $document] : [];
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function datePayload(array $request, ?string $startDate, ?string $endDate, ?string $changedSince): array
    {
        $fields = is_array($request['date_fields'] ?? null) ? $request['date_fields'] : [];

        if ($startDate !== null) {
            $startField = (string) ($fields['start'] ?? '');
            $endField = (string) ($fields['end'] ?? '');

            return array_filter(
                [$startField => $startDate, $endField => $endDate],
                fn (mixed $v, string $k): bool => $k !== '' && is_string($v),
                ARRAY_FILTER_USE_BOTH,
            );
        }

        if ($changedSince !== null) {
            $field = (string) ($fields['changed_since'] ?? '');

            return $field !== '' ? [$field => $changedSince] : [];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function pagePayload(ResolvedIntegrationConfig $config, array $request, ?int $page): array
    {
        $field = (string) ($request['page_field'] ?? '');
        if ($page === null || $field === '') {
            return [];
        }

        $pageValue = (string) ($request['page_value_type'] ?? 'integer') === 'string' ? (string) $page : $page;
        $payload = [$field => $pageValue];
        $pageSizeField = (string) ($request['page_size_field'] ?? '');
        $pageSize = $this->pageSize($config, $request);

        if ($pageSize !== null && $pageSizeField !== '') {
            $payload[$pageSizeField] = $pageSize;
        }

        return $payload;
    }

    private function pageSize(ResolvedIntegrationConfig $config, array $request): int|string|null
    {
        $field = (string) ($request['page_size_field'] ?? '');
        if ($field === '' || $this->configuredRequestValue($config, $request, $field) !== null) {
            return null;
        }

        $tenantConfig = $config->tenantConfig();
        $processing = is_array($tenantConfig['processing'] ?? null) ? $tenantConfig['processing'] : [];
        $requested = (int) ($processing['products_page_size'] ?? $request['default_page_size'] ?? 200);
        $min = (int) ($request['min_page_size'] ?? 1);
        $max = (int) ($request['max_page_size'] ?? max($min, $requested));
        $pageSize = max($min, min($max, $requested));

        return (string) ($request['page_value_type'] ?? 'integer') === 'string' ? (string) $pageSize : $pageSize;
    }

    private function configuredRequestValue(ResolvedIntegrationConfig $config, array $request, string $field): ?string
    {
        $connection = $config->connection();
        $payloadKey = (string) ($request['page_size_payload'] ?? $request['payload'] ?? 'query') === 'body' ? 'body' : 'params';
        $rows = is_array($connection[$payloadKey] ?? null) ? $connection[$payloadKey] : [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! $this->rowIsEnabled($row)) {
                continue;
            }

            if (trim((string) ($row['key'] ?? '')) !== $field) {
                continue;
            }

            $value = trim((string) ($row['value'] ?? ''));

            return $value !== '' ? $value : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $request
     * @return list<string>
     */
    private function salesInitialDates(ResolvedIntegrationConfig $config, string $targetTable, array $request): array
    {
        $days = $this->initialDays($config, $targetTable, $request);
        $end = Carbon::today();
        $start = $end->copy()->subDays($days - 1);
        $dates = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function initialDays(ResolvedIntegrationConfig $config, string $targetTable, array $request): int
    {
        if (isset($request['initial_days'])) {
            return max(1, (int) $request['initial_days']);
        }

        $processing = is_array($config->config()['processing'] ?? null) ? $config->config()['processing'] : [];
        $targetKey = $targetTable.'_initial_days';

        if (isset($processing[$targetKey])) {
            return max(1, (int) $processing[$targetKey]);
        }

        return max(1, (int) ($processing['initial_days'] ?? 120));
    }

    private function targetHasRows(TenantIntegration $integration, string $targetTable, Store $store): bool
    {
        $tenant = $integration->tenant;
        if (! $tenant instanceof Tenant || ! $this->validTableName($targetTable)) {
            return false;
        }

        return $tenant->execute(function () use ($store, $tenant, $targetTable): bool {
            $connection = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
            if (! Schema::connection($connection)->hasTable($targetTable)) {
                return false;
            }

            $query = DB::connection($connection)->table($targetTable);

            if (Schema::connection($connection)->hasColumn($targetTable, 'tenant_id')) {
                $query->where('tenant_id', (string) $tenant->id);
            }

            if (Schema::connection($connection)->hasColumn($targetTable, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            if (Schema::connection($connection)->hasColumn($targetTable, 'store_id')) {
                $query->where('store_id', (string) $store->id);
            }

            return $query->exists();
        });
    }

    private function shouldSkipStatus(array $request, ?int $status): bool
    {
        $statuses = is_array($request['skip_statuses'] ?? null) ? $request['skip_statuses'] : [];

        return is_int($status) && in_array($status, array_map('intval', $statuses), true);
    }
}
