<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplate;
use App\Models\PlanogramTemplateSlot;
use App\Services\AutoPlanogram\Template\TemplateSlotService;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplateSlotController extends Controller
{
    use InteractsWithTenantContext;

    public function __construct(private readonly TemplateSlotService $service) {}

    public function index(string $subdomain, PlanogramTemplate $planogramTemplate): Response
    {
        unset($subdomain);
        $this->authorize('view', $planogramTemplate);

        $planogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('tenant/planogram-templates/Slots', [
            'subdomain' => $this->tenantSubdomain(),
            'template' => $this->service->templateData($planogramTemplate),
            'subtemplates' => $this->service->subtemplatesData($planogramTemplate),
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

        $planogramTemplate->load(['subtemplates.slots']);

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

        $planogramTemplate->load(['subtemplates.slots']);

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

        $planogramTemplate->load(['subtemplates.slots']);

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

        $planogramTemplate->load(['subtemplates.slots']);

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

        $planogramTemplate->load(['subtemplates.slots']);

        return  redirect()->route('tenant.planogram-templates.slots.index', [
            'subdomain' => $this->tenantSubdomain(),
            'planogramTemplate' => $planogramTemplate->id,
        ]);
    }
}
