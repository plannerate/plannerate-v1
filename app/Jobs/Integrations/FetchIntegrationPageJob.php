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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Multitenancy\Jobs\NotTenantAware;

/**
 * Busca uma página específica da API e salva a resposta bruta em disco
 * para processamento assíncrono pelo ProcessPageResponseJob.
 */
class FetchIntegrationPageJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly string $integrationId,
        public readonly string $pathKey,
        public readonly int $page,
        public readonly ?string $dateStart = null,
        public readonly ?string $dateEnd = null,
        public readonly ?string $storeId = null,
        public readonly ?string $storeDocument = null,
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
            ->build($this->dateStart, $this->dateEnd, $this->storeDocument, page: $this->page);

        $response = (new IntegrationHttpClient($config))
            ->call($method, $url, $payload);

        if (! $response->successful()) {
            Log::error('FetchIntegrationPageJob: falha na chamada HTTP', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'page' => $this->page,
                'store_id' => $this->storeId,
                'status' => $response->status(),
                'url' => $url,
            ]);

            $this->fail(sprintf('HTTP %d ao acessar %s (página %d)', $response->status(), $url, $this->page));

            return;
        }

        $filePath = $this->saveResponse($response->body());

        Log::info('FetchIntegrationPageJob: resposta salva', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'page' => $this->page,
            'store_id' => $this->storeId,
            'file' => $filePath,
        ]);

        ProcessPageResponseJob::dispatch(
            $this->integrationId, $this->pathKey, $this->storeId, $filePath,
        );
    }

    // ─── Persistência da resposta ─────────────────────────────────────────────

    private function saveResponse(string $body): string
    {
        $path = 'imports/'.Str::ulid().'.json';
        Storage::disk('local')->put($path, $body);

        return $path;
    }

    // ─── Carregamento ────────────────────────────────────────────────────────

    private function loadIntegration(): ?TenantIntegration
    {
        $integration = TenantIntegration::query()
            ->with('api')
            ->whereKey($this->integrationId)
            ->first();

        if ($integration === null || $integration->api === null) {
            Log::warning('FetchIntegrationPageJob: integração ou API não encontrada', [
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
            Log::warning('FetchIntegrationPageJob: path não encontrado na API', [
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

    // ─── Horizon tags ────────────────────────────────────────────────────────

    /** @return array<int, string> */
    public function tags(): array
    {
        return [
            'integration',
            'fetch',
            "integration:{$this->integrationId}",
            "path:{$this->pathKey}",
            "page:{$this->page}",
        ];
    }
}
