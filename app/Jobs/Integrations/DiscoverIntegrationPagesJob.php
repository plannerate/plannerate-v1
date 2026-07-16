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

    /**
     * A descoberta em modo página faz 1 sondagem HTTP síncrona POR LOJA (cada
     * uma pode levar até integrations.timeout = 60s). Precisa caber várias
     * sondagens, mas ficar abaixo do timeout do supervisor imports-fetch (180s).
     */
    public int $timeout = 170;

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

        $stores = $this->loadStores($integration, $requests);
        $failedStores = [];

        foreach ($stores as $store) {
            if ($dailyDiscoverer->isApplicable($pathConfig)) {
                $dailyDiscoverer->discover($integration, $pathConfig, $store, $this->forceFull);

                continue;
            }

            // Checkpoint por loja: a falha de uma sondagem não pode abortar a
            // descoberta das lojas restantes.
            try {
                $pageDiscoverer->discover($integration, $api, $config, $requests, $pathConfig, $store, $this->forceFull);
            } catch (RuntimeException $e) {
                $failedStores[] = (string) (data_get($store, 'id') ?? 'sem-loja');

                Log::error('DiscoverIntegrationPagesJob: falha na descoberta da loja; continuando nas demais', [
                    'integration_id' => $this->integrationId,
                    'path_key' => $this->pathKey,
                    'store_id' => data_get($store, 'id'),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Só re-tenta quando TODAS falharam: nada foi despachado, então o retry
        // não duplica fan-out. Com falha parcial, as lojas que passaram já
        // despacharam seus jobs — re-tentar duplicaria as buscas delas.
        if ($failedStores !== [] && count($failedStores) === count($stores)) {
            throw new RuntimeException(sprintf(
                'Descoberta falhou em todas as %d loja(s): %s',
                count($stores),
                implode(', ', $failedStores),
            ));
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
