<?php

namespace App\Services\Integrations\Discovery;

use App\Jobs\Integrations\FetchIntegrationPageJob;
use App\Models\IntegrationImportRun;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\IntegrationPaginationMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DailyModeDiscoverer
{
    public function __construct(
        private readonly string $integrationId,
        private readonly string $pathKey,
    ) {}

    /**
     * Ativa quando initial_days > 0 + last_date_column + target_table estão configurados.
     *
     * @param  array<string, mixed>  $pathConfig
     */
    public function isApplicable(array $pathConfig): bool
    {
        return (int) data_get($pathConfig, 'initial_days', 0) > 0
            && (string) data_get($pathConfig, 'last_date_column', '') !== ''
            && (string) data_get($pathConfig, 'target_table', '') !== '';
    }

    /**
     * Encontra os dias sem registro e despacha um FetchIntegrationPageJob por dia.
     *
     * Com $forceFull, ignora o filtro incremental (dias já no banco) e refaz
     * a busca de todos os dias do range — usado pelo backfill único.
     *
     * @param  array<string, mixed>  $pathConfig
     * @param  array{id: string, document: string}|null  $store
     * @param  array<string, mixed>  $requests  IntegrationApi->requests (para o modo de paginação)
     */
    public function discover(TenantIntegration $integration, array $pathConfig, ?array $store, bool $forceFull = false, array $requests = []): void
    {
        $storeId = data_get($store, 'id');
        $storeDocument = data_get($store, 'document');

        $missingDays = $this->resolveMissingDays($integration, $pathConfig, $store, $forceFull);

        if ($missingDays === []) {
            Log::info('DailyModeDiscoverer: nenhum dia faltando', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'store_id' => $storeId,
            ]);

            return;
        }

        Log::info('DailyModeDiscoverer: dias faltando encontrados', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'store_id' => $storeId,
            'missing_count' => count($missingDays),
            'newest' => $missingDays[0] ?? null,
            'oldest' => $missingDays[array_key_last($missingDays)] ?? null,
        ]);

        $runId = $this->recordRun($integration, $store, $missingDays, $forceFull);

        // Cursor mode: cada dia é uma cadeia própria, semeada no cursor inicial.
        // Sem isso o placeholder {cursor} da URL viria vazio.
        $initialCursor = IntegrationPaginationMode::isCursor($requests, $pathConfig)
            ? IntegrationPaginationMode::initialCursor($pathConfig)
            : null;

        $this->dispatchJobs($missingDays, $storeId, $storeDocument, $runId, $initialCursor);
    }

    /**
     * Abre o run do ciclo (esperado = dias faltantes). Defensivo: falha no
     * tracking nunca impede a descoberta/importação.
     *
     * @param  array{id: string, document: string}|null  $store
     * @param  array<int, string>  $missingDays
     */
    private function recordRun(TenantIntegration $integration, ?array $store, array $missingDays, bool $forceFull): ?string
    {
        try {
            return IntegrationImportRun::startRun([
                'tenant_id' => (string) $integration->tenant_id,
                'integration_id' => (string) $integration->id,
                'path_key' => $this->pathKey,
                'store_id' => data_get($store, 'id'),
                'mode' => 'daily',
                'reference_date' => now()->toDateString(),
                'expected_units' => count($missingDays),
                'expected_dates' => array_values($missingDays),
                'force_full' => $forceFull,
            ])->id;
        } catch (Throwable $e) {
            Log::warning('DailyModeDiscoverer: falha ao registrar import run', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Gera [hoje − lag_days, ..., hoje − lag_days − initial_days] e remove os
     * dias já no banco. Com $forceFull, retorna o range completo sem descontar
     * os dias já no banco. Dias dentro da janela de recheck são sempre
     * re-buscados (ver applyRecheckWindow).
     *
     * `lag_days` existe porque alguns ERPs só materializam o movimento do dia
     * depois do fechamento: a RP Info responde HTTP 200 com
     * "Não localizada tabela de movimento ... para a data:<hoje>" quando o
     * import roda às 06:00. O guard trata isso como falha (correto — senão o dia
     * entraria como coberto e vazio), mas o resultado é retentativa com backoff
     * e ruído de erro todo dia. Com `lag_days: 1` a busca começa em ontem; nada
     * se perde, porque a janela de recheck re-busca os últimos dias de qualquer
     * forma.
     *
     * @param  array<string, mixed>  $pathConfig
     * @param  array{id: string, document: string}|null  $store
     * @return array<int, string>
     */
    private function resolveMissingDays(TenantIntegration $integration, array $pathConfig, ?array $store, bool $forceFull = false): array
    {
        $initialDays = (int) data_get($pathConfig, 'initial_days', 0);
        $lagDays = max(0, (int) data_get($pathConfig, 'lag_days', 0));
        $lastDateColumn = (string) data_get($pathConfig, 'last_date_column', '');
        $targetTable = (string) data_get($pathConfig, 'target_table', '');
        $storeId = data_get($store, 'id');

        $today = now()->toDateString();
        $rangeStart = now()->subDays($initialDays + $lagDays)->toDateString();

        $allDates = [];
        $cursor = now()->subDays($lagDays);

        for ($i = 0; $i <= $initialDays; $i++) {
            $allDates[] = $cursor->toDateString();
            $cursor = $cursor->subDay();
        }

        if ($forceFull) {
            return $allDates;
        }

        $existingDates = $this->getExistingDates(
            $integration, $targetTable, $storeId, $lastDateColumn, $rangeStart, $today,
        );

        return array_values(array_diff($allDates, $this->applyRecheckWindow($existingDates)));
    }

    /**
     * Remove da lista de "dias completos" os dias dentro da janela de recheck:
     * um dia com ≥1 registro pode estar incompleto (fetch caiu no meio das
     * páginas), então os últimos recheck_days são sempre re-buscados. O upsert
     * por id determinístico torna a re-busca idempotente.
     *
     * @param  array<int, string>  $existingDates
     * @return array<int, string>
     */
    private function applyRecheckWindow(array $existingDates): array
    {
        $recheckDays = max(0, (int) config('integrations.recheck_days', 3));
        $recheckCutoff = now()->subDays($recheckDays)->toDateString();

        return array_values(array_filter(
            $existingDates,
            fn (string $date): bool => $date < $recheckCutoff,
        ));
    }

    /** @return array<int, string> */
    private function getExistingDates(
        TenantIntegration $integration,
        string $targetTable,
        ?string $storeId,
        string $lastDateColumn,
        string $dateStart,
        string $dateEnd,
    ): array {
        if ($integration->tenant === null) {
            return [];
        }

        return $integration->tenant->execute(function () use ($targetTable, $storeId, $lastDateColumn, $dateStart, $dateEnd): array {
            if (! Schema::connection('tenant')->hasTable($targetTable)) {
                return [];
            }

            $query = DB::connection('tenant')
                ->table($targetTable)
                ->selectRaw("DISTINCT DATE({$lastDateColumn}) as existing_date")
                ->whereBetween($lastDateColumn, [$dateStart, $dateEnd]);

            if ($storeId !== null && Schema::connection('tenant')->hasColumn($targetTable, 'store_id')) {
                $query->where('store_id', $storeId);
            }

            return $query
                ->pluck('existing_date')
                ->map(fn (mixed $d): string => Carbon::parse($d)->toDateString())
                ->all();
        });
    }

    /** @param array<int, string> $missingDays */
    private function dispatchJobs(
        array $missingDays,
        ?string $storeId,
        ?string $storeDocument,
        ?string $runId,
        ?string $initialCursor = null,
    ): void {
        $delaySeconds = (int) config('integrations.fetch_delay', 3);

        foreach ($missingDays as $index => $day) {
            FetchIntegrationPageJob::dispatch(
                $this->integrationId, $this->pathKey, 1,
                $day, $day, $storeId, $storeDocument,
                autoPage: true,
                runId: $runId,
                cursor: $initialCursor,
            )->delay(now()->addSeconds($index * $delaySeconds));
        }
    }
}
