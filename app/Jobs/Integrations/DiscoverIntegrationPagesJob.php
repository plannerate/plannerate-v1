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
use Illuminate\Support\Facades\Log;
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
        $this->onQueue('imports');
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

        $minPageSize = max(1, (int) data_get($requests, 'min_page_size', 1));
        $maxPageSize = max(1, (int) data_get($requests, 'max_page_size', 1000));

        $stores = $this->loadStores($integration, $requests);

        foreach ($stores as $store) {
            $this->discoverForStore($api, $config, $requests, $pathConfig, $minPageSize, $maxPageSize, $store);
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
    ): void {
        $storeDocument = data_get($store, 'document');
        $storeId = data_get($store, 'id');

        $url = $this->buildUrl($config, $pathConfig);
        $method = strtolower((string) data_get($requests, 'method', 'get'));

        $payload = (new IntegrationPayloadBuilder($config, $requests, $pathConfig))
            ->build($this->dateStart, $this->dateEnd, $storeDocument, useMinPageSize: true);

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

        $this->dispatchPageJobs($lastPage, $storeId, $storeDocument);
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

        if ($maxPage <= 0) {
            return max(1, $lastPage);
        }

        return max(1, min($lastPage, $maxPage));
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
