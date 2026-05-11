<?php

namespace App\Services\Integrations\Importers;

use App\Jobs\Integrations\Imports\FetchIntegrationSalesDayJob;
use App\Jobs\Integrations\Imports\ProcessImportedProductsBatchJob;
use App\Jobs\Integrations\Imports\ProcessImportedSalesBatchJob;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Http\IntegrationHttpClient;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use App\Services\Integrations\Support\ImportBatchPayloadStore;
use App\Services\Integrations\Support\IntegrationResponseReader;
use App\Services\Integrations\Support\ResolvedIntegrationConfig;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GenericIntegrationImporter
{
    public function __construct(
        private readonly IntegrationHttpClient $httpClient,
        private readonly ImportBatchPayloadStore $importBatchPayloadStore,
        private readonly IntegrationResponseReader $responseReader,
        private readonly ?ResolvedIntegrationConfigResolver $configResolver = null,
    ) {}

    public function importSales(TenantIntegration $integration, ?Store $store = null): void
    {
        $request = $this->requestConfig($integration, 'sales');

        if ((bool) ($request['dispatch_by_day'] ?? false)) {
            $this->dispatchSalesDays($integration, $store);

            return;
        }

        $endpoint = $this->path($integration, 'sales', (string) ($request['fallback_path'] ?? ''));
        if ($endpoint === '') {
            Log::info('Integração sales import skipped: endpoint ainda não definido.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'store_id' => $store?->id,
                'provider' => (string) $integration->integration_type,
            ]);

            return;
        }

        [$query, $body] = $this->payload($integration, $store, 'sales', $request);

        try {
            $response = $this->httpClient->request(
                integration: $integration,
                method: (string) ($request['method'] ?? 'GET'),
                endpoint: $endpoint,
                query: $query,
                body: $body,
            );
        } catch (RequestException $exception) {
            if ($this->shouldSkipStatus($request, $exception->response?->status())) {
                Log::info('Integração sales import skipped por status do provider.', [
                    'integration_id' => (string) $integration->id,
                    'tenant_id' => (string) $integration->tenant_id,
                    'store_id' => $store?->id,
                    'provider' => (string) $integration->integration_type,
                    'endpoint' => $endpoint,
                    'status' => $exception->response?->status(),
                ]);

                return;
            }

            throw $exception;
        }

        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];
        $items = $this->responseReader->items($integration, 'sales', $payload);

        $payloadKey = $this->importBatchPayloadStore->put((string) $integration->id, 'sales', $items);
        ProcessImportedSalesBatchJob::dispatch(
            integrationId: (string) $integration->id,
            provider: (string) $integration->integration_type,
            payloadKey: $payloadKey,
            storeId: $store?->id,
            storeDocument: $store?->document,
        );

        Log::info('Integração sales import request completed.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
            'store_id' => $store?->id,
            'provider' => (string) $integration->integration_type,
            'items' => count($items),
            'status' => $response->status(),
        ]);
    }

    public function importProducts(TenantIntegration $integration, ?Store $store = null): void
    {
        $request = $this->requestConfig($integration, 'products');
        $endpoint = $this->path($integration, 'products', (string) ($request['fallback_path'] ?? ''));

        if ($endpoint === '') {
            throw new RuntimeException(sprintf(
                'Endpoint de produtos não configurado para integração [%s].',
                (string) $integration->id,
            ));
        }

        $currentPage = 1;
        $totalPages = 1;

        do {
            [$query, $body] = $this->payload($integration, $store, 'products', $request, $currentPage);

            $response = $this->httpClient->request(
                integration: $integration,
                method: (string) ($request['method'] ?? 'GET'),
                endpoint: $endpoint,
                query: $query,
                body: $body,
            );

            $payload = $response->json();
            $payload = is_array($payload) ? $payload : [];
            $totalPages = $this->responseReader->totalPages($integration, 'products', $payload, $currentPage);
            $items = $this->responseReader->items($integration, 'products', $payload);

            $payloadKey = $this->importBatchPayloadStore->put((string) $integration->id, 'products', $items);
            ProcessImportedProductsBatchJob::dispatch(
                integrationId: (string) $integration->id,
                provider: (string) $integration->integration_type,
                payloadKey: $payloadKey,
                storeId: $store?->id,
            );

            Log::info('Integração products import page fetched.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'store_id' => $store?->id,
                'provider' => (string) $integration->integration_type,
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
    private function payload(TenantIntegration $integration, ?Store $store, string $resource, array $request, ?int $page = null): array
    {
        $query = is_array($request['fixed_query'] ?? null) ? $request['fixed_query'] : [];
        $body = is_array($request['fixed_body'] ?? null) ? $request['fixed_body'] : [];
        $values = [
            ...$this->storePayload($store, $request),
            ...$this->datePayload($integration, $store, $resource, $request),
            ...$this->pagePayload($integration, $request, $page),
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
    private function storePayload(?Store $store, array $request): array
    {
        $field = (string) ($request['store_document_field'] ?? '');
        $document = preg_replace('/\D+/', '', (string) $store?->document) ?? '';

        return $field !== '' && $document !== '' ? [$field => $document] : [];
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function datePayload(TenantIntegration $integration, ?Store $store, string $resource, array $request): array
    {
        $fields = is_array($request['date_fields'] ?? null) ? $request['date_fields'] : [];

        if ($resource === 'sales') {
            return $this->salesDatePayload($integration, $store, $fields);
        }

        if ($resource !== 'products' || ! $this->tenantHasProducts($integration, $store)) {
            return [];
        }

        if (is_string($fields['changed_since'] ?? null)) {
            return [(string) $fields['changed_since'] => Carbon::yesterday()->toDateString()];
        }

        if (is_string($fields['created_from'] ?? null) && is_string($fields['created_to'] ?? null)) {
            return [
                (string) $fields['created_from'] => Carbon::yesterday()->startOfDay()->toIso8601String(),
                (string) $fields['created_to'] => Carbon::now()->toIso8601String(),
            ];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function salesDatePayload(TenantIntegration $integration, ?Store $store, array $fields): array
    {
        $startField = (string) ($fields['start'] ?? '');
        $endField = (string) ($fields['end'] ?? '');
        $endDate = Carbon::today();
        $startDate = $this->tenantHasSales($integration, $store)
            ? $endDate->copy()->subDay()
            : $endDate->copy()->subDays($this->salesLookbackDays($integration) - 1);

        return array_filter([
            $startField => $startDate->toDateString(),
            $endField => $endDate->toDateString(),
        ], fn (string $value, string $key): bool => $key !== '', ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function pagePayload(TenantIntegration $integration, array $request, ?int $page): array
    {
        $field = (string) ($request['page_field'] ?? '');
        if ($page === null || $field === '') {
            return [];
        }

        $pageValue = (string) ($request['page_value_type'] ?? 'integer') === 'string' ? (string) $page : $page;
        $payload = [$field => $pageValue];
        $pageSizeField = (string) ($request['page_size_field'] ?? '');
        $pageSize = $this->pageSize($integration, $request);

        if ($pageSize !== null && $pageSizeField !== '') {
            $payload[$pageSizeField] = $pageSize;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function pageSize(TenantIntegration $integration, array $request): int|string|null
    {
        $field = (string) ($request['page_size_field'] ?? '');
        if ($field === '' || $this->configuredRequestValue($integration, $request, $field) !== null) {
            return null;
        }

        $config = is_array($integration->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : [];
        $requested = (int) ($processing['products_page_size'] ?? $request['default_page_size'] ?? 200);
        $min = (int) ($request['min_page_size'] ?? 1);
        $max = (int) ($request['max_page_size'] ?? max($min, $requested));
        $pageSize = max($min, min($max, $requested));

        return (string) ($request['page_value_type'] ?? 'integer') === 'string' ? (string) $pageSize : $pageSize;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function configuredRequestValue(TenantIntegration $integration, array $request, string $field): ?string
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $connection = is_array($config['connection'] ?? null) ? $config['connection'] : [];
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

    private function dispatchSalesDays(TenantIntegration $integration, ?Store $store = null): void
    {
        $salesMode = $this->tenantHasSales($integration, $store) ? 'daily' : 'initial';
        $dates = $this->salesDates($integration, $store, $salesMode);

        foreach ($dates as $date) {
            FetchIntegrationSalesDayJob::dispatch(
                integrationId: (string) $integration->id,
                date: $date,
                storeId: $store?->id,
                storeDocument: $store?->document,
            );
        }

        Log::info('Integração sales day fetch jobs dispatched.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
            'store_id' => $store?->id,
            'provider' => (string) $integration->integration_type,
            'sales_mode' => $salesMode,
            'days_dispatched' => count($dates),
            'start_date' => $dates[0] ?? null,
            'end_date' => $dates[count($dates) - 1] ?? null,
        ]);
    }

    /**
     * @return list<string>
     */
    private function salesDates(TenantIntegration $integration, ?Store $store, string $mode): array
    {
        $endDate = Carbon::today();
        $lookbackDays = $this->salesLookbackDays($integration);

        if ($mode === 'daily') {
            $baseDates = [
                $endDate->copy()->subDay()->toDateString(),
                $endDate->toDateString(),
            ];
            $missingDates = $this->resolveMissingSalesDates($integration, $store, $lookbackDays);

            $merged = array_values(array_unique([...$baseDates, ...$missingDates]));
            sort($merged);

            return $merged;
        }

        $dates = [];
        for ($i = $lookbackDays - 1; $i >= 0; $i--) {
            $dates[] = $endDate->copy()->subDays($i)->toDateString();
        }

        return $dates;
    }

    private function salesLookbackDays(TenantIntegration $integration): int
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : [];

        return max(1, (int) ($processing['sales_initial_days'] ?? 120));
    }

    /**
     * @return list<string>
     */
    private function resolveMissingSalesDates(TenantIntegration $integration, ?Store $store, int $lookbackDays): array
    {
        $tenant = $integration->tenant;
        if (! $tenant instanceof Tenant) {
            return [];
        }

        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays($lookbackDays - 1);
        $expectedDates = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $expectedDates[] = $date->toDateString();
        }

        $existingDates = $tenant->execute(function () use ($integration, $store, $tenant, $startDate, $endDate): array {
            $connection = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
            $query = DB::connection($connection)
                ->table('sales')
                ->where('tenant_id', (string) $tenant->id)
                ->whereNull('deleted_at')
                ->whereBetween('sale_date', [$startDate->toDateString(), $endDate->toDateString()]);

            if ($this->separateByStore($integration) && $store instanceof Store && is_string($store->id) && $store->id !== '') {
                $query->where('store_id', (string) $store->id);
            }

            return $query
                ->distinct()
                ->pluck('sale_date')
                ->filter(fn (mixed $date): bool => is_string($date) && $date !== '')
                ->map(fn (string $date): string => Carbon::parse($date)->toDateString())
                ->values()
                ->all();
        });

        $missingDates = array_values(array_diff($expectedDates, $existingDates));
        sort($missingDates);

        return $missingDates;
    }

    private function tenantHasProducts(TenantIntegration $integration, ?Store $store = null): bool
    {
        $tenant = $integration->tenant;
        if (! $tenant instanceof Tenant) {
            return false;
        }

        return $tenant->execute(function () use ($integration, $store, $tenant): bool {
            $connection = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));

            if ($this->separateByStore($integration) && $store instanceof Store && is_string($store->id) && $store->id !== '') {
                return DB::connection($connection)
                    ->table('product_store')
                    ->where('tenant_id', (string) $tenant->id)
                    ->where('store_id', (string) $store->id)
                    ->exists();
            }

            $query = DB::connection($connection)
                ->table('products')
                ->whereNull('deleted_at');

            $query->where('tenant_id', (string) $tenant->id);

            return $query->exists();
        });
    }

    private function tenantHasSales(TenantIntegration $integration, ?Store $store = null): bool
    {
        $tenant = $integration->tenant;
        if (! $tenant instanceof Tenant) {
            return true;
        }

        return $tenant->execute(function () use ($integration, $store, $tenant): bool {
            $connection = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
            $query = DB::connection($connection)
                ->table('sales')
                ->where('tenant_id', (string) $tenant->id)
                ->whereNull('deleted_at');

            if ($this->separateByStore($integration) && $store instanceof Store && is_string($store->id) && $store->id !== '') {
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

    /**
     * @return array<string, mixed>
     */
    private function requestConfig(TenantIntegration $integration, string $resource): array
    {
        $resolvedConfig = $this->resolvedConfig($integration);
        if ($resolvedConfig->requests() === []) {
            throw new RuntimeException(sprintf(
                'Configuração de importação [%s] não encontrada para integração [%s].',
                $resource,
                (string) $integration->integration_type,
            ));
        }

        $config = $resolvedConfig->request($resource);
        if ($config === []) {
            throw new RuntimeException(sprintf(
                'Configuração de importação [%s] não encontrada para integração [%s].',
                $resource,
                (string) $integration->integration_type,
            ));
        }

        return $config;
    }

    private function path(TenantIntegration $integration, string $key, string $fallback): string
    {
        return $this->resolvedConfig($integration)->path($key, $fallback);
    }

    private function separateByStore(TenantIntegration $integration): bool
    {
        return $this->resolvedConfig($integration)->separateByStore();
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

    private function resolvedConfig(TenantIntegration $integration): ResolvedIntegrationConfig
    {
        return ($this->configResolver ?? app(ResolvedIntegrationConfigResolver::class))->resolve($integration);
    }
}
