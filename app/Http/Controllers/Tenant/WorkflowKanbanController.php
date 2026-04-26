<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Planogram;
use App\Models\WorkflowGondolaExecution;
use App\Services\WorkflowKanbanService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowKanbanController extends Controller
{
    use InteractsWithTenantContext;

    public function __construct(private readonly WorkflowKanbanService $kanbanService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WorkflowGondolaExecution::class);

        $planograms = Planogram::query()
            ->with('store:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'store_id'])
            ->map(fn (Planogram $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'store' => $p->store?->name,
            ])
            ->all();

        return Inertia::render('tenant/kanban/Index', [
            'subdomain' => $this->tenantSubdomain(),
            'planograms' => $planograms,
            'selected_planogram' => null,
            'board' => null,
            'can_initiate' => $request->user()?->can('start', WorkflowGondolaExecution::class) ?? false,
        ]);
    }

    public function show(string $subdomain, Planogram $planogram): Response
    {
        unset($subdomain);
        $this->authorize('viewAny', WorkflowGondolaExecution::class);

        $planograms = Planogram::query()
            ->with('store:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'store_id'])
            ->map(fn (Planogram $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'store' => $p->store?->name,
            ])
            ->all();

        $board = $this->kanbanService->buildBoardForPlanogram($planogram);

        return Inertia::render('tenant/kanban/Index', [
            'subdomain' => $this->tenantSubdomain(),
            'planograms' => $planograms,
            'selected_planogram' => [
                'id' => $planogram->id,
                'name' => $planogram->name,
                'store' => $planogram->store?->name,
            ],
            'board' => $board,
            'can_initiate' => request()->user()?->can('start', WorkflowGondolaExecution::class) ?? false,
        ]);
    }
}
