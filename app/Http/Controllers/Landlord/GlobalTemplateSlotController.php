<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\InteractsWithDeferredIndex;
use App\Http\Controllers\Controller;
use App\Models\GlobalPlanogramSubtemplate;
use App\Models\GlobalPlanogramTemplate;
use App\Models\GlobalPlanogramTemplateSlot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GlobalTemplateSlotController extends Controller
{
    use InteractsWithDeferredIndex;

    public function index(GlobalPlanogramTemplate $globalPlanogramTemplate): Response
    {
        $this->authorize('view', $globalPlanogramTemplate);

        $globalPlanogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('landlord/planogram-templates/Slots', [
            'template' => $this->templateData($globalPlanogramTemplate),
            'subtemplates' => $this->subtemplatesData($globalPlanogramTemplate),
        ]);
    }

    public function createSubtemplate(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate): Response
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $validated = $request->validate([
            'num_modules' => ['required', 'integer', 'min:1', 'max:6'],
        ]);

        $globalPlanogramTemplate->subtemplates()->firstOrCreate(
            ['num_modules' => $validated['num_modules']],
            [
                'code' => $globalPlanogramTemplate->code.'-'.$validated['num_modules'].'M',
                'is_active' => true,
            ],
        );

        $globalPlanogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('landlord/planogram-templates/Slots', [
            'subtemplates' => $this->subtemplatesData($globalPlanogramTemplate),
        ]);
    }

    public function storeSlot(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate, GlobalPlanogramSubtemplate $globalPlanogramSubtemplate): Response
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $validated = $this->validateSlot($request);

        $globalPlanogramSubtemplate->slots()
            ->where('module_number', $validated['module_number'])
            ->where('shelf_order', $validated['shelf_order'])
            ->delete();

        $nextOrdering = (int) ($globalPlanogramSubtemplate->slots()->max('ordering')) + 1;

        $globalPlanogramSubtemplate->slots()->create([
            ...$validated,
            'grouping_normalized' => $this->normalizeGrouping($validated['grouping']),
            'ordering' => $nextOrdering,
        ]);

        $globalPlanogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('landlord/planogram-templates/Slots', [
            'subtemplates' => $this->subtemplatesData($globalPlanogramTemplate),
        ]);
    }

    public function updateSlot(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate, GlobalPlanogramTemplateSlot $globalPlanogramTemplateSlot): Response
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $validated = $this->validateSlot($request);

        $globalPlanogramTemplateSlot->update([
            ...$validated,
            'grouping_normalized' => $this->normalizeGrouping($validated['grouping']),
        ]);

        $globalPlanogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('landlord/planogram-templates/Slots', [
            'subtemplates' => $this->subtemplatesData($globalPlanogramTemplate),
        ]);
    }

    public function destroySlot(GlobalPlanogramTemplate $globalPlanogramTemplate, GlobalPlanogramTemplateSlot $globalPlanogramTemplateSlot): Response
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $globalPlanogramTemplateSlot->delete();

        $globalPlanogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('landlord/planogram-templates/Slots', [
            'subtemplates' => $this->subtemplatesData($globalPlanogramTemplate),
        ]);
    }

    public function reorder(Request $request, GlobalPlanogramTemplate $globalPlanogramTemplate): Response
    {
        $this->authorize('update', $globalPlanogramTemplate);

        $validated = $request->validate([
            'subtemplate_id' => ['required', 'string'],
            'from.module_number' => ['required', 'integer', 'min:1'],
            'from.shelf_order' => ['required', 'integer', 'min:1'],
            'to.module_number' => ['required', 'integer', 'min:1'],
            'to.shelf_order' => ['required', 'integer', 'min:1'],
        ]);

        $subtemplate = $globalPlanogramTemplate->subtemplates()->findOrFail($validated['subtemplate_id']);

        $fromSlot = $subtemplate->slots()
            ->where('module_number', $validated['from']['module_number'])
            ->where('shelf_order', $validated['from']['shelf_order'])
            ->first();

        $toSlot = $subtemplate->slots()
            ->where('module_number', $validated['to']['module_number'])
            ->where('shelf_order', $validated['to']['shelf_order'])
            ->first();

        if ($fromSlot !== null) {
            $fromSlot->update([
                'module_number' => $validated['to']['module_number'],
                'shelf_order' => $validated['to']['shelf_order'],
            ]);
        }

        if ($toSlot !== null) {
            $toSlot->update([
                'module_number' => $validated['from']['module_number'],
                'shelf_order' => $validated['from']['shelf_order'],
            ]);
        }

        $globalPlanogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('landlord/planogram-templates/Slots', [
            'subtemplates' => $this->subtemplatesData($globalPlanogramTemplate),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function templateData(GlobalPlanogramTemplate $template): array
    {
        return [
            'id' => $template->id,
            'code' => $template->code,
            'name' => $template->name,
            'department' => $template->department,
            'is_active' => $template->is_active,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function subtemplatesData(GlobalPlanogramTemplate $template): array
    {
        return $template->subtemplates
            ->map(fn (GlobalPlanogramSubtemplate $sub): array => [
                'id' => $sub->id,
                'code' => $sub->code,
                'num_modules' => $sub->num_modules,
                'slots' => $sub->slots->map(fn (GlobalPlanogramTemplateSlot $slot): array => [
                    'id' => $slot->id,
                    'subtemplate_id' => $slot->subtemplate_id,
                    'module_number' => $slot->module_number,
                    'shelf_order' => $slot->shelf_order,
                    'category' => $slot->category,
                    'subcategory' => $slot->subcategory,
                    'grouping' => $slot->grouping,
                    'min_facings' => $slot->min_facings,
                    'priority' => $slot->priority,
                    'price_order' => $slot->price_order->value,
                    'size_order' => $slot->size_order->value,
                    'brand_exposure' => $slot->brand_exposure->value,
                    'flavor_exposure' => $slot->flavor_exposure->value,
                    'space_fallback' => $slot->space_fallback->value,
                    'use_target_stock' => $slot->use_target_stock,
                    'ordering' => $slot->ordering,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function validateSlot(Request $request): array
    {
        return $request->validate([
            'module_number' => ['required', 'integer', 'min:1', 'max:6'],
            'shelf_order' => ['required', 'integer', 'min:1', 'max:10'],
            'grouping' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'subcategory' => ['nullable', 'string', 'max:255'],
            'min_facings' => ['required', 'integer', 'min:1', 'max:20'],
            'priority' => ['required', 'integer', 'min:1', 'max:10'],
            'price_order' => ['required', 'string', 'in:asc,desc,none'],
            'size_order' => ['required', 'string', 'in:asc,desc,none'],
            'brand_exposure' => ['required', 'string', 'in:vertical,horizontal,mixed'],
            'flavor_exposure' => ['required', 'string', 'in:vertical,horizontal,mixed'],
            'space_fallback' => ['required', 'string', 'in:reduce_c,reduce_facings,skip'],
            'use_target_stock' => ['boolean'],
        ]);
    }

    private function normalizeGrouping(string $value): string
    {
        return (string) preg_replace('/\s+/', ' ', strtolower(trim($value)));
    }
}
