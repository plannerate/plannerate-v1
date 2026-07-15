<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates;

use App\Http\Controllers\Concerns\InteractsWithTrashedFilter;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Models\Gondola;
use App\Models\Tenant;
use App\Support\Tenancy\InteractsWithTenantContext;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Template\TemplateExportService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Template\TemplateImportService;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlanogramTemplateController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;
    use InteractsWithTrashedFilter;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PlanogramTemplate::class);

        $search = $this->requestString($request, 'search');
        $trashed = $this->resolveTrashedFilter($request);

        return $this->renderDeferredIndex('tenant/planogram-templates/Index', 'templates', fn (): LengthAwarePaginator => $this->templatesPaginator(
            $search,
            $trashed,
            $this->resolvePerPage($request, 15),
        ), [
            'filters' => [
                'search' => $search,
                'trashed' => $trashed,
            ],
            'can' => [
                'create' => Gate::allows('create', PlanogramTemplate::class),
                'update' => Gate::allows('update', new PlanogramTemplate),
                'delete' => Gate::allows('delete', new PlanogramTemplate),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', PlanogramTemplate::class);

        return Inertia::render('tenant/planogram-templates/Form', []);
    }

    /**
     * Lista templates ativos do tenant com seus subtemplates (num_modules)
     * para popular o seletor de modo no GondolaCreateStepper.
     */
    public function options(): JsonResponse
    {
        $this->authorize('viewAny', PlanogramTemplate::class);

        $templates = PlanogramTemplate::visible()
            ->where('tenant_id', $this->tenantId())
            ->with(['subtemplates' => function ($query): void {
                $query->where('is_active', true)->orderBy('num_modules');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'department'])
            ->map(fn (PlanogramTemplate $template): array => [
                'value' => $template->id,
                'label' => $template->name,
                'description' => $template->department,
                'subtemplates' => $template->subtemplates
                    ->map(fn ($subtemplate): array => [
                        'id' => $subtemplate->id,
                        'num_modules' => (int) $subtemplate->num_modules,
                        'code' => $subtemplate->code,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return response()->json(['templates' => $templates]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PlanogramTemplate::class);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'string', 'max:26'],
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

        return $this->toTenantRoute('tenant.planogram-templates.index');
    }

    public function importPage(): Response
    {
        $this->authorize('create', PlanogramTemplate::class);

        return Inertia::render('tenant/planogram-templates/Import', []);
    }

    public function import(Request $request, TemplateImportService $importService): RedirectResponse
    {
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
            'message' => __('app.tenant.planogram_templates.messages.imported', [
                'templates' => $report->templatesCreated,
                'subtemplates' => $report->subtemplatesCreated,
                'slots' => $report->slotsCreated,
            ]),
        ]);

        return $this->toTenantRoute('tenant.planogram-templates.index');
    }

    public function edit(PlanogramTemplate $planogramTemplate): Response
    {
        $this->authorize('update', $planogramTemplate);

        $planogramTemplate->load('category');

        return Inertia::render('tenant/planogram-templates/Form', [
            'template' => [
                'id' => $planogramTemplate->id,
                'code' => $planogramTemplate->code,
                'name' => $planogramTemplate->name,
                'department' => $planogramTemplate->department,
                'category_id' => $planogramTemplate->category_id,
                'category_name' => $planogramTemplate->category?->name,
                'description' => $planogramTemplate->description,
                'is_active' => $planogramTemplate->is_active,
            ],
        ]);
    }

    public function update(Request $request, PlanogramTemplate $planogramTemplate): RedirectResponse
    {
        $this->authorize('update', $planogramTemplate);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'string', 'max:26'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $planogramTemplate->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.planogram_templates.messages.updated'),
        ]);

        return $this->toTenantRoute('tenant.planogram-templates.index');
    }

    public function show(PlanogramTemplate $planogramTemplate): Response
    {
        $this->authorize('view', $planogramTemplate);

        $planogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('tenant/planogram-templates/Show', [
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
                    'layout_orientation' => $sub->layout_orientation?->value,
                    'flow_direction' => $sub->flow_direction?->value,
                    'hot_zone_priority' => $sub->hot_zone_priority?->value,
                    'cold_zone_priority' => $sub->cold_zone_priority?->value,
                ]),
                'created_at' => $planogramTemplate->created_at?->toDateTimeString(),
            ],
        ]);
    }

    public function export(PlanogramTemplate $planogramTemplate, TemplateExportService $exportService): StreamedResponse
    {
        $this->authorize('view', $planogramTemplate);

        return $exportService->exportTemplate($planogramTemplate);
    }

    public function exportAll(Request $request, TemplateExportService $exportService): StreamedResponse
    {
        $this->authorize('viewAny', PlanogramTemplate::class);
        $this->authorize('viewAny', PlanogramTemplate::class);

        return $exportService->exportAll($this->tenantId(), $this->requestString($request, 'search'));
    }

    /**
     * Promove um template auto (origin='auto') tornando-o visível para reuso.
     * Seta is_active=true e limpa origin (null) no template e em todos os seus subtemplates.
     * Limpar origin é obrigatório para que scopeVisible() inclua o template na listagem.
     */
    public function promote(PlanogramTemplate $planogramTemplate): RedirectResponse
    {
        $this->authorize('update', $planogramTemplate);

        if ($planogramTemplate->origin !== 'auto') {
            return back()->with('warning', __('app.tenant.planogram_templates.messages.not_auto_origin'));
        }

        $planogramTemplate->update(['is_active' => true, 'origin' => null]);
        $planogramTemplate->subtemplates()->update(['is_active' => true]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.planogram_templates.messages.promoted'),
        ]);

        return back();
    }

    public function destroy(PlanogramTemplate $planogramTemplate): RedirectResponse
    {
        $this->authorize('delete', $planogramTemplate);

        // Template já na lixeira: exclui definitivamente (força a exclusão).
        // Caso contrário, apenas envia para a lixeira (soft delete).
        if ($planogramTemplate->trashed()) {
            // Nunca apaga gôndolas — só bloqueia se alguma ainda usa este template.
            if (Gondola::where('template_id', $planogramTemplate->id)->exists()) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => __('app.tenant.planogram_templates.messages.force_delete_blocked'),
                ]);

                return $this->toTenantRoute('tenant.planogram-templates.index');
            }

            // withoutGlobalScopes() pega até subtemplates/slots já soft-deleted
            // individualmente — sem isso eles ficariam órfãos (nunca mais alcançáveis).
            $subtemplateIds = PlanogramSubtemplate::withoutGlobalScopes()
                ->where('template_id', $planogramTemplate->id)
                ->pluck('id');

            PlanogramTemplateSlot::withoutGlobalScopes()
                ->whereIn('subtemplate_id', $subtemplateIds)
                ->forceDelete();

            PlanogramSubtemplate::withoutGlobalScopes()
                ->where('template_id', $planogramTemplate->id)
                ->forceDelete();

            $planogramTemplate->forceDelete();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('app.tenant.planogram_templates.messages.force_deleted'),
            ]);

            return $this->toTenantRoute('tenant.planogram-templates.index');
        }

        $planogramTemplate->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.planogram_templates.messages.deleted'),
        ]);

        return $this->toTenantRoute('tenant.planogram-templates.index');
    }

    public function restore(PlanogramTemplate $planogramTemplate): RedirectResponse
    {
        $this->authorize('delete', $planogramTemplate);

        $planogramTemplate->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.tenant.planogram_templates.messages.restored'),
        ]);

        return $this->toTenantRoute('tenant.planogram-templates.index');
    }

    private function templatesPaginator(string $search, string $trashed, int $perPage): LengthAwarePaginator
    {
        $query = PlanogramTemplate::withCount(['subtemplates']);
        $this->applyTrashedToQuery($query, $trashed);

        return $query
            ->where(function ($q): void {
                $q->whereNull('origin')->orWhere('origin', '!=', 'auto');
            })
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
                'trashed' => $t->trashed(),
            ]);
    }

    private function tenantId(): string
    {
        return (string) Tenant::current()?->getKey();
    }
}
