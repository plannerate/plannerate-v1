<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\WorkflowExecutionStatus;
use App\Http\Controllers\Controller;
use App\Models\Planogram;
use App\Models\Store;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Services\WorkflowKanbanService;
use App\Services\WorkflowPlanogramStepService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowKanbanController extends Controller
{
    use InteractsWithTenantContext;

    public function __construct(
        private readonly WorkflowKanbanService $kanbanService,
        private readonly WorkflowPlanogramStepService $stepService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WorkflowGondolaExecution::class);

        $filters = $request->only(['planogram_id', 'store_id', 'gondola_search', 'execution_status']);
        $selectedState = $this->resolveSelectedPlanogramState($request);

        return Inertia::render('tenant/planograms/Kanban', [
            'subdomain' => $this->tenantSubdomain(),
            'planograms' => $this->planograms($request),
            'stores' => $this->stores(),
            'users' => $this->users(),
            'filters' => $filters,
            'board' => $selectedState['board'],
            'selected_planogram' => $selectedState['selected_planogram'],
            'can_initiate' => $request->user()?->can('start', WorkflowGondolaExecution::class) ?? false,
        ]);
    }

    /**
     * @return array<int, array{id: string, name: string, store: ?string, store_id: ?string}>
     */
    private function planograms(Request $request): array
    {
        return Planogram::query()
            ->with('store:id,name')
            ->when(
                $request->filled('store_id'),
                fn (Builder $query): Builder => $query->where('store_id', $request->input('store_id'))
            )
            ->orderBy('name')
            ->get(['id', 'name', 'store_id'])
            ->map(fn (Planogram $planogram): array => [
                'id' => $planogram->id,
                'name' => $planogram->name,
                'store' => $planogram->store?->name,
                'store_id' => $planogram->store_id,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function stores(): array
    {
        return Store::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function users(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    /**
     * @return array{board: mixed, selected_planogram: ?array{id: string, name: string, store: ?string}}
     */
    private function resolveSelectedPlanogramState(Request $request): array
    {
        if (! $request->filled('planogram_id')) {
            return [
                'board' => null,
                'selected_planogram' => null,
            ];
        }

        $planogram = Planogram::query()->find($request->input('planogram_id'));

        if ($planogram === null) {
            return [
                'board' => null,
                'selected_planogram' => null,
            ];
        }

        $this->stepService->syncForPlanogram($planogram);
        $board = $this->kanbanService->buildBoardForPlanogram($planogram, $request->user());

        if ($request->filled('execution_status')) {
            $board = $this->filterBoardByExecutionStatus($board, (string) $request->input('execution_status'));
        }

        return [
            'board' => $board,
            'selected_planogram' => [
                'id' => $planogram->id,
                'name' => $planogram->name,
                'store' => $planogram->store?->name,
            ],
        ];
    }

    /**
     * @param  array<int, array{step: array<string, mixed>, executions: array<int, array<string, mixed>>}>  $board
     * @return array<int, array{step: array<string, mixed>, executions: array<int, array<string, mixed>>}>
     */
    private function filterBoardByExecutionStatus(array $board, string $status): array
    {
        $allowedStatuses = collect(WorkflowExecutionStatus::cases())
            ->map(fn (WorkflowExecutionStatus $item): string => $item->value)
            ->all();

        if (! in_array($status, $allowedStatuses, true)) {
            return $board;
        }

        return collect($board)
            ->map(function (array $column) use ($status): array {
                $column['executions'] = collect($column['executions'])
                    ->filter(fn (array $execution): bool => ($execution['status'] ?? null) === $status)
                    ->values()
                    ->all();

                return $column;
            })
            ->all();
    }
}
