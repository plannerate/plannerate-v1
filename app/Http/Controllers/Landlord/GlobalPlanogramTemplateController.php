<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\InteractsWithDeferredIndex;
use App\Http\Controllers\Controller;
use App\Models\GlobalPlanogramTemplate;
use App\Models\Tenant;
use App\Models\TenantPlanogramTemplateShare;
use App\Models\User;
use App\Services\AutoPlanogram\Template\CopyGlobalTemplateToTenantService;
use App\Services\AutoPlanogram\Template\GlobalTemplateImportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class GlobalPlanogramTemplateController extends Controller
{
    use InteractsWithDeferredIndex;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', GlobalPlanogramTemplate::class);

        $search = $this->requestString($request, 'search');

        return $this->renderDeferredIndex('landlord/planogram-templates/Index', 'templates', fn (): LengthAwarePaginator => $this->templatesPaginator(
            $search,
            $this->resolvePerPage($request, 15),
        ), [
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', GlobalPlanogramTemplate::class);

        return Inertia::render('landlord/planogram-templates/Import');
    }

    public function import(Request $request, GlobalTemplateImportService $importService): RedirectResponse
    {
        $this->authorize('create', GlobalPlanogramTemplate::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $filePath = $file->store('global-template-imports', 'public');
        $absolutePath = storage_path('app/public/'.$filePath);

        /** @var User $user */
        $user = $request->user();
        $report = $importService->import($absolutePath, $user->getKey());

        if ($report->hasErrors()) {
            return back()->withErrors(['file' => implode(' ', $report->errors)]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.planogram_templates.messages.imported', [
                'templates' => $report->templatesCreated,
                'subtemplates' => $report->subtemplatesCreated,
                'slots' => $report->slotsCreated,
                'products' => $report->productsImported,
            ]),
        ]);

        if (count($report->warnings) > 0) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('app.landlord.planogram_templates.messages.import_warnings', [
                    'count' => count($report->warnings),
                ]),
            ]);
        }

        return to_route('landlord.planogram-templates.index');
    }

    public function show(GlobalPlanogramTemplate $globalPlanogramTemplate): Response
    {
        $this->authorize('view', $globalPlanogramTemplate);

        $globalPlanogramTemplate->load(['subtemplates.slots']);

        $sharedTenantIds = TenantPlanogramTemplateShare::where('global_template_id', $globalPlanogramTemplate->getKey())
            ->pluck('tenant_id')
            ->toArray();

        $shares = TenantPlanogramTemplateShare::with('tenant', 'sharedBy')
            ->where('global_template_id', $globalPlanogramTemplate->getKey())
            ->latest('shared_at')
            ->get()
            ->map(fn (TenantPlanogramTemplateShare $share): array => [
                'id' => $share->id,
                'tenant_id' => $share->tenant_id,
                'tenant_name' => $share->tenant?->name,
                'shared_at' => $share->shared_at?->toDateTimeString(),
                'shared_by_name' => $share->sharedBy?->name,
            ]);

        $availableTenants = Tenant::whereNotIn('id', $sharedTenantIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ]);

        return Inertia::render('landlord/planogram-templates/Show', [
            'template' => [
                'id' => $globalPlanogramTemplate->id,
                'code' => $globalPlanogramTemplate->code,
                'name' => $globalPlanogramTemplate->name,
                'department' => $globalPlanogramTemplate->department,
                'description' => $globalPlanogramTemplate->description,
                'is_active' => $globalPlanogramTemplate->is_active,
                'subtemplates_count' => $globalPlanogramTemplate->subtemplates->count(),
                'subtemplates' => $globalPlanogramTemplate->subtemplates->map(fn ($sub): array => [
                    'id' => $sub->id,
                    'code' => $sub->code,
                    'num_modules' => $sub->num_modules,
                    'slots_count' => $sub->slots->count(),
                ]),
                'created_at' => $globalPlanogramTemplate->created_at?->toDateTimeString(),
            ],
            'shares' => $shares,
            'available_tenants' => $availableTenants,
        ]);
    }

    public function share(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate, CopyGlobalTemplateToTenantService $copyService): RedirectResponse
    {
        $this->authorize('share', $globalPlanogramTemplate);

        $request->validate([
            'tenant_ids' => ['required', 'array', 'min:1'],
            'tenant_ids.*' => ['required', 'string', 'exists:landlord.tenants,id'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $tenants = Tenant::whereIn('id', $request->input('tenant_ids'))->get();

        foreach ($tenants as $tenant) {
            $copyService->copy($globalPlanogramTemplate, $tenant, $user);
        }

        $count = $tenants->count();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.planogram_templates.shares.messages.shared', ['count' => $count]),
        ]);

        return back();
    }

    public function destroy(GlobalPlanogramTemplate $globalPlanogramTemplate): RedirectResponse
    {
        $this->authorize('delete', $globalPlanogramTemplate);

        $globalPlanogramTemplate->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.planogram_templates.messages.deleted'),
        ]);

        return to_route('landlord.planogram-templates.index');
    }

    private function templatesPaginator(string $search, int $perPage): LengthAwarePaginator
    {
        return GlobalPlanogramTemplate::withCount(['subtemplates', 'templateProducts', 'shares'])
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search): void {
                $w->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('department', 'like', '%'.$search.'%');
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (GlobalPlanogramTemplate $t): array => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'department' => $t->department,
                'is_active' => $t->is_active,
                'subtemplates_count' => $t->subtemplates_count,
                'template_products_count' => $t->template_products_count,
                'shares_count' => $t->shares_count,
                'created_at' => $t->created_at?->toDateTimeString(),
            ]);
    }
}
