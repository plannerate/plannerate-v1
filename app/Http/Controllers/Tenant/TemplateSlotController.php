<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\InteractsWithSyncImageDownLoad;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplate;
use App\Models\PlanogramTemplateSlot;
use App\Models\Product;
use App\Services\AutoPlanogram\Template\SlotReviewAnalysisService;
use App\Services\AutoPlanogram\Template\TemplateSlotService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplateSlotController extends Controller
{
    use InteractsWithSyncImageDownLoad;
    use InteractsWithTenantContext;

    public function __construct(
        private readonly TemplateSlotService $service,
        private readonly SlotReviewAnalysisService $reviewAnalysisService,
    ) {}

    public function index(string $subdomain, PlanogramTemplate $planogramTemplate): Response
    {
        unset($subdomain);
        $this->authorize('view', $planogramTemplate);

        $planogramTemplate->load(['subtemplates.slots.category']);

        return Inertia::render('tenant/planogram-templates/Slots', [
            'subdomain' => $this->tenantSubdomain(),
            'template' => $this->service->templateData($planogramTemplate),
            'subtemplates' => $this->service->subtemplatesData($planogramTemplate),
        ]);
    }

    public function review(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate): Response
    {
        unset($subdomain);
        $this->authorize('view', $planogramTemplate);
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'slot_id' => ['nullable', 'string'],
            'module' => ['nullable', 'integer', 'min:1', 'max:20'],
            'shelf_width_cm' => ['nullable', 'numeric', 'min:30', 'max:500'],
        ]);

        $planogramTemplate->load(['subtemplates.slots.category']);

        $currentModule = (int) ($validated['module'] ?? 1);

        $selectedSlotId = is_string($validated['slot_id'] ?? null) && $validated['slot_id'] !== ''
            ? $validated['slot_id']
            : null;

        $slotAnalysis = null;

        if ($selectedSlotId !== null) {
            $slot = PlanogramTemplateSlot::query()
                ->whereKey($selectedSlotId)
                ->whereHas('subtemplate', fn ($query) => $query->where('template_id', $planogramTemplate->getKey()))
                ->first();

            if ($slot !== null) {
                $slotAnalysis = $this->reviewAnalysisService->analyze(
                    $slot,
                    (float) ($validated['shelf_width_cm'] ?? 100.0),
                );
            } else {
                $selectedSlotId = null;
            }
        }

        return Inertia::render('tenant/planogram-templates/Review', [
            'subdomain' => $this->tenantSubdomain(),
            'template' => $this->service->templateData($planogramTemplate),
            'subtemplates' => $this->service->subtemplatesData($planogramTemplate),
            'current_module' => $currentModule,
            'selected_slot_id' => $selectedSlotId,
            'slot_analysis' => $slotAnalysis,
        ]);
    }

    public function createSubtemplate(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $validated = $request->validate([
            'num_modules' => ['required', 'integer', 'min:1', 'max:6'],
        ]);

        $this->service->createSubtemplate($planogramTemplate, $validated['num_modules'], [
            'tenant_id' => $this->tenantId(),
        ]);

        $planogramTemplate->load(['subtemplates.slots.category']);

        return redirect()->route('tenant.planogram-templates.slots.index', [
            'subdomain' => $this->tenantSubdomain(),
            'planogramTemplate' => $planogramTemplate->id,
        ]);
    }

    public function cloneSubtemplate(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate, PlanogramSubtemplate $planogramSubtemplate): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $validated = $request->validate([
            'target_modules' => ['required', 'integer', 'min:'.($planogramSubtemplate->num_modules + 1), 'max:20'],
        ]);

        if ($planogramTemplate->subtemplates()->where('num_modules', $validated['target_modules'])->exists()) {
            return back()->withErrors(['target_modules' => "Já existe um subtemplate com {$validated['target_modules']} módulos para este template."]);
        }

        $planogramSubtemplate->cloneWithSlots($validated['target_modules']);

        return redirect()->route('tenant.planogram-templates.slots.index', [
            'subdomain' => $this->tenantSubdomain(),
            'planogramTemplate' => $planogramTemplate->id,
        ]);
    }

    public function destroySubtemplate(string $subdomain, PlanogramTemplate $planogramTemplate, PlanogramSubtemplate $planogramSubtemplate): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $planogramSubtemplate->slots()->delete();
        $planogramSubtemplate->delete();

        $planogramTemplate->load(['subtemplates.slots.category']);

        return redirect()->route('tenant.planogram-templates.slots.index', [
            'subdomain' => $this->tenantSubdomain(),
            'planogramTemplate' => $planogramTemplate->id,
        ]);
    }

    public function storeSlot(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate, PlanogramSubtemplate $planogramSubtemplate): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $validated = $this->service->validateSlot($request);

        $this->service->storeSlot($planogramSubtemplate, $validated, [
            'tenant_id' => $this->tenantId(),
        ]);

        $planogramTemplate->load(['subtemplates.slots.category']);

        return redirect()->route('tenant.planogram-templates.slots.index', [
            'subdomain' => $this->tenantSubdomain(),
            'planogramTemplate' => $planogramTemplate->id,
        ]);
    }

    public function updateSlot(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate, PlanogramTemplateSlot $planogramTemplateSlot): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $validated = $this->service->validateSlot($request);

        $this->service->updateSlot($planogramTemplateSlot, $validated);

        $planogramTemplate->load(['subtemplates.slots.category']);

        return redirect()->route('tenant.planogram-templates.slots.index', [
            'subdomain' => $this->tenantSubdomain(),
            'planogramTemplate' => $planogramTemplate->id,
        ]);
    }

    public function destroySlot(string $subdomain, PlanogramTemplate $planogramTemplate, PlanogramTemplateSlot $planogramTemplateSlot): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $this->service->destroySlot($planogramTemplateSlot);

        $planogramTemplate->load(['subtemplates.slots.category']);

        return redirect()->route('tenant.planogram-templates.slots.index', [
            'subdomain' => $this->tenantSubdomain(),
            'planogramTemplate' => $planogramTemplate->id,
        ]);
    }

    public function reorder(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $validated = $this->service->validateReorder($request);

        $this->service->reorderSlots($planogramTemplate, $validated);

        $planogramTemplate->load(['subtemplates.slots.category']);

        return redirect()->route('tenant.planogram-templates.slots.index', [
            'subdomain' => $this->tenantSubdomain(),
            'planogramTemplate' => $planogramTemplate->id,
        ]);
    }

    public function slotProducts(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate): JsonResponse
    {
        unset($subdomain);
        $this->authorize('view', $planogramTemplate);
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'category_id' => ['required', 'string', 'max:26'],
        ]);

        $categoryIds = Category::getDescendantIds($validated['category_id']);

        $products = Product::query()
            ->select(['id', 'name', 'ean', 'brand', 'category_id'])
            ->whereIn('category_id', $categoryIds)
            ->orderBy('name')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $products->map(fn (Product $product): array => [
                'id' => (string) $product->id,
                'name' => (string) $product->name,
                'ean' => (string) ($product->ean ?? ''),
                'brand' => (string) ($product->brand ?? ''),
                'category_id' => (string) ($product->category_id ?? ''),
            ])->values()->all(),
        ]);
    }

    public function slotAnalysis(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate): JsonResponse
    {
        unset($subdomain);
        $this->authorize('view', $planogramTemplate);
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'slot_id' => ['required', 'string'],
            'shelf_width_cm' => ['nullable', 'numeric', 'min:30', 'max:500'],
        ]);

        $slot = PlanogramTemplateSlot::query()
            ->whereKey($validated['slot_id'])
            ->whereHas('subtemplate', fn ($query) => $query->where('template_id', $planogramTemplate->getKey()))
            ->firstOrFail();

        $analysis = $this->reviewAnalysisService->analyze(
            $slot,
            (float) ($validated['shelf_width_cm'] ?? 100.0),
        );

        return response()->json([
            'data' => $analysis,
        ]);
    }

    public function syncImages(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate): RedirectResponse
    {
        unset($subdomain);
        $this->authorize('view', $planogramTemplate);

        return $this->updateImages($request);
    }
}
