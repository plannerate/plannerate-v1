<?php

namespace App\Services\Integrations\Importers;

use App\Jobs\Integrations\Imports\FetchSysmoSalesDayJob;
use App\Jobs\Integrations\Imports\ProcessImportedProductsBatchJob;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Http\IntegrationHttpClient;
use App\Services\Integrations\Support\ImportBatchPayloadStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SysmoImporter implements ClientApiImporter
{
    public function __construct(
        private readonly IntegrationHttpClient $httpClient,
        private readonly ImportBatchPayloadStore $importBatchPayloadStore,
    ) {}

    public function importSales(TenantIntegration $integration, ?Store $store = null): void
    {
        $salesMode = $this->salesMode($integration, $store);
        $dates = $this->salesDates($integration, $store, $salesMode);

        foreach ($dates as $date) {
            FetchSysmoSalesDayJob::dispatch(
                integrationId: (string) $integration->id,
                date: $date,
                storeId: $store?->id,
                storeDocument: $store?->document,
            );
        }

        Log::info('Sysmo sales day fetch jobs dispatched.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
            'store_id' => $store?->id,
            'sales_mode' => $salesMode,
            'days_dispatched' => count($dates),
            'start_date' => $dates[0] ?? null,
            'end_date' => $dates[count($dates) - 1] ?? null,
        ]);
    }

    public function importProducts(TenantIntegration $integration, ?Store $store = null): void
    {
        $endpoint = $this->path($integration, 'products', '/sysmo-integrador-api/api/integradorService/hubprodutos.listar_produtos');
        $currentPage = 1;
        $totalPages = 1;

        do {
            $body = [
                ...$this->storeBody($store),
                ...$this->productsDatePayload($integration, $store),
                'pagina' => (string) $currentPage,
            ];
            $this->applyProductsPageSize($body, $integration);

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

            $payloadKey = $this->importBatchPayloadStore->put((string) $integration->id, 'products', $items);
            ProcessImportedProductsBatchJob::dispatch(
                integrationId: (string) $integration->id,
                provider: 'sysmo',
                payloadKey: $payloadKey,
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
            unset($payload, $items, $body);
            gc_collect_cycles();
        } while ($currentPage <= $totalPages);
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
        array $context = [],
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
            ...$context,
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
     * @return array{data_ultima_alteracao: string}|array{}
     */
    private function productsDatePayload(TenantIntegration $integration, ?Store $store = null): array
    {
        if (! $this->tenantHasProducts($integration, $store)) {
            return [];
        }

        return [
            'data_ultima_alteracao' => Carbon::yesterday()->toDateString(),
        ];
    }

    private function tenantHasProducts(TenantIntegration $integration, ?Store $store = null): bool
    {
        $tenant = $integration->tenant;
        if (! $tenant instanceof Tenant) {
            return true;
        }

        return $tenant->execute(function () use ($integration, $store, $tenant): bool {
            $connection = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
            $isSeparateByStore = $this->separateByStore($integration);

            if ($isSeparateByStore && $store instanceof Store && is_string($store->id) && $store->id !== '') {
                return DB::connection($connection)
                    ->table('product_store')
                    ->where('tenant_id', (string) $tenant->id)
                    ->where('store_id', (string) $store->id)
                    ->exists();
            }

            return DB::connection($connection)
                ->table('products')
                ->whereNull('deleted_at')
                ->exists();
        });
    }

    private function separateByStore(TenantIntegration $integration): bool
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : [];

        return (bool) ($processing['separate_by_store'] ?? false);
    }

    /**
     * @param  array<string, string>  $body
     */
    private function applyProductsPageSize(array &$body, TenantIntegration $integration): void
    {
        if (array_key_exists('tamanho_pagina', $body) && trim((string) $body['tamanho_pagina']) !== '') {
            return;
        }

        if ($this->connectionBodyHasValue($integration, 'tamanho_pagina')) {
            return;
        }

        $config = is_array($integration->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : [];
        $requested = (int) ($processing['products_page_size'] ?? 500);

        $body['tamanho_pagina'] = (string) max(100, min(5000, $requested));
    }

    private function connectionBodyHasValue(TenantIntegration $integration, string $key): bool
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $connection = is_array($config['connection'] ?? null) ? $config['connection'] : [];
        $bodyRows = is_array($connection['body'] ?? null) ? $connection['body'] : [];

        foreach ($bodyRows as $row) {
            if (! is_array($row) || ! $this->rowIsEnabled($row)) {
                continue;
            }

            if (trim((string) ($row['key'] ?? '')) !== $key) {
                continue;
            }

            return trim((string) ($row['value'] ?? '')) !== '';
        }

        return false;
    }

    /**
     * @return array{data_inicial: string, data_final: string}
     */
    private function salesDatePayload(TenantIntegration $integration, string $mode): array
    {
        $endDate = Carbon::today();
        if ($mode === 'daily') {
            return [
                'data_inicial' => $endDate->copy()->subDay()->toDateString(),
                'data_final' => $endDate->toDateString(),
            ];
        }

        $config = is_array($integration->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : [];
        $days = max(1, (int) ($processing['sales_initial_days'] ?? 120));

        return [
            'data_inicial' => $endDate->copy()->subDays($days - 1)->toDateString(),
            'data_final' => $endDate->toDateString(),
        ];
    }

    private function salesMode(TenantIntegration $integration, ?Store $store = null): string
    {
        return $this->tenantHasSales($integration, $store) ? 'daily' : 'initial';
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
            $this->logSalesGapSummary($integration, $store, $lookbackDays, $missingDates);

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
            $isSeparateByStore = $this->separateByStore($integration);

            $query = DB::connection($connection)
                ->table('sales')
                ->where('tenant_id', (string) $tenant->id)
                ->whereNull('deleted_at')
                ->whereBetween('sale_date', [$startDate->toDateString(), $endDate->toDateString()]);

            if ($isSeparateByStore && $store instanceof Store && is_string($store->id) && $store->id !== '') {
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

    /**
     * @param  list<string>  $missingDates
     */
    private function logSalesGapSummary(
        TenantIntegration $integration,
        ?Store $store,
        int $lookbackDays,
        array $missingDates,
    ): void {
        Log::info('Sysmo sales gap analysis.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
            'store_id' => $store?->id,
            'lookback_days' => $lookbackDays,
            'missing_days_count' => count($missingDates),
            'missing_days_sample' => array_slice($missingDates, 0, 10),
        ]);
    }

    private function tenantHasSales(TenantIntegration $integration, ?Store $store = null): bool
    {
        $tenant = $integration->tenant;
        if (! $tenant instanceof Tenant) {
            return true;
        }

        return $tenant->execute(function () use ($integration, $store, $tenant): bool {
            $connection = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
            $isSeparateByStore = $this->separateByStore($integration);

            if ($isSeparateByStore && $store instanceof Store && is_string($store->id) && $store->id !== '') {
                return DB::connection($connection)
                    ->table('sales')
                    ->where('tenant_id', (string) $tenant->id)
                    ->where('store_id', (string) $store->id)
                    ->whereNull('deleted_at')
                    ->exists();
            }

            return DB::connection($connection)
                ->table('sales')
                ->where('tenant_id', (string) $tenant->id)
                ->whereNull('deleted_at')
                ->exists();
        });
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
