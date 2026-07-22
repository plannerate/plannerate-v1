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
     * Reconcilia os runs ainda 'running' da data de referência **e os que
     * ficaram para trás** em datas anteriores.
     *
     * A varredura das datas antigas existe porque o par import (06:00) →
     * post-import (07:30) só cobre o mesmo dia: uma importação longa que
     * atravessa a meia-noite abre runs com a data de ontem, o post-import do dia
     * seguinte procura pela data de hoje e aqueles ficam 'running' para sempre —
     * aparecendo na UI como se ainda estivessem rodando.
     *
     * É seguro fechar os antigos aqui porque este método roda **depois da
     * barreira de filas** do sync:post-import: com as filas vazias, run de dia
     * anterior está necessariamente encerrado.
     *
     * @return array{reconciled: int, complete: int, partial: int}
     */
    public static function reconcileForDate(string $referenceDate): array
    {
        $runs = IntegrationImportRun::query()
            ->where('status', 'running')
            ->where('reference_date', '<=', $referenceDate)
            ->get();

        $summary = ['reconciled' => 0, 'complete' => 0, 'partial' => 0];

        foreach ($runs as $run) {
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
                'reference_date' => $run->reference_date,
                'expected_units' => $run->expected_units,
                'covered_units' => $covered,
                'persisted_records' => $run->persisted_records,
            ]);
        }

        return $status;
    }
}
