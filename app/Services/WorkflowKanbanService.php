<?php

namespace App\Services;

use App\Enums\WorkflowExecutionStatus;
use App\Enums\WorkflowHistoryAction;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowHistory;
use App\Models\WorkflowPlanogramStep;
use Illuminate\Support\Facades\DB;

class WorkflowKanbanService
{
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
                'started_at' => now(),
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

            return $execution->fresh();
        });
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

    /**
     * Build board data for all gondola executions in a planogram, grouped by step.
     *
     * @return array<int, array{step: array<string, mixed>, executions: array<int, array<string, mixed>>}>
     */
    public function buildBoardForPlanogram(Planogram $planogram): array
    {
        $steps = $planogram->workflowSteps()
            ->with(['template', 'executions.gondola', 'executions.currentResponsible'])
            ->where('is_skipped', false)
            ->get()
            ->sortBy('suggested_order');

        return $steps->map(function (WorkflowPlanogramStep $step) {
            return [
                'step' => [
                    'id' => $step->id,
                    'name' => $step->name,
                    'color' => $step->color,
                    'icon' => $step->icon,
                    'suggested_order' => $step->suggested_order,
                    'is_required' => $step->is_required,
                    'status' => $step->status,
                ],
                'executions' => $step->executions->map(fn (WorkflowGondolaExecution $exec) => [
                    'id' => $exec->id,
                    'gondola_id' => $exec->gondola_id,
                    'gondola_name' => $exec->gondola?->name,
                    'gondola_location' => $exec->gondola?->location,
                    'status' => $exec->status?->value,
                    'assigned_to_user' => $exec->currentResponsible ? [
                        'id' => $exec->currentResponsible->id,
                        'name' => $exec->currentResponsible->name,
                    ] : null,
                    'started_at' => $exec->started_at?->toIso8601String(),
                    'sla_date' => $exec->sla_date?->toIso8601String(),
                ])->values()->all(),
            ];
        })->values()->all();
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
