<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Models\PlanogramTemplate;
use App\Models\Tenant;
use App\Services\AutoPlanogram\Template\TemplateImportService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanogramTemplateController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;

    public function index(Request $request, string $subdomain): Response
    {
        unset($subdomain);
        $this->authorize('viewAny', PlanogramTemplate::class);

        $search = $this->requestString($request, 'search');

        return $this->renderDeferredIndex('tenant/planogram-templates/Index', 'templates', fn (): LengthAwarePaginator => $this->templatesPaginator(
            $search,
            $this->resolvePerPage($request, 15),
        ), [
            'subdomain' => $this->tenantSubdomain(),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(string $subdomain): Response
    {
        unset($subdomain);
        $this->authorize('create', PlanogramTemplate::class);

        return Inertia::render('tenant/planogram-templates/Form', [
            'subdomain' => $this->tenantSubdomain(),
        ]);
    }

    public function store(Request $request, string $subdomain): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('create', PlanogramTemplate::class);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['tenant_id'] = $this->tenantId();
        $validated['created_by'] = $request->user()?->getKey();

        PlanogramTemplate::create($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.planogram_templates.messages.created'),
        ]);

        return to_route('tenant.planogram-templates.index', $this->tenantRouteParameters());
    }

    public function importPage(string $subdomain): Response
    {
        unset($subdomain);
        $this->authorize('create', PlanogramTemplate::class);

        return Inertia::render('tenant/planogram-templates/Import', [
            'subdomain' => $this->tenantSubdomain(),
        ]);
    }

    public function import(Request $request, string $subdomain, TemplateImportService $importService): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('create', PlanogramTemplate::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $file = $request->file('file');
        $filePath = $file->store('template-imports', 'public');
        $absolutePath = storage_path('app/public/'.$filePath);

        $tenantId = $this->tenantId();
        $report = $importService->import($absolutePath, $tenantId);

        if ($report->hasErrors()) {
            return back()->withErrors(['file' => implode(' ', $report->errors)]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.planogram-templates.messages.imported', [
                'templates' => $report->templatesCreated,
                'subtemplates' => $report->subtemplatesCreated,
                'slots' => $report->slotsCreated,
            ]),
        ]);

        return to_route('tenant.planogram-templates.index', $this->tenantRouteParameters());
    }

    public function edit(string $subdomain, PlanogramTemplate $planogramTemplate): Response
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        return Inertia::render('tenant/planogram-templates/Form', [
            'subdomain' => $this->tenantSubdomain(),
            'template' => [
                'id' => $planogramTemplate->id,
                'code' => $planogramTemplate->code,
                'name' => $planogramTemplate->name,
                'department' => $planogramTemplate->department,
                'description' => $planogramTemplate->description,
                'is_active' => $planogramTemplate->is_active,
            ],
        ]);
    }

    public function update(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $planogramTemplate->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.planogram_templates.messages.updated'),
        ]);

        return to_route('tenant.planogram-templates.index', $this->tenantRouteParameters());
    }

    public function show(string $subdomain, PlanogramTemplate $planogramTemplate): Response
    {
        unset($subdomain);
        $this->authorize('view', $planogramTemplate);

        $planogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('tenant/planogram-templates/Show', [
            'subdomain' => $this->tenantSubdomain(),
            'template' => [
                'id' => $planogramTemplate->id,
                'code' => $planogramTemplate->code,
                'name' => $planogramTemplate->name,
                'department' => $planogramTemplate->department,
                'description' => $planogramTemplate->description,
                'is_active' => $planogramTemplate->is_active,
                'subtemplates_count' => $planogramTemplate->subtemplates->count(),
                'subtemplates' => $planogramTemplate->subtemplates->map(fn ($sub) => [
                    'id' => $sub->id,
                    'code' => $sub->code,
                    'num_modules' => $sub->num_modules,
                    'slots_count' => $sub->slots->count(),
                ]),
                'created_at' => $planogramTemplate->created_at?->toDateTimeString(),
            ],
        ]);
    }

    public function destroy(string $subdomain, PlanogramTemplate $planogramTemplate): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('delete', $planogramTemplate);

        $planogramTemplate->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.planogram-templates.messages.deleted'),
        ]);

        return to_route('tenant.planogram-templates.index', $this->tenantRouteParameters());
    }

    private function templatesPaginator(string $search, int $perPage): LengthAwarePaginator
    {
        return PlanogramTemplate::withCount(['subtemplates'])
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search): void {
                $w->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('department', 'like', '%'.$search.'%');
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (PlanogramTemplate $t): array => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'department' => $t->department,
                'is_active' => $t->is_active,
                'subtemplates_count' => $t->subtemplates_count,
                'created_at' => $t->created_at?->toDateTimeString(),
            ]);
    }

    private function tenantId(): string
    {
        return (string) Tenant::current()?->getKey();
    }
}
