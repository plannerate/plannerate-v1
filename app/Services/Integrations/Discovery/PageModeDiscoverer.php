<?php

namespace App\Services\Integrations\Discovery;

use App\Jobs\Integrations\FetchIntegrationPageJob;
use App\Models\IntegrationApi;
use App\Models\TenantIntegration;
use App\Services\Integrations\IntegrationHttpClient;
use App\Services\Integrations\IntegrationPayloadBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PageModeDiscoverer
{
    public function __construct(
        private readonly string $integrationId,
        private readonly string $pathKey,
    ) {}

    /**
     * Faz a chamada HTTP para descobrir o total de páginas e despacha um
     * FetchIntegrationPageJob por página × loja.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $requests
     * @param  array<string, mixed>  $pathConfig
     * @param  array{id: string, document: string}|null  $store
     * @param  bool  $forceFull  Ignora o filtro incremental (changed_since) e busca o catálogo completo
     *
     * @throws RuntimeException quando a chamada HTTP falha
     */
    public function discover(
        TenantIntegration $integration,
        IntegrationApi $api,
        array $config,
        array $requests,
        array $pathConfig,
        ?array $store,
        bool $forceFull = false,
    ): void {
        $storeDocument = data_get($store, 'document');
        $storeId = data_get($store, 'id');

        $minPageSize = max(1, (int) (data_get($pathConfig, 'min_page_size') ?? data_get($requests, 'min_page_size', 1)));
        $maxPageSize = max(1, (int) (data_get($pathConfig, 'max_page_size') ?? data_get($requests, 'max_page_size', 1000)));

        [$effectiveDateStart, $effectiveDateEnd] = $this->resolveEffectiveDates($integration, $pathConfig, $store, $forceFull);

        $url = $this->buildUrl($config, $pathConfig);
        $method = strtolower((string) data_get($requests, 'method', 'get'));

        $payload = (new IntegrationPayloadBuilder($config, $requests, $pathConfig))
            ->build($effectiveDateStart, $effectiveDateEnd, $storeDocument, useMinPageSize: true);

        Log::info('PageModeDiscoverer: iniciando descoberta de páginas', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'store_id' => $storeId,
            'force_full' => $forceFull,
            'payload' => $payload,
        ]);

        $response = (new IntegrationHttpClient($config))
            ->call($method, $url, $payload);

        if (! $response->successful()) {
            Log::error('PageModeDiscoverer: falha na chamada HTTP', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'store_id' => $storeId,
                'status' => $response->status(),
                'url' => $url,
            ]);

            throw new RuntimeException(sprintf('HTTP %d ao acessar %s', $response->status(), $url));
        }

        $responseData = $response->json();
        $responseMeta = $api->response ?? [];

        $lastPageAtMinSize = $this->readLastPage($responseData, $responseMeta);
        $actualPerPage = $this->readPerPage($responseData, $responseMeta, $minPageSize);
        $lastPage = (int) ceil($lastPageAtMinSize * $actualPerPage / $maxPageSize);
        $lastPage = $this->applyMaxPageLimit($lastPage, $pathConfig);

        Log::info('PageModeDiscoverer: descoberta concluída', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'store_id' => $storeId,
            'pages_at_min_size' => $lastPageAtMinSize,
            'actual_per_page' => $actualPerPage,
            'min_page_size' => $minPageSize,
            'max_page_size' => $maxPageSize,
            'fetch_jobs' => $lastPage,
            'url' => $url,
        ]);

        $this->dispatchJobs($lastPage, $storeId, $storeDocument, $effectiveDateStart, $effectiveDateEnd);
    }

    // ─── Datas ───────────────────────────────────────────────────────────────

    /**
     * Modo chunk (chunk_days > 0 + last_date_column + target_table):
     *   banco vazio  → start = initial_days atrás, end = start + chunk_days
     *   atrasado     → start = última data, end = start + chunk_days
     *   em dia       → start = ontem, end = hoje
     *
     * Modo padrão:
     *   banco vazio  → start = initial_days atrás, end = hoje
     *   tem dados    → start = recheck_days atrás, end = hoje (janela re-buscada
     *                  para cobrir dias com fetch parcial; upsert é idempotente)
     *
     * @param  array<string, mixed>  $pathConfig
     * @param  array{id: string, document: string}|null  $store
     * @return array{?string, ?string}
     */
    private function resolveEffectiveDates(TenantIntegration $integration, array $pathConfig, ?array $store, bool $forceFull = false): array
    {
        if ($forceFull) {
            return [null, null];
        }

        $dateFields = (array) data_get($pathConfig, 'date_fields', []);
        $initialDays = (int) data_get($pathConfig, 'initial_days', 0);
        $chunkDays = (int) data_get($pathConfig, 'chunk_days', 0);
        $lastDateColumn = (string) data_get($pathConfig, 'last_date_column', '');
        $targetTable = (string) data_get($pathConfig, 'target_table', '');
        $pivotTables = (array) data_get($pathConfig, 'pivot_tables', []);
        $storeId = data_get($store, 'id');

        $useChunkMode = $chunkDays > 0 && $lastDateColumn !== '' && $targetTable !== '';

        if ($useChunkMode) {
            [$effectiveDateStart, $effectiveDateEnd] = $this->resolveChunkDates(
                $integration, $targetTable, $storeId, $initialDays, $chunkDays, $lastDateColumn,
            );
        } else {
            $hasRecords = $this->storeHasRecords($integration, $targetTable, $pivotTables, $storeId);
            $startIfEmpty = $initialDays > 0 ? now()->subDays($initialDays)->toDateString() : null;
            $effectiveDateStart = $hasRecords ? $this->incrementalStartDate() : $startIfEmpty;
            $effectiveDateEnd = now()->toDateString();
        }

        if (isset($dateFields['start']) && isset($dateFields['end'])) {
            return [$effectiveDateStart, $effectiveDateEnd];
        }

        if (isset($dateFields['changed_since'])) {
            return [$effectiveDateStart, null];
        }

        return [null, null];
    }

    /** @return array{?string, string} */
    private function resolveChunkDates(
        TenantIntegration $integration,
        string $targetTable,
        ?string $storeId,
        int $initialDays,
        int $chunkDays,
        string $lastDateColumn,
    ): array {
        $lastDate = $this->getLastRecordDate($integration, $targetTable, $storeId, $lastDateColumn);
        $today = now()->toDateString();

        if ($lastDate === null) {
            $start = $initialDays > 0 ? now()->subDays($initialDays)->toDateString() : null;
        } else {
            $lastCarbon = Carbon::parse($lastDate);

            if ($lastCarbon->gte(now()->subDays(2))) {
                return [$this->incrementalStartDate(), $today];
            }

            $start = $lastCarbon->toDateString();
        }

        if ($start === null) {
            return [null, $today];
        }

        $end = Carbon::parse($start)->addDays($chunkDays)->toDateString();

        return [$start, min($end, $today)];
    }

    /**
     * Início do sync incremental quando o tenant já tem dados: volta
     * recheck_days em vez de só "ontem", para re-cobrir dias cujo fetch pode
     * ter sido parcial. A re-busca é idempotente (upsert por id determinístico).
     */
    private function incrementalStartDate(): string
    {
        $recheckDays = max(1, (int) config('integrations.recheck_days', 3));

        return now()->subDays($recheckDays)->toDateString();
    }

    private function getLastRecordDate(
        TenantIntegration $integration,
        string $targetTable,
        ?string $storeId,
        string $lastDateColumn,
    ): ?string {
        if ($integration->tenant === null) {
            return null;
        }

        return $integration->tenant->execute(function () use ($targetTable, $storeId, $lastDateColumn): ?string {
            if (! Schema::connection('tenant')->hasTable($targetTable)) {
                return null;
            }

            $query = DB::connection('tenant')
                ->table($targetTable)
                ->selectRaw("MAX({$lastDateColumn}) as last_date");

            if ($storeId !== null && Schema::connection('tenant')->hasColumn($targetTable, 'store_id')) {
                $query->where('store_id', $storeId);
            }

            return $query->value('last_date');
        });
    }

    /**
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

            $query = DB::connection('tenant')->table($targetTable);

            if ($storeId !== null && Schema::connection('tenant')->hasColumn($targetTable, 'store_id')) {
                $query->where('store_id', $storeId);
            }

            return $query->exists();
        });
    }

    // ─── Paginação ───────────────────────────────────────────────────────────

    /** @param array<string, mixed> $responseData */
    private function readLastPage(array $responseData, array $responseMeta): int
    {
        $path = (string) data_get($responseMeta, 'pagination.last_page_path', '');

        return $path !== '' ? (int) data_get($responseData, $path, 1) : 1;
    }

    /**
     * Lê o per_page real da resposta para corrigir a fórmula de paginação
     * quando a API ignora o min_page_size configurado.
     *
     * @param  array<string, mixed>  $responseData
     * @param  array<string, mixed>  $responseMeta
     */
    private function readPerPage(array $responseData, array $responseMeta, int $defaultPageSize): int
    {
        $path = (string) data_get($responseMeta, 'pagination.per_page_path', '');

        if ($path === '') {
            return $defaultPageSize;
        }

        $perPage = (int) data_get($responseData, $path, 0);

        return $perPage > 0 ? $perPage : $defaultPageSize;
    }

    /** @param array<string, mixed> $pathConfig */
    private function applyMaxPageLimit(int $lastPage, array $pathConfig): int
    {
        $maxPage = (int) data_get($pathConfig, 'max_page', 0);
        $lastPage = max(1, $lastPage);

        if ($maxPage <= 0 || $maxPage >= $lastPage) {
            return $lastPage;
        }

        return $maxPage;
    }

    // ─── Dispatch ────────────────────────────────────────────────────────────

    private function dispatchJobs(
        int $lastPage,
        ?string $storeId,
        ?string $storeDocument,
        ?string $dateStart,
        ?string $dateEnd,
    ): void {
        $delaySeconds = (int) config('integrations.fetch_delay', 3);

        for ($page = 1; $page <= $lastPage; $page++) {
            FetchIntegrationPageJob::dispatch(
                $this->integrationId, $this->pathKey, $page,
                $dateStart, $dateEnd, $storeId, $storeDocument,
                autoPage: false,
                knownLastPage: $lastPage,
            )->delay(now()->addSeconds(($page - 1) * $delaySeconds));
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $config */
    private function buildUrl(array $config, array $pathConfig): string
    {
        $baseUrl = (string) data_get($config, 'connection.base_url', '');
        $fallbackPath = (string) data_get($pathConfig, 'fallback_path', '');

        return rtrim($baseUrl, '/').$fallbackPath;
    }
}
