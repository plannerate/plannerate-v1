<?php

namespace App\Services\Integrations\Discovery;

use App\Jobs\Integrations\FetchIntegrationPageJob;
use App\Models\TenantIntegration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
     * @param  array<string, mixed>  $pathConfig
     * @param  array{id: string, document: string}|null  $store
     */
    public function discover(TenantIntegration $integration, array $pathConfig, ?array $store): void
    {
        $storeId = data_get($store, 'id');
        $storeDocument = data_get($store, 'document');

        $missingDays = $this->resolveMissingDays($integration, $pathConfig, $store);

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

        $this->dispatchJobs($missingDays, $storeId, $storeDocument);
    }

    /**
     * Gera [hoje, ontem, ..., hoje − initial_days] e remove os dias já no banco.
     *
     * @param  array<string, mixed>  $pathConfig
     * @param  array{id: string, document: string}|null  $store
     * @return array<int, string>
     */
    private function resolveMissingDays(TenantIntegration $integration, array $pathConfig, ?array $store): array
    {
        $initialDays = (int) data_get($pathConfig, 'initial_days', 0);
        $lastDateColumn = (string) data_get($pathConfig, 'last_date_column', '');
        $targetTable = (string) data_get($pathConfig, 'target_table', '');
        $storeId = data_get($store, 'id');

        $today = now()->toDateString();
        $rangeStart = now()->subDays($initialDays)->toDateString();

        $allDates = [];
        $cursor = now();

        for ($i = 0; $i <= $initialDays; $i++) {
            $allDates[] = $cursor->toDateString();
            $cursor = $cursor->subDay();
        }

        $existingDates = $this->getExistingDates(
            $integration, $targetTable, $storeId, $lastDateColumn, $rangeStart, $today,
        );

        return array_values(array_diff($allDates, $existingDates));
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
    private function dispatchJobs(array $missingDays, ?string $storeId, ?string $storeDocument): void
    {
        $delaySeconds = (int) config('integrations.fetch_delay', 3);

        foreach ($missingDays as $index => $day) {
            FetchIntegrationPageJob::dispatch(
                $this->integrationId, $this->pathKey, 1,
                $day, $day, $storeId, $storeDocument,
                autoPage: true,
            )->delay(now()->addSeconds($index * $delaySeconds));
        }
    }
}
