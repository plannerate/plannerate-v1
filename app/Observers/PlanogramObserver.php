<?php

namespace App\Observers;

use App\Enums\PlanogramLifecycleStatus;
use App\Models\Planogram;
use App\Support\Workflow\PeriodicReviewSchedule;

/**
 * Mantém o vencimento da Revisão Periódica coerente com o período do planograma.
 */
class PlanogramObserver
{
    /**
     * Recalcula `periodic_review_due_at` quando o período (`start_date`/
     * `end_date`) muda após a conclusão e antes de a revisão ser iniciada.
     *
     * Se a revisão já começou (`periodic_review_started_at` != null), o
     * vencimento fica congelado e nada é alterado.
     */
    public function updated(Planogram $planogram): void
    {
        if (! $planogram->wasChanged(['start_date', 'end_date'])) {
            return;
        }

        if ($planogram->lifecycle_status !== PlanogramLifecycleStatus::Completed) {
            return;
        }

        if ($planogram->periodic_review_started_at !== null) {
            return;
        }

        $newDueAt = PeriodicReviewSchedule::computeDueAt($planogram);

        // updateQuietly evita disparar novamente este observer (recursão).
        $planogram->updateQuietly(['periodic_review_due_at' => $newDueAt]);
    }
}
