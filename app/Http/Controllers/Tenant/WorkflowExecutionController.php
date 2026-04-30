<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\WorkflowExecutionAssignRequest;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowHistory;
use App\Models\WorkflowPlanogramStep;
use App\Models\WorkflowTemplate;
use App\Services\WorkflowKanbanService;
use App\Services\WorkflowPlanogramStepService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowExecutionController extends Controller
{
    use InteractsWithTenantContext;

    public function __construct(
        private readonly WorkflowKanbanService $kanbanService,
        private readonly WorkflowPlanogramStepService $stepService,
    ) {}

    public function store(Request $request, string $subdomain, Planogram $planogram): JsonResponse
    {
        unset($subdomain);
        $this->authorize('start', WorkflowGondolaExecution::class);

        $request->validate([
            'gondola_id' => ['required', 'string'],
            'step_id' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $gondola = Gondola::findOrFail($request->string('gondola_id'));
        $step = WorkflowPlanogramStep::findOrFail($request->string('step_id'));

        abort_if((string) $gondola->planogram_id !== (string) $planogram->id, 422, 'A gôndola não pertence ao planograma informado.');
        abort_if((string) $step->planogram_id !== (string) $planogram->id, 422, 'A etapa não pertence ao planograma informado.');
        abort_if((bool) $step->is_skipped, 422, 'A etapa está desativada para este planograma.');

        $execution = $this->kanbanService->startExecution(
            $gondola,
            $step,
            $request->user(),
            $request->string('notes') ?: null
        );

        return response()->json(['execution' => $execution->toArray()], 201);
    }

    public function move(Request $request, string $subdomain, WorkflowGondolaExecution $execution): JsonResponse
    {
        unset($subdomain);
        $this->authorize('move', $execution);

        $request->validate([
            'target_step_id' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $currentStep = $execution->step()->first();
        $gondola = $execution->gondola()->first();

        abort_if($currentStep === null, 422, 'A execução não possui etapa atual válida.');
        abort_if($gondola === null, 422, 'A execução não possui gôndola válida.');

        $planogram = Planogram::query()->findOrFail($gondola->planogram_id);
        $this->stepService->syncForPlanogram($planogram);

        $targetStepId = $request->string('target_step_id')->toString();
        $targetStep = $this->resolveTargetStepForExecution($execution, $targetStepId);

        $execution = $this->kanbanService->moveToStep(
            $execution,
            $targetStep,
            $request->user(),
            $request->string('notes') ?: null
        );

        return response()->json(['execution' => $execution->toArray()]);
    }

    private function resolveTargetStepForExecution(WorkflowGondolaExecution $execution, string $targetStepId): WorkflowPlanogramStep
    {
        $gondola = $execution->gondola()->firstOrFail();
        $currentStep = $execution->step()->with('template:id,template_next_step_id,template_previous_step_id')->firstOrFail();

        $targetStep = WorkflowPlanogramStep::query()->find($targetStepId);

        if ($targetStep !== null) {
            abort_if((string) $targetStep->planogram_id !== (string) $gondola->planogram_id, 422, 'A etapa de destino não pertence ao planograma desta gôndola.');
            abort_if((bool) $targetStep->is_skipped, 422, 'A etapa de destino está desativada para este planograma.');

            return $targetStep;
        }

        $targetTemplate = WorkflowTemplate::query()->findOrFail($targetStepId);

        $allowedTemplateIds = collect([
            $currentStep->template?->template_next_step_id,
            $currentStep->template?->template_previous_step_id,
        ])->filter()->values();

        abort_if(! $allowedTemplateIds->contains($targetTemplate->id), 422, 'Movimento permitido apenas para a próxima ou etapa anterior do fluxo.');

        $mappedStep = WorkflowPlanogramStep::query()
            ->where('planogram_id', $gondola->planogram_id)
            ->where('workflow_template_id', $targetTemplate->id)
            ->first();

        abort_if($mappedStep === null, 422, 'Etapa de destino não configurada para o planograma desta execução.');
        abort_if((bool) $mappedStep->is_skipped, 422, 'A etapa de destino está desativada para este planograma.');

        return $mappedStep;
    }

    public function start(Request $request, string $subdomain, WorkflowGondolaExecution $execution): JsonResponse
    {
        unset($subdomain);
        $this->authorize('start', $execution);

        $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $execution = $this->kanbanService->startPendingExecution(
            $execution,
            $request->user(),
            $request->string('notes') ?: null
        );

        return response()->json(['execution' => $execution->toArray()]);
    }

    public function pause(Request $request, string $subdomain, WorkflowGondolaExecution $execution): JsonResponse
    {
        unset($subdomain);
        $this->authorize('pause', $execution);

        $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $execution = $this->kanbanService->pause(
            $execution,
            $request->user(),
            $request->string('notes') ?: null
        );

        return response()->json(['execution' => $execution->toArray()]);
    }

    public function abandon(Request $request, string $subdomain, WorkflowGondolaExecution $execution): JsonResponse
    {
        unset($subdomain);
        $this->authorize('abandon', $execution);

        $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $execution = $this->kanbanService->abandon(
            $execution,
            $request->user(),
            $request->string('notes') ?: null
        );

        return response()->json(['execution' => $execution->toArray()]);
    }

    public function resume(Request $request, string $subdomain, WorkflowGondolaExecution $execution): JsonResponse
    {
        unset($subdomain);
        $this->authorize('resume', $execution);

        $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $execution = $this->kanbanService->resume(
            $execution,
            $request->user(),
            $request->string('notes') ?: null
        );

        return response()->json(['execution' => $execution->toArray()]);
    }

    public function complete(Request $request, string $subdomain, WorkflowGondolaExecution $execution): JsonResponse
    {
        unset($subdomain);
        $this->authorize('complete', $execution);

        $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $execution = $this->kanbanService->complete(
            $execution,
            $request->user(),
            $request->string('notes') ?: null
        );

        return response()->json(['execution' => $execution->toArray()]);
    }

    public function assign(WorkflowExecutionAssignRequest $request, string $subdomain, WorkflowGondolaExecution $execution): JsonResponse
    {
        unset($subdomain);
        $this->authorize('manage', $execution);

        $validated = $request->validated();

        $assignee = User::findOrFail((string) data_get($validated, 'user_id'));

        $isAllowedUser = $execution->step()
            ->firstOrFail()
            ->availableUsers()
            ->whereKey($assignee->id)
            ->exists();

        abort_unless($isAllowedUser, 422, 'O usuário selecionado não está permitido para esta etapa.');

        $execution = $this->kanbanService->assignTo($execution, $assignee, $request->user());

        return response()->json(['execution' => $execution->toArray()]);
    }

    public function details(Request $request, string $subdomain, WorkflowGondolaExecution $execution): JsonResponse
    {
        unset($subdomain);
        $this->authorize('viewAny', WorkflowGondolaExecution::class);

        $execution->load([
            'gondola:id,name,location',
            'step:id,name,description,workflow_template_id',
            'step.template:id,name,description',
            'step.availableUsers:id,name',
            'currentResponsible:id,name',
            'startedBy:id,name',
        ]);

        return response()->json([
            'execution' => [
                'id' => $execution->id,
                'status' => $execution->status?->value,
                'gondola' => $execution->gondola ? [
                    'id' => $execution->gondola->id,
                    'name' => $execution->gondola->name,
                    'location' => $execution->gondola->location,
                ] : null,
                'step' => $execution->step ? [
                    'id' => $execution->step->id,
                    'name' => $execution->step->name,
                    'description' => $execution->step->description,
                ] : null,
                'assigned_to_user' => $execution->currentResponsible ? [
                    'id' => $execution->currentResponsible->id,
                    'name' => $execution->currentResponsible->name,
                ] : null,
                'started_by' => $execution->execution_started_by ? [
                    'id' => $execution->execution_started_by,
                    'name' => $execution->startedBy?->name,
                ] : null,
                'started_at' => $execution->started_at?->toIso8601String(),
                'sla_date' => $execution->sla_date?->toIso8601String(),
                'can_start' => $request->user()?->can('start', $execution) ?? false,
                'can_pause' => $request->user()?->can('pause', $execution) ?? false,
                'can_resume' => $request->user()?->can('resume', $execution) ?? false,
                'can_complete' => $request->user()?->can('complete', $execution) ?? false,
                'can_abandon' => $request->user()?->can('abandon', $execution) ?? false,
                'can_move' => $request->user()?->can('move', $execution) ?? false,
            ],
            'allowed_users' => $execution->step?->availableUsers
                ?->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                ])
                ->values()
                ->all() ?? [],
        ]);
    }

    public function history(string $subdomain, WorkflowGondolaExecution $execution): JsonResponse
    {
        unset($subdomain);
        $this->authorize('viewAny', WorkflowGondolaExecution::class);

        $histories = $execution->histories()
            ->with('performedBy:id,name')
            ->get()
            ->map(fn (WorkflowHistory $h): array => [
                'id' => $h->id,
                'action' => $h->action?->value,
                'description' => $h->description,
                'from_step_id' => $h->from_step_id,
                'to_step_id' => $h->to_step_id,
                'previous_responsible_id' => $h->previous_responsible_id,
                'new_responsible_id' => $h->new_responsible_id,
                'can_restore' => $h->can_restore,
                'performed_at' => $h->performed_at?->toIso8601String(),
                'performed_by' => $h->performedBy ? [
                    'id' => $h->performedBy->id,
                    'name' => $h->performedBy->name,
                ] : null,
            ])
            ->all();

        return response()->json(['histories' => $histories]);
    }

    public function restore(Request $request, string $subdomain, WorkflowHistory $history): JsonResponse
    {
        unset($subdomain);
        $this->authorize('restore', $history->execution);

        abort_if(! $history->can_restore, 422, 'Este histórico não pode ser restaurado.');

        $execution = $this->kanbanService->restoreToHistory($history, $request->user());

        return response()->json(['execution' => $execution->toArray()]);
    }
}
