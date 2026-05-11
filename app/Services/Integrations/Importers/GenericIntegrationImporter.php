<?php

namespace App\Services\Integrations\Importers;

use App\Jobs\Integrations\Imports\ProcessImportedResourceBatchJob;
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
use Illuminate\Support\Facades\Schema;
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
        $this->importResource($integration, 'sales', 'sales', $store);
    }

    public function importProducts(TenantIntegration $integration, ?Store $store = null): void
    {
        $this->importResource($integration, 'products', 'products', $store);
    }

    public function importResource(TenantIntegration $integration, string $resource, string $targetTable, ?Store $store = null): void
    {
        $request = $this->requestConfig($integration, $resource);
        $endpoint = $this->path($integration, $resource, (string) ($request['fallback_path'] ?? ''));

        if ($endpoint === '') {
            Log::warning('Integração import skipped: endpoint ainda não definido.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'resource' => $resource,
                'target_table' => $targetTable,
                'store_id' => $store?->id,
                'provider' => (string) $integration->integration_type,
            ]);

            return;
        }

        if ($this->dateStrategy($integration, $resource) === 'sales_incremental') {
            foreach ($this->salesDates($integration, $targetTable, $request, $store) as $date) {
                $this->importPages($integration, $resource, $targetTable, $request, $endpoint, $store, $date);
            }

            return;
        }

        $this->importPages($integration, $resource, $targetTable, $request, $endpoint, $store);
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function importPages(
        TenantIntegration $integration,
        string $resource,
        string $targetTable,
        array $request,
        string $endpoint,
        ?Store $store,
        ?string $date = null,
    ): void {
        $currentPage = 1;
        $totalPages = 1;

        do {
            [$query, $body] = $this->payload($integration, $store, $resource, $targetTable, $request, $currentPage, $date);

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
                    Log::info('Integração import skipped por status do provider.', [
                        'integration_id' => (string) $integration->id,
                        'tenant_id' => (string) $integration->tenant_id,
                        'resource' => $resource,
                        'target_table' => $targetTable,
                        'store_id' => $store?->id,
                        'provider' => (string) $integration->integration_type,
                        'endpoint' => $endpoint,
                        'date' => $date,
                        'status' => $exception->response?->status(),
                    ]);

                    return;
                }

                throw $exception;
            }

            $payload = $response->json();
            $payload = is_array($payload) ? $payload : [];
            $totalPages = $this->responseReader->totalPages($integration, $resource, $payload, $currentPage);
            $items = $this->responseReader->items($integration, $resource, $payload);

            $payloadKey = $this->importBatchPayloadStore->put((string) $integration->id, $resource, $items);
            ProcessImportedResourceBatchJob::dispatch(
                integrationId: (string) $integration->id,
                provider: (string) $integration->integration_type,
                resource: $resource,
                targetTable: $targetTable,
                payloadKey: $payloadKey,
                storeId: $store?->id,
                storeDocument: $store?->document,
            );

            Log::info('Integração import page fetched.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'resource' => $resource,
                'target_table' => $targetTable,
                'store_id' => $store?->id,
                'provider' => (string) $integration->integration_type,
                'date' => $date,
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
        TenantIntegration $integration,
        ?Store $store,
        string $resource,
        string $targetTable,
        array $request,
        ?int $page = null,
        ?string $date = null,
    ): array {
        $query = is_array($request['fixed_query'] ?? null) ? $request['fixed_query'] : [];
        $body = is_array($request['fixed_body'] ?? null) ? $request['fixed_body'] : [];
        $values = [
            ...$this->storePayload($store, $request),
            ...$this->datePayload($integration, $store, $resource, $targetTable, $request, $date),
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
    private function datePayload(
        TenantIntegration $integration,
        ?Store $store,
        string $resource,
        string $targetTable,
        array $request,
        ?string $date = null,
    ): array {
        $fields = is_array($request['date_fields'] ?? null) ? $request['date_fields'] : [];
        $strategy = $this->dateStrategy($integration, $resource);

        if ($strategy === 'none') {
            return [];
        }

        if ($strategy === 'sales_incremental' && is_string($date) && $date !== '') {
            return $this->rangeDatePayload($fields, Carbon::parse($date), Carbon::parse($date), true);
        }

        if (! in_array($strategy, ['products_incremental', 'range_incremental'], true)) {
            return [];
        }

        $endDate = Carbon::today();
        $startDate = $this->targetHasRows($integration, $targetTable, $store)
            ? $endDate->copy()->subDay()
            : $endDate->copy()->subDays($this->initialDays($integration, $targetTable, $request) - 1);

        return $this->rangeDatePayload($fields, $startDate, $endDate, false);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function rangeDatePayload(array $fields, Carbon $startDate, Carbon $endDate, bool $defaultSalesFields): array
    {
        if (is_string($fields['changed_since'] ?? null)) {
            return [(string) $fields['changed_since'] => $startDate->toDateString()];
        }

        if (is_string($fields['created_from'] ?? null) && is_string($fields['created_to'] ?? null)) {
            return [
                (string) $fields['created_from'] => $startDate->copy()->startOfDay()->toIso8601String(),
                (string) $fields['created_to'] => $endDate->copy()->endOfDay()->toIso8601String(),
            ];
        }

        $startField = (string) ($fields['start'] ?? ($defaultSalesFields ? 'data_inicial' : ''));
        $endField = (string) ($fields['end'] ?? ($defaultSalesFields ? 'data_final' : ''));

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

    /**
     * @param  array<string, mixed>  $request
     * @return list<string>
     */
    private function salesDates(TenantIntegration $integration, string $targetTable, array $request, ?Store $store): array
    {
        $endDate = Carbon::today();
        $lookbackDays = $this->initialDays($integration, $targetTable, $request);

        if (! $this->targetHasRows($integration, $targetTable, $store)) {
            return $this->dateRange($endDate->copy()->subDays($lookbackDays - 1), $endDate);
        }

        $baseDates = [
            $endDate->copy()->subDay()->toDateString(),
            $endDate->toDateString(),
        ];
        $missingDates = $this->resolveMissingDates($integration, $targetTable, $request, $store, $lookbackDays);
        $merged = array_values(array_unique([...$baseDates, ...$missingDates]));
        sort($merged);

        return $merged;
    }

    /**
     * @return list<string>
     */
    private function dateRange(Carbon $startDate, Carbon $endDate): array
    {
        $dates = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /**
     * @param  array<string, mixed>  $request
     * @return list<string>
     */
    private function resolveMissingDates(TenantIntegration $integration, string $targetTable, array $request, ?Store $store, int $lookbackDays): array
    {
        $tenant = $integration->tenant;
        if (! $tenant instanceof Tenant || ! $this->validTableName($targetTable)) {
            return [];
        }

        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays($lookbackDays - 1);
        $expectedDates = $this->dateRange($startDate, $endDate);
        $dateColumn = (string) ($request['date_column'] ?? 'sale_date');

        $existingDates = $tenant->execute(function () use ($store, $tenant, $targetTable, $dateColumn, $startDate, $endDate): array {
            $connection = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
            if (! Schema::connection($connection)->hasTable($targetTable) || ! Schema::connection($connection)->hasColumn($targetTable, $dateColumn)) {
                return [];
            }

            $query = DB::connection($connection)
                ->table($targetTable)
                ->whereBetween($dateColumn, [$startDate->toDateString(), $endDate->toDateString()]);

            if (Schema::connection($connection)->hasColumn($targetTable, 'tenant_id')) {
                $query->where('tenant_id', (string) $tenant->id);
            }

            if (Schema::connection($connection)->hasColumn($targetTable, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            if ($store instanceof Store && is_string($store->id) && $store->id !== '' && Schema::connection($connection)->hasColumn($targetTable, 'store_id')) {
                $query->where('store_id', (string) $store->id);
            }

            return $query
                ->distinct()
                ->pluck($dateColumn)
                ->filter(fn (mixed $date): bool => is_string($date) && $date !== '')
                ->map(fn (string $date): string => Carbon::parse($date)->toDateString())
                ->values()
                ->all();
        });

        $missingDates = array_values(array_diff($expectedDates, $existingDates));
        sort($missingDates);

        return $missingDates;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function initialDays(TenantIntegration $integration, string $targetTable, array $request): int
    {
        if (isset($request['initial_days'])) {
            return max(1, (int) $request['initial_days']);
        }

        $config = is_array($integration->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : [];
        $targetKey = $targetTable.'_initial_days';

        if (isset($processing[$targetKey])) {
            return max(1, (int) $processing[$targetKey]);
        }

        if ($targetTable === 'sales') {
            return max(1, (int) ($processing['sales_initial_days'] ?? 120));
        }

        if ($targetTable === 'products') {
            return max(1, (int) ($processing['products_initial_days'] ?? 120));
        }

        return 120;
    }

    private function targetHasRows(TenantIntegration $integration, string $targetTable, ?Store $store = null): bool
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

            if ($store instanceof Store && is_string($store->id) && $store->id !== '' && Schema::connection($connection)->hasColumn($targetTable, 'store_id')) {
                $query->where('store_id', (string) $store->id);
            }

            return $query->exists();
        });
    }

    private function dateStrategy(TenantIntegration $integration, string $resource): string
    {
        return $this->resolvedConfig($integration)->dateStrategy($resource);
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

    private function validTableName(string $table): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $table) === 1;
    }

    private function resolvedConfig(TenantIntegration $integration): ResolvedIntegrationConfig
    {
        return ($this->configResolver ?? app(ResolvedIntegrationConfigResolver::class))->resolve($integration);
    }
}
