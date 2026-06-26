<?php

namespace App\Support\Workflow;

use App\Models\Planogram;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Regras de agendamento da Revisão Periódica de um planograma.
 *
 * O vencimento da revisão é calculado como:
 *   periodic_review_due_at = completed_at + (end_date − start_date)
 *
 * ou seja, o planograma fica "no ar" pelo mesmo intervalo do seu período de
 * análise antes de ser promovido automaticamente para Revisão Periódica.
 */
class PeriodicReviewSchedule
{
    /**
     * Calcula a data de vencimento da Revisão Periódica.
     *
     * Retorna `null` (não agenda) quando o planograma não tem datas válidas:
     * - `start_date`/`end_date`/`completed_at` ausentes; ou
     * - `end_date` anterior a `start_date` (datas invertidas — loga aviso).
     */
    public static function computeDueAt(Planogram $planogram): ?CarbonInterface
    {
        $start = $planogram->start_date;
        $end = $planogram->end_date;
        $completedAt = $planogram->completed_at;

        if ($start === null || $end === null || $completedAt === null) {
            return null;
        }

        if ($end->lessThan($start)) {
            Log::warning('Revisão periódica não agendada: end_date anterior a start_date.', [
                'planogram_id' => $planogram->getKey(),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]);

            return null;
        }

        $periodLength = $start->diffAsCarbonInterval($end);

        return $completedAt->copy()->add($periodLength);
    }
}
