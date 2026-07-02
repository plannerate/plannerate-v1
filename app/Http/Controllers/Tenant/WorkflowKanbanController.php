<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Gondola;
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

        $filters = $request->only(['planogram_id', 'store_id', 'gondola_search', 'execution_status', 'current_responsible_id', 'lifecycle_status']);
        $selectedPlanogram = $this->selectedPlanogram($request);

        if ($selectedPlanogram !== null) {
            $this->stepService->syncForPlanogram($selectedPlanogram);
        }

        return Inertia::render('tenant/planograms/Kanban', [
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
                $request->input('current_responsible_id'),
                $request->input('lifecycle_status'),
            ),
            'selected_planogram' => $selectedPlanogram ? [
                'id' => $selectedPlanogram->id,
                'name' => $selectedPlanogram->name,
                'store' => $selectedPlanogram->store?->name,
                'category_id' => $selectedPlanogram->category_id,
                'start_date' => $selectedPlanogram->start_date?->toDateString(),
                'end_date' => $selectedPlanogram->end_date?->toDateString(),
                'lifecycle_status' => $selectedPlanogram->lifecycle_status?->value,
                'completed_at' => $selectedPlanogram->completed_at?->toIso8601String(),
                'periodic_review_due_at' => $selectedPlanogram->periodic_review_due_at?->toIso8601String(),
            ] : null,
            'can_initiate' => $request->user()?->can('start', WorkflowGondolaExecution::class) ?? false,
            'can_create_gondola' => $request->user()?->can('create', Gondola::class) ?? false,
            'can_create' => $request->user()?->can('create', Planogram::class) ?? false,
        ]);
    }

    /**
     * @return array<int, array{id: string, name: string, store: ?string, store_id: ?string, lifecycle_status: ?string, periodic_review_due_at: ?string}>
     */
    private function planograms(Request $request): array
    {
        return Planogram::query()
            ->with('store:id,name')
            ->when(
                $request->filled('store_id'),
                fn (Builder $query): Builder => $query->where('store_id', $request->input('store_id'))
            )
            ->when(
                $request->filled('lifecycle_status'),
                fn (Builder $query): Builder => $query->where('lifecycle_status', $request->input('lifecycle_status'))
            )
            ->orderBy('name')
            ->get(['id', 'name', 'store_id', 'lifecycle_status', 'periodic_review_due_at'])
            ->map(fn (Planogram $planogram): array => [
                'id' => $planogram->id,
                'name' => $planogram->name,
                'store' => $planogram->store?->name,
                'store_id' => $planogram->store_id,
                'lifecycle_status' => $planogram->lifecycle_status?->value,
                'periodic_review_due_at' => $planogram->periodic_review_due_at?->toIso8601String(),
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
