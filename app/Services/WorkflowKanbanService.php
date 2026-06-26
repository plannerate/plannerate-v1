<?php

namespace App\Services;

use App\Enums\PlanogramLifecycleStatus;
use App\Enums\WorkflowExecutionStatus;
use App\Enums\WorkflowHistoryAction;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowHistory;
use App\Models\WorkflowPlanogramStep;
use App\Models\WorkflowTemplate;
use App\Notifications\AppNotification;
use App\Support\Workflow\PeriodicReviewSchedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkflowKanbanService
{
    /**
     * Build board data for the tenant using workflow templates as columns.
     *
     * @return array<int, array{step: array<string, mixed>, executions: array<int, array<string, mixed>>}>
     */
    public function buildBoardForTenant(
        ?User $user = null,
        ?string $planogramId = null,
        ?string $storeId = null,
        ?string $executionStatus = null,
        ?string $gondolaSearch = null,
        ?string $currentResponsibleId = null,
    ): array {
        $templates = WorkflowTemplate::query()
            ->where('status', 'published')
            ->orderBy('suggested_order')
            ->get([
                'id',
                'name',
                'description',
                'color',
                'icon',
                'suggested_order',
                'is_required_by_default',
                'status',
                'template_next_step_id',
                'template_previous_step_id',
            ]);

        $steps = WorkflowPlanogramStep::query()
            ->where('is_skipped', false)
            ->when($planogramId, fn ($query) => $query->where('planogram_id', $planogramId))
            ->when($storeId, fn ($query) => $query->whereHas('planogram', fn ($planogramQuery) => $planogramQuery->where('store_id', $storeId)))
            ->get(['id', 'workflow_template_id'])
            ->keyBy('id');

        $stepIds = $steps->keys()->all();

        $executions = WorkflowGondolaExecution::query()
            ->whereIn('workflow_planogram_step_id', $stepIds)
            ->with([
                'gondola:id,name,location,planogram_id',
                'gondola.planogram:id,name,store_id',
                'currentResponsible:id,name',
                'startedBy:id,name',
                'step:id,name,workflow_template_id,access_mode,stage_type,planogram_id',
                'step.template:id,access_mode,stage_type,suggested_order',
                // Pré-carrega o que as policies (start/complete) consultariam por
                // execução, evitando N+1 ao montar can_start/can_complete do board.
                'step.availableUsers:id',
                'step.planogram:id',
                'step.planogram.workflowSteps:id,planogram_id,workflow_template_id,is_skipped,stage_type',
                'step.planogram.workflowSteps.template:id,suggested_order,stage_type',
            ])
            ->when($executionStatus, fn ($query) => $query->where('status', $executionStatus))
            ->when(
                $currentResponsibleId,
                fn ($query) => $query->forResponsible($currentResponsibleId)
            )
            ->when(
                $gondolaSearch,
                fn ($query) => $query->whereHas(
                    'gondola',
                    fn ($gondolaQuery) => $gondolaQuery->where('name', 'like', '%'.$gondolaSearch.'%')
                )
            )
            ->get();

        $executionsByTemplate = $executions
            ->groupBy(fn (WorkflowGondolaExecution $execution): ?string => $steps->get($execution->workflow_planogram_step_id)?->workflow_template_id);

        return $templates->map(function (WorkflowTemplate $template) use ($executionsByTemplate, $user) {
            /** @var Collection<int, WorkflowGondolaExecution> $templateExecutions */
            $templateExecutions = $executionsByTemplate->get($template->id, collect());

            return [
                'step' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'color' => $template->color,
                    'icon' => $template->icon,
                    'suggested_order' => $template->suggested_order,
                    'is_required' => (bool) $template->is_required_by_default,
                    'is_skipped' => false,
                    'status' => $template->status,
                    'template_next_step_id' => $template->template_next_step_id,
                    'template_previous_step_id' => $template->template_previous_step_id,
                ],
                'executions' => $templateExecutions
                    ->map(fn (WorkflowGondolaExecution $exec): array => $this->executionToArray($exec, $user))
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    /**
     * Mapeia uma execução para o payload de card do board. Ponto único usado
     * pela montagem do board para evitar divergência entre colunas.
     *
     * @return array<string, mixed>
     */
    private function executionToArray(WorkflowGondolaExecution $exec, ?User $user): array
    {
        return [
            'id' => $exec->id,
            'gondola_id' => $exec->gondola_id,
            'gondola_name' => $exec->gondola?->name,
            'gondola_location' => $exec->gondola?->location,
            'planogram_name' => $exec->gondola?->planogram?->name,
            'step_name' => $exec->step?->name,
            'status' => $exec->status?->value,
            'assigned_to_user' => $exec->currentResponsible ? [
                'id' => $exec->currentResponsible->id,
                'name' => $exec->currentResponsible->name,
            ] : null,
            'started_by' => $exec->execution_started_by ? [
                'id' => $exec->execution_started_by,
                'name' => $exec->startedBy?->name,
            ] : null,
            'started_at' => $exec->started_at?->toIso8601String(),
            'sla_date' => $exec->sla_date?->toIso8601String(),
            'can_start' => $user?->can('start', $exec) ?? false,
            'can_pause' => $user?->can('pause', $exec) ?? false,
            'can_resume' => $user?->can('resume', $exec) ?? false,
            'can_complete' => $user?->can('complete', $exec) ?? false,
            'can_abandon' => $user?->can('abandon', $exec) ?? false,
            'can_request_abandonment' => $user?->can('requestAbandonment', $exec) ?? false,
            'can_move' => $user?->can('move', $exec) ?? false,
            'can_open_editor' => $exec->canOpenEditorBy($user),
        ];
    }

    /**
     * Start a new execution for a gondola at the given step.
     */
    public function startExecution(
        Gondola $gondola,
        WorkflowPlanogramStep $step,
        User $actor,
        ?string $notes = null
    ): WorkflowGondolaExecution {
        return DB::transaction(function () use ($gondola, $step, $actor, $notes) {
            $execution = WorkflowGondolaExecution::create([
                'tenant_id' => $gondola->tenant_id,
                'gondola_id' => $gondola->id,
                'workflow_planogram_step_id' => $step->id,
                'status' => WorkflowExecutionStatus::Active,
                'execution_started_by' => $actor->id,
                'current_responsible_id' => $actor->id,
                'started_at' => now(),
            ]);

            $this->recordHistory($execution, WorkflowHistoryAction::Started, $actor, null, $step->id, $notes);

            return $execution;
        });
    }

    public function startPendingExecution(
        WorkflowGondolaExecution $execution,
        User $actor,
        ?string $notes = null
    ): WorkflowGondolaExecution {
        return DB::transaction(function () use ($execution, $actor, $notes) {
            $execution->update([
                'status' => WorkflowExecutionStatus::Active,
                'execution_started_by' => $actor->id,
                'current_responsible_id' => $actor->id,
                'started_at' => now(),
                'paused_at' => null,
                'notes' => $notes,
            ]);

            $this->recordHistory(
                $execution,
                WorkflowHistoryAction::Started,
                $actor,
                null,
                $execution->workflow_planogram_step_id,
                $notes
            );

            return $execution->fresh();
        });
    }

    /**
     * Move execution to a different step, resetting timing fields.
     */
    public function moveToStep(
        WorkflowGondolaExecution $execution,
        WorkflowPlanogramStep $targetStep,
        User $actor,
        ?string $notes = null
    ): WorkflowGondolaExecution {
        return DB::transaction(function () use ($execution, $targetStep, $actor, $notes) {
            $fromStepId = $execution->workflow_planogram_step_id;

            $execution->update([
                'workflow_planogram_step_id' => $targetStep->id,
                'status' => WorkflowExecutionStatus::Pending,
                'current_responsible_id' => null,
                'execution_started_by' => null,
                'started_at' => null,
                'paused_at' => null,
            ]);

            $this->recordHistory($execution, WorkflowHistoryAction::Moved, $actor, $fromStepId, $targetStep->id, $notes);

            return $execution->fresh();
        });
    }

    /**
     * Pause the execution.
     */
    public function pause(WorkflowGondolaExecution $execution, User $actor, ?string $notes = null): WorkflowGondolaExecution
    {
        return DB::transaction(function () use ($execution, $actor, $notes) {
            $execution->update([
                'status' => WorkflowExecutionStatus::Paused,
                'paused_at' => now(),
            ]);

            $this->recordHistory($execution, WorkflowHistoryAction::Paused, $actor, $execution->workflow_planogram_step_id, null, $notes);

            return $execution->fresh();
        });
    }

    /**
     * Resume a paused execution.
     */
    public function resume(WorkflowGondolaExecution $execution, User $actor, ?string $notes = null): WorkflowGondolaExecution
    {
        return DB::transaction(function () use ($execution, $actor, $notes) {
            $execution->update([
                'status' => WorkflowExecutionStatus::Active,
                'paused_at' => null,
            ]);

            $this->recordHistory($execution, WorkflowHistoryAction::Resumed, $actor, $execution->workflow_planogram_step_id, null, $notes);

            return $execution->fresh();
        });
    }

    /**
     * Mark execution as completed.
     */
    public function complete(WorkflowGondolaExecution $execution, User $actor, ?string $notes = null): WorkflowGondolaExecution
    {
        return DB::transaction(function () use ($execution, $actor, $notes) {
            $execution->update([
                'status' => WorkflowExecutionStatus::Completed,
                'completed_at' => now(),
            ]);

            $this->recordHistory($execution, WorkflowHistoryAction::Completed, $actor, $execution->workflow_planogram_step_id, null, $notes);

            $this->maybeMarkPlanogramCompleted($execution);

            return $execution->fresh();
        });
    }

    /**
     * Marca o planograma como concluído quando todas as gôndolas com execução
     * finalizaram a etapa final de fluxo (`stage_type = flow`).
     *
     * Conclusão por planograma: exige que toda gôndola com execução tenha a
     * execução da etapa final de fluxo com `completed` e que não reste nenhuma
     * execução de fluxo em andamento (active/pending/paused). Idempotente: só
     * atua enquanto o planograma estiver `in_progress`. Ao concluir, agenda o
     * vencimento da Revisão Periódica (quando há datas válidas).
     */
    private function maybeMarkPlanogramCompleted(WorkflowGondolaExecution $execution): void
    {
        $execution->loadMissing('step.planogram.workflowSteps.template');

        $planogram = $execution->step?->planogram;

        if (! $planogram instanceof Planogram) {
            return;
        }

        // Idempotência: só processa fluxo ainda em andamento.
        if ($planogram->lifecycle_status !== PlanogramLifecycleStatus::InProgress) {
            return;
        }

        $activeSteps = $planogram->workflowSteps->where('is_skipped', false);

        $flowSteps = $activeSteps->filter(
            fn (WorkflowPlanogramStep $step): bool => ! $step->stage_type->isPeriodicReview()
        );

        // Fallback: sem etapas de fluxo, usa as não puladas para definir a final.
        $candidateSteps = $flowSteps->isNotEmpty() ? $flowSteps : $activeSteps;

        if ($candidateSteps->isEmpty()) {
            return;
        }

        $finalStep = $candidateSteps->sortBy('suggested_order')->last();
        $flowStepIds = $candidateSteps->pluck('id')->all();

        $executions = WorkflowGondolaExecution::query()
            ->whereIn('workflow_planogram_step_id', $flowStepIds)
            ->get(['id', 'gondola_id', 'workflow_planogram_step_id', 'status']);

        if ($executions->isEmpty()) {
            return;
        }

        // Alguma execução de fluxo ainda em andamento → planograma não concluído.
        $hasUnfinished = $executions->contains(
            fn (WorkflowGondolaExecution $exec): bool => in_array($exec->status, [
                WorkflowExecutionStatus::Active,
                WorkflowExecutionStatus::Pending,
                WorkflowExecutionStatus::Paused,
            ], true)
        );

        if ($hasUnfinished) {
            return;
        }

        // Toda gôndola com execução precisa ter concluído a etapa final.
        $allGondolasFinished = $executions
            ->groupBy('gondola_id')
            ->every(fn (Collection $gondolaExecutions): bool => $gondolaExecutions->contains(
                fn (WorkflowGondolaExecution $exec): bool => (string) $exec->workflow_planogram_step_id === (string) $finalStep->id
                    && $exec->status === WorkflowExecutionStatus::Completed
            ));

        if (! $allGondolasFinished) {
            return;
        }

        $planogram->completed_at = now();
        $planogram->lifecycle_status = PlanogramLifecycleStatus::Completed;
        $planogram->periodic_review_due_at = PeriodicReviewSchedule::computeDueAt($planogram);
        $planogram->save();
    }

    public function abandon(WorkflowGondolaExecution $execution, User $actor, ?string $notes = null): WorkflowGondolaExecution
    {
        return DB::transaction(function () use ($execution, $actor, $notes) {
            $execution->update([
                'status' => WorkflowExecutionStatus::Pending,
                'current_responsible_id' => null,
                'started_at' => null,
                'execution_started_by' => null,
                'notes' => $notes,
            ]);

            $this->recordHistory($execution, WorkflowHistoryAction::Cancelled, $actor, $execution->workflow_planogram_step_id, null, $notes);

            return $execution->fresh();
        });
    }

    public function requestAbandonment(
        WorkflowGondolaExecution $execution,
        User $actor,
        ?string $notes = null,
        ?string $actionUrl = null
    ): void {
        $execution->loadMissing([
            'gondola:id,name,planogram_id',
            'startedBy:id,name',
            'step:id,name',
        ]);

        $startedBy = $execution->startedBy;

        abort_if($startedBy === null, 422, 'A execução não possui usuário executor para notificar.');

        $gondolaName = $execution->gondola?->name ?? 'gôndola sem nome';
        $stepName = $execution->step?->name ?? 'etapa atual';
        $planogramContext = $execution->gondola?->planogram_id !== null
            ? " do planograma {$execution->gondola->planogram_id}"
            : '';
        $message = "{$actor->name} solicitou que você abandone a execução da {$gondolaName}{$planogramContext} na etapa {$stepName}.";

        if ($notes !== null && $notes !== '') {
            $message .= " Observação: {$notes}";
        }

        $startedBy->notify(new AppNotification(
            title: 'Solicitação de abandono',
            message: $message,
            type: 'warning',
            actionUrl: $actionUrl,
            tenantId: (string) (Tenant::current()?->getKey() ?? ''),
        ));
    }

    /**
     * Assign a responsible user to the execution.
     */
    public function assignTo(WorkflowGondolaExecution $execution, User $assignee, User $actor): WorkflowGondolaExecution
    {
        return DB::transaction(function () use ($execution, $assignee, $actor) {
            $previousResponsibleId = $execution->current_responsible_id;

            $execution->update(['current_responsible_id' => $assignee->id]);

            $this->recordHistory(
                $execution,
                WorkflowHistoryAction::Assigned,
                $actor,
                null,
                null,
                null,
                $previousResponsibleId,
                $assignee->id
            );

            return $execution->fresh();
        });
    }

    /**
     * Restore an execution to the state captured in a history snapshot.
     */
    public function restoreToHistory(WorkflowHistory $history, User $actor): WorkflowGondolaExecution
    {
        return DB::transaction(function () use ($history, $actor) {
            $execution = $history->execution;
            $snapshot = $history->snapshot ?? [];

            $restorable = collect($snapshot)->only([
                'workflow_planogram_step_id',
                'status',
                'current_responsible_id',
                'started_at',
                'completed_at',
                'sla_date',
                'paused_at',
                'notes',
                'context',
            ])->toArray();

            $execution->update($restorable);

            $this->recordHistory(
                $execution,
                WorkflowHistoryAction::Restored,
                $actor,
                null,
                $restorable['workflow_planogram_step_id'] ?? null,
                "Restaurado para o estado do histórico #{$history->id}"
            );

            return $execution->fresh();
        });
    }

    private function recordHistory(
        WorkflowGondolaExecution $execution,
        WorkflowHistoryAction $action,
        User $actor,
        ?string $fromStepId = null,
        ?string $toStepId = null,
        ?string $description = null,
        ?string $previousResponsibleId = null,
        ?string $newResponsibleId = null
    ): WorkflowHistory {
        return WorkflowHistory::create([
            'user_id' => $actor->id,
            'workflow_gondola_execution_id' => $execution->id,
            'action' => $action,
            'from_step_id' => $fromStepId,
            'to_step_id' => $toStepId,
            'previous_responsible_id' => $previousResponsibleId,
            'new_responsible_id' => $newResponsibleId,
            'description' => $description,
            'snapshot' => $execution->toArray(),
            'can_restore' => true,
            'performed_at' => now(),
        ]);
    }
}
