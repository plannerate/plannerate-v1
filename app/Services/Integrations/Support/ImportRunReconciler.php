<?php

namespace App\Services\Integrations\Support;

use App\Models\IntegrationImportRun;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reconcilia os runs de importação DEPOIS que as filas esvaziaram (chamado pelo
 * sync:post-import). Marca complete/partial comparando o que foi FETCHADO
 * (covered_units, incrementado por cada fetch concluído, mesmo com zero
 * registros) vs. o planejado (expected_units).
 *
 * Cobertura por fetch-concluído (não por dado-existente): um dia sem venda
 * (feriado) teve o fetch executado → coberto, sem falso-positivo de parcial.
 * Ler após a barreira de filas evita race (todos os fetches já rodaram).
 * Runs parciais viram Log::warning.
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
        $covered = (int) $run->covered_units;
        $status = ($run->expected_units > 0 && $covered >= $run->expected_units) ? 'complete' : 'partial';

        $run->update([
            'status' => $status,
            'reconciled_at' => now(),
        ]);

        if ($status === 'partial') {
            Log::warning('ImportRunReconciler: import run parcial (nem todo dia/página esperado foi fetchado)', [
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
}
