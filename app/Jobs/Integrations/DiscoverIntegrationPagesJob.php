<?php

namespace App\Jobs\Integrations;

use App\Models\IntegrationApi;
use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\Discovery\DailyModeDiscoverer;
use App\Services\Integrations\Discovery\PageModeDiscoverer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\Multitenancy\Jobs\NotTenantAware;

/**
 * Determina o modo de importação (diário ou por página) e delega
 * a descoberta e o despacho de jobs para o service correspondente.
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
        public readonly bool $forceFull = false,
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

        $dailyDiscoverer = new DailyModeDiscoverer($this->integrationId, $this->pathKey);
        $pageDiscoverer = new PageModeDiscoverer($this->integrationId, $this->pathKey);

        foreach ($this->loadStores($integration, $requests) as $store) {
            if ($dailyDiscoverer->isApplicable($pathConfig)) {
                $dailyDiscoverer->discover($integration, $pathConfig, $store, $this->forceFull);
            } else {
                try {
                    $pageDiscoverer->discover($integration, $api, $config, $requests, $pathConfig, $store, $this->forceFull);
                } catch (RuntimeException $e) {
                    $this->fail($e->getMessage());

                    return;
                }
            }
        }
    }

    // ─── Lojas ───────────────────────────────────────────────────────────────

    /**
     * Se a API não exige filtro por loja, retorna [null] (uma iteração sem store).
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

    // ─── Horizon tags ────────────────────────────────────────────────────────

    /** @return array<int, string> */
    public function tags(): array
    {
        return [
            'integration',
            'discover',
            "integration:{$this->integrationId}",
            "path:{$this->pathKey}",
            ...($this->forceFull ? ['full-backfill'] : []),
        ];
    }
}
