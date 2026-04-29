<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Planogram;
use App\Models\Store;
use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Services\WorkflowKanbanService;
use App\Services\WorkflowPlanogramStepService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowKanbanController extends Controller
{
    use InteractsWithTenantContext;

    /**
     * @var list<string>
     */
    private const EXECUTION_STATUS_FILTERS = ['pending', 'active', 'paused', 'completed', 'cancelled'];

    public function __construct(
        private readonly WorkflowKanbanService $kanbanService,
        private readonly WorkflowPlanogramStepService $stepService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WorkflowGondolaExecution::class);

        $planogramId = trim((string) $request->string('planogram_id'));
        $hasPlanogramFilter = $planogramId !== '';
        $storeId = trim((string) $request->string('store_id'));
        $hasStoreFilter = $storeId !== '';
        $status = trim((string) $request->string('status'));
        $executionStatuses = in_array($status, self::EXECUTION_STATUS_FILTERS, true) ? [$status] : null;

        $planograms = Planogram::query()
            ->with('store:id,name')
            ->when($hasStoreFilter, fn ($query) => $query->where('store_id', $storeId))
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
        $selectedPlanogramId = $hasPlanogramFilter ? $planogramId : '';

        $selectedPlanogram = null;
        $board = null;

        if ($selectedPlanogramId !== '') {
            $planogram = Planogram::query()->find($selectedPlanogramId);

            if ($planogram !== null) {
                $this->stepService->syncForPlanogram($planogram);
                $board = $this->kanbanService->buildBoardForPlanogramWithStatuses($planogram, $executionStatuses, $request->user());
                $selectedPlanogram = [
                    'id' => $planogram->id,
                    'name' => $planogram->name,
                    'store' => $planogram->store?->name,
                ];
            }
        } else {
            $board = $this->kanbanService->buildBoardForInProgressExecutions(
                $request->user(),
                $hasStoreFilter ? $storeId : null,
                $executionStatuses,
            );
        }

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
