<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\GlobalPlanogramSubtemplate;
use App\Models\GlobalPlanogramTemplate;
use App\Models\GlobalPlanogramTemplateSlot;
use App\Services\AutoPlanogram\Template\TemplateSlotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GlobalTemplateSlotController extends Controller
{
    public function __construct(private readonly TemplateSlotService $service) {}

    public function index(GlobalPlanogramTemplate $globalPlanogramTemplate): Response
    {
        $this->authorize('view', $globalPlanogramTemplate);

        $globalPlanogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('landlord/planogram-templates/Slots', [
            'template' => $this->service->templateData($globalPlanogramTemplate),
            'subtemplates' => $this->service->subtemplatesData($globalPlanogramTemplate),
        ]);
    }

    public function createSubtemplate(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate): RedirectResponse
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $validated = $request->validate([
            'num_modules' => ['required', 'integer', 'min:1', 'max:6'],
        ]);

        $this->service->createSubtemplate($globalPlanogramTemplate, $validated['num_modules']);

        $globalPlanogramTemplate->load(['subtemplates.slots']);

        return  redirect()->route('landlord.planogram-templates.slots.index', [
            'globalPlanogramTemplate' => $globalPlanogramTemplate->id,
        ]);
    }

    public function storeSlot(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate, GlobalPlanogramSubtemplate $globalPlanogramSubtemplate): RedirectResponse
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $validated = $this->service->validateSlot($request);

        $this->service->storeSlot($globalPlanogramSubtemplate, $validated);

        $globalPlanogramTemplate->load(['subtemplates.slots']);

        return  redirect()->route('landlord.planogram-templates.slots.index', [
            'globalPlanogramTemplate' => $globalPlanogramTemplate->id,
        ]);
    }

    public function updateSlot(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate, GlobalPlanogramTemplateSlot $globalPlanogramTemplateSlot): RedirectResponse
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $validated = $this->service->validateSlot($request);

        $this->service->updateSlot($globalPlanogramTemplateSlot, $validated);

        $globalPlanogramTemplate->load(['subtemplates.slots']);

        return  redirect()->route('landlord.planogram-templates.slots.index', [
            'globalPlanogramTemplate' => $globalPlanogramTemplate->id,
        ]);
    }

    public function destroySlot(GlobalPlanogramTemplate $globalPlanogramTemplate, GlobalPlanogramTemplateSlot $globalPlanogramTemplateSlot): RedirectResponse
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $this->service->destroySlot($globalPlanogramTemplateSlot);

        $globalPlanogramTemplate->load(['subtemplates.slots']);

        return  redirect()->route('landlord.planogram-templates.slots.index', [
            'globalPlanogramTemplate' => $globalPlanogramTemplate->id,
        ]);
    }

    public function reorder(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate): RedirectResponse
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $validated = $this->service->validateReorder($request);

        $this->service->reorderSlots($globalPlanogramTemplate, $validated);

        $globalPlanogramTemplate->load(['subtemplates.slots']);

        return  redirect()->route('landlord.planogram-templates.slots.index', [
            'globalPlanogramTemplate' => $globalPlanogramTemplate->id,
        ]);
    }
}
