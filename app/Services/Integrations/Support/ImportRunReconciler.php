<?php

namespace App\Services\Integrations\Support;

use App\Models\IntegrationImportRun;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reconcilia os runs de importação DEPOIS que as filas esvaziaram (chamado pelo
 * sync:post-import). Confere a cobertura real contra o plano do run e marca
 * complete/partial — sinal de conclusão sem contador ao vivo (que daria
 * falso-completo sob autoPage). Runs parciais viram Log::warning.
 */
class ImportRunReconciler
{
    /**
     * Reconcilia todos os runs ainda 'running' de uma data de referência.
     *
     * @return array{reconciled: int, complete: int, partial: int}
     */
    public static function reconcileForDate(string $referenceDate): array
    {
        $summary = ['reconciled' => 0, 'complete' => 0, 'partial' => 0];

        foreach (IntegrationImportRun::query()->runningOn($referenceDate)->get() as $run) {
            try {
                $status = self::reconcileRun($run);
                $summary['reconciled']++;
                $summary[$status]++;
            } catch (Throwable $e) {
                Log::warning('ImportRunReconciler: falha ao reconciliar run', [
                    'run_id' => $run->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    private static function reconcileRun(IntegrationImportRun $run): string
    {
        $covered = self::coveredUnits($run);
        $status = ($run->expected_units > 0 && $covered >= $run->expected_units) ? 'complete' : 'partial';

        $run->update([
            'covered_units' => $covered,
            'status' => $status,
            'reconciled_at' => now(),
        ]);

        if ($status === 'partial') {
            Log::warning('ImportRunReconciler: import run parcial (dado esperado não chegou)', [
                'run_id' => $run->id,
                'tenant_id' => $run->tenant_id,
                'integration_id' => $run->integration_id,
                'path' => $run->path_key,
                'store_id' => $run->store_id,
                'expected_units' => $run->expected_units,
                'covered_units' => $covered,
                'persisted_records' => $run->persisted_records,
            ]);
        }

        return $status;
    }

    /**
     * Cobertura confirmada:
     * - daily (tem expected_dates): dias esperados que têm ≥1 linha no tenant.
     * - page (sem datas): não dá pra verificar por página → expected se
     *   persistiu algo, 0 caso contrário.
     */
    private static function coveredUnits(IntegrationImportRun $run): int
    {
        $expectedDates = (array) $run->expected_dates;

        if ($expectedDates === []) {
            return $run->persisted_records > 0 ? $run->expected_units : 0;
        }

        $tenant = Tenant::find($run->tenant_id);
        $integration = TenantIntegration::with('api')->find($run->integration_id);

        if (! $tenant instanceof Tenant || $integration === null) {
            return 0;
        }

        $targetTable = (string) data_get($integration->api?->requests ?? [], "paths.{$run->path_key}.target_table", 'sales');

        return (int) $tenant->execute(function () use ($targetTable, $run, $expectedDates): int {
            if (! Schema::connection('tenant')->hasTable($targetTable)
                || ! Schema::connection('tenant')->hasColumn($targetTable, 'sale_date')) {
                return 0;
            }

            $query = DB::connection('tenant')->table($targetTable)
                ->where('tenant_id', $run->tenant_id)
                ->whereIn('sale_date', $expectedDates);

            if ($run->store_id !== null && Schema::connection('tenant')->hasColumn($targetTable, 'store_id')) {
                $query->where('store_id', $run->store_id);
            }

            $found = $query->distinct()->pluck('sale_date')
                ->map(fn (mixed $d): string => Carbon::parse((string) $d)->toDateString())
                ->unique();

            return collect($expectedDates)->intersect($found)->count();
        });
    }
}
