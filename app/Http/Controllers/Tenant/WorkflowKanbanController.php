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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        $statusFilter = WorkflowExecutionStatus::tryFrom((string) $request->input('status'));

        $planograms = Planogram::query()
            ->with('store:id,name')
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->input('store_id')))
            ->orderBy('name')
            ->get(['id', 'name', 'store_id'])
            ->map(fn (Planogram $planogram): array => [
                'id' => $planogram->id,
                'name' => $planogram->name,
                'store' => $planogram->store?->name,
                'store_id' => $planogram->store_id,
            ])
            ->all();

        $stores = Store::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();

        $filters = $request->only(['planogram_id', 'store_id', 'gondola_search', 'status']);
        $selectedPlanogram = null;
        $board = null;

        if ($request->filled('planogram_id')) {
            $planogram = Planogram::query()->find($request->input('planogram_id'));

            if ($planogram !== null) {
                $this->stepService->syncForPlanogram($planogram);
                $board = $this->kanbanService->buildBoardForPlanogram($planogram, $request->user());
                $selectedPlanogram = [
                    'id' => $planogram->id,
                    'name' => $planogram->name,
                    'store' => $planogram->store?->name,
                ];
            }
        } else {
            $board = Planogram::query()
                ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->input('store_id')))
                ->orderBy('name')
                ->get()
                ->flatMap(function (Planogram $planogram) use ($request): array {
                    $this->stepService->syncForPlanogram($planogram);

                    return $this->kanbanService->buildBoardForPlanogram($planogram, $request->user());
                })
                ->groupBy(
                    fn (array $column): string => sprintf(
                        '%s|%s',
                        (string) ($column['step']['suggested_order'] ?? '0'),
                        mb_strtolower((string) ($column['step']['name'] ?? ''))
                    )
                )
                ->map(function ($columns): array {
                    $firstColumn = $columns->first();
                    $executions = $columns
                        ->flatMap(fn (array $column): array => $column['executions'] ?? [])
                        ->values()
                        ->all();

                    return [
                        'step' => $firstColumn['step'],
                        'executions' => $executions,
                    ];
                })
                ->sortBy(fn (array $column): int => (int) ($column['step']['suggested_order'] ?? 0))
                ->values()
                ->all();

            if ($board !== []) {
                $selectedPlanogram = [
                    'id' => 'all',
                    'name' => 'Todos os planogramas',
                    'store' => null,
                ];
            }
        }

        if ($board !== null && $statusFilter !== null) {
            $board = collect($board)
                ->map(function (array $column) use ($statusFilter): array {
                    $column['executions'] = collect($column['executions'])
                        ->filter(fn (array $execution): bool => ($execution['status'] ?? null) === $statusFilter->value)
                        ->values()
                        ->all();

                    return $column;
                })
                ->values()
                ->all();
        }

        Storage::put('board.json', json_encode($board));

        return Inertia::render('tenant/planograms/Kanban', [
            'subdomain' => $this->tenantSubdomain(),
            'planograms' => $planograms,
            'stores' => $stores,
            'users' => $users,
            'filters' => $filters,
            'board' => $board,
            'selected_planogram' => $selectedPlanogram,
            'can_initiate' => $request->user()?->can('start', WorkflowGondolaExecution::class) ?? false,
        ]);
    }
}
