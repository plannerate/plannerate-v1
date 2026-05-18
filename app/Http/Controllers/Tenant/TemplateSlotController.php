<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\InteractsWithDeferredIndex;
use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplate;
use App\Models\PlanogramTemplateSlot;
use App\Support\Tenancy\InteractsWithTenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplateSlotController extends Controller
{
    use InteractsWithDeferredIndex;
    use InteractsWithTenantContext;

    public function index(string $subdomain, PlanogramTemplate $planogramTemplate): Response
    {
        unset($subdomain);
        $this->authorize('view', $planogramTemplate);

        $planogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('tenant/planogram-templates/Slots', [
            'subdomain' => $this->tenantSubdomain(),
            'template' => $this->templateData($planogramTemplate),
            'subtemplates' => $this->subtemplatesData($planogramTemplate),
        ]);
    }

    public function createSubtemplate(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate): Response
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $validated = $request->validate([
            'num_modules' => ['required', 'integer', 'min:1', 'max:6'],
        ]);

        $planogramTemplate->subtemplates()->firstOrCreate(
            ['num_modules' => $validated['num_modules']],
            [
                'tenant_id' => $this->tenantId(),
                'code' => $planogramTemplate->code.'-'.$validated['num_modules'].'M',
                'is_active' => true,
            ],
        );

        $planogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('tenant/planogram-templates/Slots', [
            'subtemplates' => $this->subtemplatesData($planogramTemplate),
        ]);
    }

    public function storeSlot(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate, PlanogramSubtemplate $planogramSubtemplate): Response
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $validated = $this->validateSlot($request);

        $planogramSubtemplate->slots()
            ->where('module_number', $validated['module_number'])
            ->where('shelf_order', $validated['shelf_order'])
            ->delete();

        $nextOrdering = (int) ($planogramSubtemplate->slots()->max('ordering')) + 1;

        $planogramSubtemplate->slots()->create([
            ...$validated,
            'tenant_id' => $this->tenantId(),
            'grouping_normalized' => $this->normalizeGrouping($validated['grouping']),
            'ordering' => $nextOrdering,
        ]);

        $planogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('tenant/planogram-templates/Slots', [
            'subtemplates' => $this->subtemplatesData($planogramTemplate),
        ]);
    }

    public function updateSlot(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate, PlanogramTemplateSlot $planogramTemplateSlot): Response
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $validated = $this->validateSlot($request);

        $planogramTemplateSlot->update([
            ...$validated,
            'grouping_normalized' => $this->normalizeGrouping($validated['grouping']),
        ]);

        $planogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('tenant/planogram-templates/Slots', [
            'subtemplates' => $this->subtemplatesData($planogramTemplate),
        ]);
    }

    public function destroySlot(string $subdomain, PlanogramTemplate $planogramTemplate, PlanogramTemplateSlot $planogramTemplateSlot): Response
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $planogramTemplateSlot->delete();

        $planogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('tenant/planogram-templates/Slots', [
            'subtemplates' => $this->subtemplatesData($planogramTemplate),
        ]);
    }

    public function reorder(Request $request, string $subdomain, PlanogramTemplate $planogramTemplate): Response
    {
        unset($subdomain);
        $this->authorize('update', $planogramTemplate);

        $validated = $request->validate([
            'subtemplate_id' => ['required', 'string'],
            'from.module_number' => ['required', 'integer', 'min:1'],
            'from.shelf_order' => ['required', 'integer', 'min:1'],
            'to.module_number' => ['required', 'integer', 'min:1'],
            'to.shelf_order' => ['required', 'integer', 'min:1'],
        ]);

        $subtemplate = $planogramTemplate->subtemplates()->findOrFail($validated['subtemplate_id']);

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

        $planogramTemplate->load(['subtemplates.slots']);

        return Inertia::render('tenant/planogram-templates/Slots', [
            'subtemplates' => $this->subtemplatesData($planogramTemplate),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function templateData(PlanogramTemplate $planogramTemplate): array
    {
        return [
            'id' => $planogramTemplate->id,
            'code' => $planogramTemplate->code,
            'name' => $planogramTemplate->name,
            'department' => $planogramTemplate->department,
            'is_active' => $planogramTemplate->is_active,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function subtemplatesData(PlanogramTemplate $planogramTemplate): array
    {
        return $planogramTemplate->subtemplates
            ->map(fn (PlanogramSubtemplate $sub): array => [
                'id' => $sub->id,
                'code' => $sub->code,
                'num_modules' => $sub->num_modules,
                'slots' => $sub->slots->map(fn (PlanogramTemplateSlot $slot): array => [
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
