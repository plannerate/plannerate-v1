<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\WorkflowExecutionStatus;
use App\Http\Controllers\Controller;
use App\Models\WorkflowGondolaExecution;
use App\Services\WorkflowKanbanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KanbanColumnExecutionsController extends Controller
{
    public function __construct(
        private readonly WorkflowKanbanService $kanbanService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkflowGondolaExecution::class);

        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $validated = $request->validate([
            'step_ids' => ['required', 'array', 'min:1'],
            'step_ids.*' => ['required', 'string', 'ulid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', 'string', Rule::enum(WorkflowExecutionStatus::class)],
            'gondola_search' => ['nullable', 'string', 'max:255'],
        ]);

        $status = isset($validated['status'])
            ? WorkflowExecutionStatus::tryFrom((string) $validated['status'])
            : null;

        $gondolaSearch = isset($validated['gondola_search']) ? trim((string) $validated['gondola_search']) : null;
        $gondolaSearch = $gondolaSearch === '' ? null : $gondolaSearch;

        $paginator = $this->kanbanService->paginateExecutionsForStepIds(
            array_values(array_unique($validated['step_ids'])),
            $user,
            $status,
            $gondolaSearch,
            min(50, max(1, (int) ($validated['per_page'] ?? 20))),
            max(1, (int) ($validated['page'] ?? 1)),
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
