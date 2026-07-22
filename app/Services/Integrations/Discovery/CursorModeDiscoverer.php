<?php

namespace App\Services\Integrations\Discovery;

use App\Jobs\Integrations\FetchIntegrationPageJob;
use App\Models\IntegrationImportRun;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\IntegrationPaginationMode;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Descoberta para `pagination_mode: cursor`.
 *
 * Não há o que sondar: a API não informa total de páginas nem last_page. Este
 * discoverer apenas **semeia a cadeia** — despacha 1 job com o cursor inicial
 * por loja, e cada FetchIntegrationPageJob encadeia o próximo a partir do id do
 * último item recebido.
 *
 * Cobertura do run: `expected_units = 1` (a semente) e só a semente incrementa
 * `covered_units` (o FetchIntegrationPageJob pula páginas ≥ 2 no modo
 * encadeado). O run mede "a cadeia começou", não "todas as páginas vieram" —
 * o número de páginas é desconhecido por definição neste modo.
 *
 * Paths com janela de datas (vendas) NÃO passam por aqui: o DailyModeDiscoverer
 * tem precedência e já semeia uma cadeia por loja × dia, o que dá paralelismo
 * entre dias e cobertura por dia.
 */
class CursorModeDiscoverer
{
    public function __construct(
        private readonly string $integrationId,
        private readonly string $pathKey,
    ) {}

    /**
     * @param  array<string, mixed>  $requests
     * @param  array<string, mixed>  $pathConfig
     */
    public function isApplicable(array $requests, array $pathConfig): bool
    {
        return IntegrationPaginationMode::isCursor($requests, $pathConfig);
    }

    /**
     * @param  array<string, mixed>  $pathConfig
     * @param  array{id: string, document: string}|null  $store
     */
    public function discover(TenantIntegration $integration, array $pathConfig, ?array $store, bool $forceFull = false): void
    {
        $storeId = data_get($store, 'id');
        $storeDocument = data_get($store, 'document');
        $initialCursor = IntegrationPaginationMode::initialCursor($pathConfig);

        Log::info('CursorModeDiscoverer: semeando cadeia de cursor', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'store_id' => $storeId,
            'initial_cursor' => $initialCursor,
            'force_full' => $forceFull,
        ]);

        $runId = $this->recordRun($integration, $store, $forceFull);

        FetchIntegrationPageJob::dispatch(
            $this->integrationId, $this->pathKey, 1,
            null, null, $storeId, $storeDocument,
            autoPage: false,
            knownLastPage: null,
            runId: $runId,
            cursor: $initialCursor,
        );
    }

    /**
     * Defensivo: falha no tracking nunca impede a importação.
     *
     * @param  array{id: string, document: string}|null  $store
     */
    private function recordRun(TenantIntegration $integration, ?array $store, bool $forceFull): ?string
    {
        try {
            return IntegrationImportRun::startRun([
                'tenant_id' => (string) $integration->tenant_id,
                'integration_id' => (string) $integration->id,
                'path_key' => $this->pathKey,
                'store_id' => data_get($store, 'id'),
                'mode' => IntegrationPaginationMode::CURSOR,
                'reference_date' => now()->toDateString(),
                'expected_units' => 1,
                'expected_dates' => null,
                'force_full' => $forceFull,
            ])->id;
        } catch (Throwable $e) {
            Log::warning('CursorModeDiscoverer: falha ao registrar import run', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
