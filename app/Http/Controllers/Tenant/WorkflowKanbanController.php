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
        $selectedPlanogram = $this->selectedPlanogram($request);

        if ($selectedPlanogram !== null) {
            $this->stepService->syncForPlanogram($selectedPlanogram);
        }

        return Inertia::render('tenant/planograms/Kanban', [
            'subdomain' => $this->tenantSubdomain(),
            'planograms' => $this->planograms($request),
            'stores' => $this->stores(),
            'users' => $this->users(),
            'filters' => $filters,
            'board' => $this->kanbanService->buildBoardForTenant(
                $request->user(),
                $request->input('planogram_id'),
                $request->input('store_id'),
                $request->input('execution_status'),
                $request->input('gondola_search'),
            ),
            'selected_planogram' => $selectedPlanogram ? [
                'id' => $selectedPlanogram->id,
                'name' => $selectedPlanogram->name,
                'store' => $selectedPlanogram->store?->name,
            ] : null,
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

    private function selectedPlanogram(Request $request): ?Planogram
    {
        if (! $request->filled('planogram_id')) {
            return null;
        }

        return Planogram::query()
            ->with('store:id,name')
            ->find($request->input('planogram_id'));
    }
}
