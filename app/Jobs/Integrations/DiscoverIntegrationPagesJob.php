<?php

namespace App\Jobs\Integrations;

use App\Models\IntegrationApi;
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
 * e despacha um FetchIntegrationPageJob por página.
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

        $url = $this->buildUrl($config, $pathConfig);
        $method = strtolower((string) data_get($requests, 'method', 'get'));

        $payload = (new IntegrationPayloadBuilder($config, $requests, $pathConfig))
            ->build($this->dateStart, $this->dateEnd);

        $response = (new IntegrationHttpClient($config))
            ->call($method, $url, $payload);

        if (! $response->successful()) {
            Log::error('DiscoverIntegrationPagesJob: falha na chamada HTTP', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'status' => $response->status(),
                'url' => $url,
            ]);

            $this->fail(sprintf('HTTP %d ao acessar %s', $response->status(), $url));

            return;
        }

        Log::info('DiscoverIntegrationPagesJob: resposta HTTP recebida', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'status' => $response->status(),
            'url' => $url,
        ]);

        $responseData = $response->json();
        $responseMeta = $api->response ?? [];

        $lastPage = $this->readLastPage($responseData, $responseMeta);

        $this->logDiscovery($lastPage, $responseData, $responseMeta);

        $this->dispatchPageJobs($lastPage);
    }

    // ─── Carregamento ────────────────────────────────────────────────────────

    private function loadIntegration(): ?TenantIntegration
    {
        $integration = TenantIntegration::query()
            ->with('api')
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

    // ─── Dispatch ────────────────────────────────────────────────────────────

    private function dispatchPageJobs(int $lastPage): void
    {
        for ($page = 1; $page <= $lastPage; $page++) {
            // FetchIntegrationPageJob::dispatch($this->integrationId, $this->pathKey, $page, $this->dateStart, $this->dateEnd);
            Log::info("DiscoverIntegrationPagesJob: dispatching FetchIntegrationPageJob for page {$page}", [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'page' => $page,
            ]);
        }
    }

    // ─── Log ─────────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $responseData */
    private function logDiscovery(int $lastPage, array $responseData, array $responseMeta): void
    {
        $totalPath = (string) data_get($responseMeta, 'pagination.total_path', '');
        $total = $totalPath !== '' ? (int) data_get($responseData, $totalPath, 0) : 0;

        Log::info('DiscoverIntegrationPagesJob: descoberta concluída', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'last_page' => $lastPage,
            'total_records' => $total,
            'date_start' => $this->dateStart,
            'date_end' => $this->dateEnd,
        ]);
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
