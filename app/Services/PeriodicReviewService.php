<?php

namespace App\Services;

use App\Enums\PlanogramLifecycleStatus;
use App\Enums\WorkflowExecutionStatus;
use App\Enums\WorkflowHistoryAction;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\Tenant;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowHistory;
use App\Models\WorkflowPlanogramStep;
use App\Notifications\AppNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Automação da Revisão Periódica.
 *
 * Promove planogramas concluídos para a etapa de Revisão Periódica quando vence
 * o período de análise (`periodic_review_due_at`). A promoção reabre todas as
 * gôndolas na etapa de revisão (execuções `pending`), registra histórico de
 * sistema e notifica os responsáveis.
 */
class PeriodicReviewService
{
    public function __construct(
        private readonly WorkflowPlanogramStepService $stepService,
    ) {}

    /**
     * Planogramas elegíveis para promoção: concluídos, com vencimento já
     * atingido e que ainda não entraram em revisão (guard de idempotência).
     *
     * @return EloquentCollection<int, Planogram>
     */
    public function eligibleForPromotion(): EloquentCollection
    {
        return Planogram::query()
            ->where('lifecycle_status', PlanogramLifecycleStatus::Completed)
            ->whereNotNull('periodic_review_due_at')
            ->whereNull('periodic_review_started_at')
            ->where('periodic_review_due_at', '<=', now())
            ->get();
    }

    /**
     * Promove um planograma concluído para Revisão Periódica.
     *
     * Idempotente: o guard `periodic_review_started_at` + a transação garantem
     * que reprocessar não duplica execuções nem histórico.
     *
     * @return bool true se promoveu; false se já estava em revisão ou não há
     *              etapa de revisão periódica configurada.
     */
    public function promote(Planogram $planogram): bool
    {
        return DB::transaction(function () use ($planogram): bool {
            // Idempotência: não reprocessa planograma já promovido.
            if ($planogram->periodic_review_started_at !== null) {
                return false;
            }

            $this->stepService->syncForPlanogram($planogram);

            $reviewStep = $planogram->workflowSteps()
                ->where('is_skipped', false)
                ->with('template')
                ->get()
                ->first(fn (WorkflowPlanogramStep $step): bool => $step->stage_type->isPeriodicReview());

            if (! $reviewStep instanceof WorkflowPlanogramStep) {
                Log::warning('Revisão periódica não promovida: planograma sem etapa de revisão periódica.', [
                    'planogram_id' => $planogram->getKey(),
                ]);

                return false;
            }

            $gondolas = Gondola::query()
                ->where('planogram_id', $planogram->id)
                ->get(['id']);

            foreach ($gondolas as $gondola) {
                // Reaproveita uma execução já existente na etapa de revisão
                // (idempotência) ou cria uma nova como pendente.
                $execution = WorkflowGondolaExecution::query()->firstOrCreate(
                    [
                        'gondola_id' => $gondola->id,
                        'workflow_planogram_step_id' => $reviewStep->id,
                    ],
                    [
                        'status' => WorkflowExecutionStatus::Pending,
                    ],
                );

                if ($execution->wasRecentlyCreated) {
                    $this->recordSystemHistory($execution, $planogram);
                }
            }

            $planogram->update([
                'lifecycle_status' => PlanogramLifecycleStatus::PeriodicReview,
                'periodic_review_started_at' => now(),
            ]);

            $this->notifyResponsibles($planogram, $reviewStep);

            return true;
        });
    }

    /**
     * Grava o histórico da transição automática (actor = sistema, user_id null).
     */
    private function recordSystemHistory(WorkflowGondolaExecution $execution, Planogram $planogram): void
    {
        $due = $planogram->periodic_review_due_at?->toDateTimeString() ?? 'sem vencimento';

        WorkflowHistory::query()->create([
            'user_id' => null,
            'workflow_gondola_execution_id' => $execution->id,
            'action' => WorkflowHistoryAction::PeriodicReviewTriggered,
            'to_step_id' => $execution->workflow_planogram_step_id,
            'description' => "Promovido automaticamente para Revisão Periódica (vencimento {$due}).",
            'snapshot' => $execution->toArray(),
            'can_restore' => false,
            'performed_at' => now(),
        ]);
    }

    /**
     * Notifica os responsáveis da etapa de revisão periódica sobre a promoção.
     */
    private function notifyResponsibles(Planogram $planogram, WorkflowPlanogramStep $reviewStep): void
    {
        $users = $reviewStep->availableUsers()->get();

        if ($users->isEmpty()) {
            return;
        }

        $notification = new AppNotification(
            title: 'Revisão periódica iniciada',
            message: "Planograma {$planogram->name} entrou em Revisão Periódica.",
            type: 'info',
            actionUrl: $this->kanbanActionUrl($planogram),
            tenantId: (string) (Tenant::current()?->getKey() ?? ''),
        );

        foreach ($users as $user) {
            $user->notify($notification);
        }
    }

    /**
     * Monta a URL do kanban filtrado pelo planograma (best-effort: rotas de
     * tenant dependem do host, indisponível em contexto de console).
     */
    private function kanbanActionUrl(Planogram $planogram): ?string
    {
        try {
            return route('tenant.kanban.index', ['planogram_id' => $planogram->id]);
        } catch (\Throwable) {
            return null;
        }
    }
}
