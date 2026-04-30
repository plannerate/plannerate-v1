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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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

    public function abandon(WorkflowGondolaExecution $execution, User $actor, ?string $notes = null): WorkflowGondolaExecution
    {
        return DB::transaction(function () use ($execution, $actor, $notes) {
            $execution->update([
                'status' => WorkflowExecutionStatus::Cancelled,
                'completed_at' => now(),
                'notes' => $notes,
            ]);

            $this->recordHistory($execution, WorkflowHistoryAction::Cancelled, $actor, $execution->workflow_planogram_step_id, null, $notes);

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
     * Kanban column shell: step metadata, step ids for API fetch, empty executions, total count.
     *
     * @return array<int, array{step: array<string, mixed>, step_ids: array<int, string>, column_steps: array<int, array{id: string, planogram_id: string}>, executions: array<int, array<string, mixed>>, executions_count: int}>
     */
    public function buildColumnStructureForPlanogram(Planogram $planogram): array
    {
        $steps = $planogram->workflowSteps()
            ->with(['template'])
            ->withCount('executions')
            ->where('is_skipped', false)
            ->get()
            ->sortBy('suggested_order');

        return $steps->map(function (WorkflowPlanogramStep $step) {
            return [
                'step' => $this->stepToKanbanArray($step),
                'step_ids' => [$step->id],
                'column_steps' => [
                    [
                        'id' => $step->id,
                        'planogram_id' => (string) $step->planogram_id,
                    ],
                ],
                'executions' => [],
                'executions_count' => (int) $step->executions_count,
            ];
        })->values()->all();
    }

    /**
     * Build board data for all gondola executions in a planogram, grouped by step.
     *
     * @return array<int, array{step: array<string, mixed>, step_ids: array<int, string>, executions: array<int, array<string, mixed>>, executions_count: int}>
     */
    public function buildBoardForPlanogram(Planogram $planogram, ?User $user = null): array
    {
        $steps = $planogram->workflowSteps()
            ->with(['template', 'executions.gondola.planogram', 'executions.currentResponsible', 'executions.startedBy'])
            ->where('is_skipped', false)
            ->get()
            ->sortBy('suggested_order');

        return $steps->map(function (WorkflowPlanogramStep $step) use ($user) {
            return [
                'step' => $this->stepToKanbanArray($step),
                'step_ids' => [$step->id],
                'column_steps' => [
                    [
                        'id' => $step->id,
                        'planogram_id' => (string) $step->planogram_id,
                    ],
                ],
                'executions' => $step->executions
                    ->map(fn (WorkflowGondolaExecution $exec) => $this->mapExecutionToKanbanPayload($exec, $step, $user))
                    ->values()
                    ->all(),
                'executions_count' => $step->executions->count(),
            ];
        })->values()->all();
    }

    /**
     * Paginate executions for one or more workflow step ids (merged kanban columns).
     *
     * @param  array<int, string>  $stepIds
     */
    public function paginateExecutionsForStepIds(
        array $stepIds,
        User $user,
        ?WorkflowExecutionStatus $status,
        ?string $gondolaSearch,
        int $perPage = 20,
        int $page = 1
    ): LengthAwarePaginator {
        $steps = WorkflowPlanogramStep::query()
            ->whereIn('id', $stepIds)
            ->get();

        if ($steps->count() !== count(array_unique($stepIds))) {
            abort(404);
        }

        $tenantId = $steps->first()?->tenant_id;

        if ($tenantId === null || $steps->contains(fn (WorkflowPlanogramStep $s): bool => $s->tenant_id !== $tenantId)) {
            abort(403);
        }

        $stepsById = $steps->keyBy('id');

        $query = WorkflowGondolaExecution::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('workflow_planogram_step_id', $stepIds)
            ->with(['gondola.planogram', 'currentResponsible', 'startedBy'])
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
            ->when(
                filled($gondolaSearch),
                fn (Builder $q) => $q->whereHas(
                    'gondola',
                    fn (Builder $gq) => $gq->where('name', 'like', '%'.$gondolaSearch.'%')
                )
            )
            ->orderByDesc('updated_at');

        /** @var LengthAwarePaginator<int, WorkflowGondolaExecution> $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $paginator->setCollection(
            $paginator->getCollection()->map(function (WorkflowGondolaExecution $exec) use ($user, $stepsById) {
                $step = $stepsById->get($exec->workflow_planogram_step_id);

                if ($step === null) {
                    throw new \LogicException('Workflow step not found for execution.');
                }

                return $this->mapExecutionToKanbanPayload($exec, $step, $user);
            })
        );

        return $paginator;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapExecutionToKanbanPayload(
        WorkflowGondolaExecution $exec,
        WorkflowPlanogramStep $step,
        ?User $user = null
    ): array {
        return [
            'id' => $exec->id,
            'workflow_planogram_step_id' => $exec->workflow_planogram_step_id,
            'planogram_id' => (string) $step->planogram_id,
            'gondola_id' => $exec->gondola_id,
            'gondola_name' => $exec->gondola?->name,
            'gondola_location' => $exec->gondola?->location,
            'planogram_name' => $exec->gondola?->planogram?->name,
            'step_name' => $step->name,
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
            'can_move' => $user?->can('move', $exec) ?? false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stepToKanbanArray(WorkflowPlanogramStep $step): array
    {
        return [
            'id' => $step->id,
            'planogram_id' => $step->planogram_id,
            'name' => $step->name,
            'description' => $step->description,
            'color' => $step->color,
            'icon' => $step->icon,
            'suggested_order' => $step->suggested_order,
            'is_required' => $step->is_required,
            'is_skipped' => $step->is_skipped,
            'status' => $step->status,
        ];
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
