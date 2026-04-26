<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Gondola;
use App\Models\Planogram;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowHistory;
use App\Models\WorkflowPlanogramStep;
use App\Services\WorkflowKanbanService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowExecutionController extends Controller
{
    use InteractsWithTenantContext;

    public function __construct(private readonly WorkflowKanbanService $kanbanService) {}

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

        $targetStep = WorkflowPlanogramStep::findOrFail($request->string('target_step_id'));

        $execution = $this->kanbanService->moveToStep(
            $execution,
            $targetStep,
            $request->user(),
            $request->string('notes') ?: null
        );

        return response()->json(['execution' => $execution->toArray()]);
    }

    public function pause(Request $request, string $subdomain, WorkflowGondolaExecution $execution): JsonResponse
    {
        unset($subdomain);
        $this->authorize('manage', $execution);

        $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $execution = $this->kanbanService->pause(
            $execution,
            $request->user(),
            $request->string('notes') ?: null
        );

        return response()->json(['execution' => $execution->toArray()]);
    }

    public function resume(Request $request, string $subdomain, WorkflowGondolaExecution $execution): JsonResponse
    {
        unset($subdomain);
        $this->authorize('manage', $execution);

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
        $this->authorize('manage', $execution);

        $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $execution = $this->kanbanService->complete(
            $execution,
            $request->user(),
            $request->string('notes') ?: null
        );

        return response()->json(['execution' => $execution->toArray()]);
    }

    public function assign(Request $request, string $subdomain, WorkflowGondolaExecution $execution): JsonResponse
    {
        unset($subdomain);
        $this->authorize('manage', $execution);

        $request->validate(['user_id' => ['required', 'string']]);

        $assignee = User::findOrFail($request->string('user_id'));

        $execution = $this->kanbanService->assignTo($execution, $assignee, $request->user());

        return response()->json(['execution' => $execution->toArray()]);
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
