<?php

namespace App\Jobs\Integrations;

use App\Models\IntegrationApi;
use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\IntegrationHttpClient;
use App\Services\Integrations\IntegrationPayloadBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Jobs\NotTenantAware;

/**
 * Descobre quantas páginas existem para um path da integração
 * e despacha um FetchIntegrationPageJob por página × loja.
 */
class DiscoverIntegrationPagesJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly string $integrationId,
        public readonly string $pathKey,
        public readonly ?string $dateStart = null,
        public readonly ?string $dateEnd = null,
    ) {
        $this->onQueue('imports-fetch');
    }

    public function handle(): void
    {
        $integration = $this->loadIntegration();

        if ($integration === null) {
            return;
        }

        $api = $integration->api;
        $pathConfig = $this->resolvePathConfig($api);

        if ($pathConfig === null) {
            return;
        }

        $config = $integration->config ?? [];
        $requests = $api->requests ?? [];

        $minPageSize = max(1, (int) (data_get($pathConfig, 'min_page_size') ?? data_get($requests, 'min_page_size', 1)));
        $maxPageSize = max(1, (int) (data_get($pathConfig, 'max_page_size') ?? data_get($requests, 'max_page_size', 1000)));

        $stores = $this->loadStores($integration, $requests);

        foreach ($stores as $store) {
            [$effectiveDateStart, $effectiveDateEnd] = $this->resolveEffectiveDates($integration, $pathConfig, $store);
            $this->discoverForStore($api, $config, $requests, $pathConfig, $minPageSize, $maxPageSize, $store, $effectiveDateStart, $effectiveDateEnd);
        }
    }

    // ─── Lojas ───────────────────────────────────────────────────────────────

    /**
     * Retorna a lista de lojas relevantes para a integração.
     *
     * Se a API não exige filtro por loja, retorna um array com null
     * (uma iteração sem storeDocument/storeId).
     *
     * @param  array<string, mixed>  $requests
     * @return array<int, array{id: string, document: string}|null>
     */
    private function loadStores(TenantIntegration $integration, array $requests): array
    {
        $storeDocumentField = (string) data_get($requests, 'store_document_field', '');

        if ($storeDocumentField === '' || $integration->tenant === null) {
            return [null];
        }

        $stores = $integration->tenant->execute(function (): array {
            return Store::published()
                ->get(['id', 'document'])
                ->map(fn (Store $store): array => [
                    'id' => (string) $store->id,
                    'document' => preg_replace('/\D/', '', (string) $store->document) ?? '',
                ])
                ->filter(fn (array $s): bool => $s['document'] !== '')
                ->values()
                ->all();
        });

        if ($stores === []) {
            Log::warning('DiscoverIntegrationPagesJob: nenhuma loja publicada encontrada', [
                'integration_id' => $this->integrationId,
            ]);
        }

        return $stores;
    }

    // ─── Descoberta por loja ─────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $requests
     * @param  array<string, mixed>  $pathConfig
     * @param  array{id: string, document: string}|null  $store
     */
    private function discoverForStore(
        IntegrationApi $api,
        array $config,
        array $requests,
        array $pathConfig,
        int $minPageSize,
        int $maxPageSize,
        ?array $store,
        ?string $effectiveDateStart,
        ?string $effectiveDateEnd,
    ): void {
        $storeDocument = data_get($store, 'document');
        $storeId = data_get($store, 'id');

        $url = $this->buildUrl($config, $pathConfig);
        $method = strtolower((string) data_get($requests, 'method', 'get'));

        $payload = (new IntegrationPayloadBuilder($config, $requests, $pathConfig))
            ->build($effectiveDateStart, $effectiveDateEnd, $storeDocument, useMinPageSize: true);

        Log::info('DiscoverIntegrationPagesJob: iniciando descoberta de páginas', [
            'payload' => $payload,
        ]);

        $response = (new IntegrationHttpClient($config))
            ->call($method, $url, $payload);

        if (! $response->successful()) {
            Log::error('DiscoverIntegrationPagesJob: falha na chamada HTTP', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'store_id' => $storeId,
                'status' => $response->status(),
                'url' => $url,
            ]);

            $this->fail(sprintf('HTTP %d ao acessar %s', $response->status(), $url));

            return;
        }

        $responseData = $response->json();
        $responseMeta = $api->response ?? [];

        $lastPageAtMinSize = $this->readLastPage($responseData, $responseMeta);
        $lastPage = (int) ceil($lastPageAtMinSize * $minPageSize / $maxPageSize);
        $lastPage = $this->applyMaxPageLimit($lastPage, $pathConfig);

        Log::info('DiscoverIntegrationPagesJob: descoberta concluída', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'store_id' => $storeId,
            'pages_at_min_size' => $lastPageAtMinSize,
            'min_page_size' => $minPageSize,
            'max_page_size' => $maxPageSize,
            'fetch_jobs' => $lastPage,
            'url' => $url,
        ]);

        $this->dispatchPageJobs($lastPage, $storeId, $storeDocument);
    }

    // ─── Datas por loja ──────────────────────────────────────────────────────

    /**
     * Resolve o intervalo de datas efetivo para uma loja específica.
     * Se a loja já tem registros na tabela alvo (ou na pivot), usa ontem.
     * Caso contrário, usa initial_days atrás (ou null se initial_days = 0).
     *
     * @param  array<string, mixed>  $pathConfig
     * @param  array{id: string, document: string}|null  $store
     * @return array{?string, ?string}
     */
    private function resolveEffectiveDates(TenantIntegration $integration, array $pathConfig, ?array $store): array
    {
        $dateFields = (array) data_get($pathConfig, 'date_fields', []);
        $initialDays = (int) data_get($pathConfig, 'initial_days', 0);
        $targetTable = (string) data_get($pathConfig, 'target_table', '');
        $pivotTables = (array) data_get($pathConfig, 'pivot_tables', []);
        $storeId = data_get($store, 'id');

        $hasRecords = $this->storeHasRecords($integration, $targetTable, $pivotTables, $storeId);

        $startIfEmpty = $initialDays > 0 ? now()->subDays($initialDays)->toDateString() : null;
        $effectiveDateStart = $hasRecords ? now()->subDay()->toDateString() : $startIfEmpty;

        if (isset($dateFields['start']) && isset($dateFields['end'])) {
            return [$effectiveDateStart, now()->toDateString()];
        }

        if (isset($dateFields['changed_since'])) {
            return [$effectiveDateStart, null];
        }

        return [null, null];
    }

    /**
     * Verifica se já existem registros para uma loja específica.
     * Para tabelas com pivot (ex: product_store), verifica nela com store_id.
     * Para tabelas com store_id direto (ex: sales), filtra pela coluna.
     *
     * @param  array<int, array<string, mixed>>  $pivotTables
     */
    private function storeHasRecords(
        TenantIntegration $integration,
        string $targetTable,
        array $pivotTables,
        ?string $storeId,
    ): bool {
        if ($targetTable === '' || $integration->tenant === null) {
            return false;
        }

        return (bool) $integration->tenant->execute(function () use ($targetTable, $pivotTables, $storeId): bool {
            if (! Schema::connection('tenant')->hasTable($targetTable)) {
                return false;
            }

            // Verifica pivot table primeiro (ex: product_store com store_id)
            foreach ($pivotTables as $pivot) {
                $pivotTable = (string) data_get($pivot, 'table', '');

                if ($pivotTable === '' || ! Schema::connection('tenant')->hasTable($pivotTable)) {
                    continue;
                }

                $query = DB::connection('tenant')->table($pivotTable);

                if ($storeId !== null) {
                    $query->where('store_id', $storeId);
                }

                return $query->exists();
            }

            // Verifica tabela principal com store_id (ex: sales)
            $query = DB::connection('tenant')->table($targetTable);

            if ($storeId !== null && Schema::connection('tenant')->hasColumn($targetTable, 'store_id')) {
                $query->where('store_id', $storeId);
            }

            return $query->exists();
        });
    }

    // ─── Carregamento ────────────────────────────────────────────────────────

    private function loadIntegration(): ?TenantIntegration
    {
        $integration = TenantIntegration::query()
            ->with(['api', 'tenant'])
            ->whereKey($this->integrationId)
            ->first();

        if ($integration === null || $integration->api === null) {
            Log::warning('DiscoverIntegrationPagesJob: integração ou API não encontrada', [
                'integration_id' => $this->integrationId,
            ]);

            return null;
        }

        return $integration;
    }

    private function resolvePathConfig(IntegrationApi $api): ?array
    {
        $pathConfig = data_get($api->requests ?? [], "paths.{$this->pathKey}");

        if (! is_array($pathConfig)) {
            Log::warning('DiscoverIntegrationPagesJob: path não encontrado na API', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
            ]);

            return null;
        }

        return $pathConfig;
    }

    private function buildUrl(array $config, array $pathConfig): string
    {
        $baseUrl = (string) data_get($config, 'connection.base_url', '');
        $fallbackPath = (string) data_get($pathConfig, 'fallback_path', '');

        return rtrim($baseUrl, '/').$fallbackPath;
    }

    // ─── Paginação ───────────────────────────────────────────────────────────

    /** @param array<string, mixed> $responseData */
    private function readLastPage(array $responseData, array $responseMeta): int
    {
        $path = (string) data_get($responseMeta, 'pagination.last_page_path', '');

        return $path !== '' ? (int) data_get($responseData, $path, 1) : 1;
    }

    /** @param array<string, mixed> $pathConfig */
    private function applyMaxPageLimit(int $lastPage, array $pathConfig): int
    {
        $maxPage = (int) data_get($pathConfig, 'max_page', 0);
        $lastPage = max(1, $lastPage);

        if ($maxPage <= 0) {
            return $lastPage;
        }

        if ($maxPage >= $lastPage) {
            return $lastPage;
        }

        return $maxPage;
    }

    // ─── Dispatch ────────────────────────────────────────────────────────────

    private function dispatchPageJobs(int $lastPage, ?string $storeId, ?string $storeDocument): void
    {
        for ($page = 1; $page <= $lastPage; $page++) {
            FetchIntegrationPageJob::dispatch(
                $this->integrationId, $this->pathKey, $page,
                $this->dateStart, $this->dateEnd, $storeId, $storeDocument,
            );
        }
    }

    // ─── Horizon tags ────────────────────────────────────────────────────────

    /** @return array<int, string> */
    public function tags(): array
    {
        return [
            'integration',
            'discover',
            "integration:{$this->integrationId}",
            "path:{$this->pathKey}",
        ];
    }
}
