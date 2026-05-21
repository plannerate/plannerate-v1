<?php

namespace App\Services\AutoPlanogram\Template;

use App\Models\PlanogramRejectedProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class TemplateSlotService
{
    /** @return array<string, mixed> */
    public function validateSlot(Request $request): array
    {
        return $request->validate([
            'module_number' => ['required', 'integer', 'min:1', 'max:6'],
            'shelf_order' => ['required', 'integer', 'min:1', 'max:10'],
            'category_id' => ['nullable', 'string', 'max:26'],
            'category' => ['nullable', 'string', 'max:255'],
            'subcategory' => ['nullable', 'string', 'max:255'],
            'min_facings' => ['required', 'integer', 'min:1', 'max:20'],
            'max_facings' => ['required', 'integer', 'min:1', 'max:20'],
            'priority' => ['required', 'integer', 'min:1', 'max:10'],
            'price_order' => ['required', 'string', 'in:asc,desc,none'],
            'size_order' => ['required', 'string', 'in:asc,desc,none'],
            'brand_exposure' => ['required', 'string', 'in:vertical,horizontal,mixed'],
            'flavor_exposure' => ['required', 'string', 'in:vertical,horizontal,mixed'],
            'space_fallback' => ['required', 'string', 'in:reduce_c,reduce_facings,skip'],
            'use_target_stock' => ['boolean'],
            'facing_expansion' => ['required', 'string', 'in:none,score,current_stock,equal'],
        ]);
    }

    /** @return array<string, mixed> */
    public function validateReorder(Request $request): array
    {
        return $request->validate([
            'subtemplate_id' => ['required', 'string'],
            'from.module_number' => ['required', 'integer', 'min:1'],
            'from.shelf_order' => ['required', 'integer', 'min:1'],
            'to.module_number' => ['required', 'integer', 'min:1'],
            'to.shelf_order' => ['required', 'integer', 'min:1'],
        ]);
    }

    /** @return array<string, mixed> */
    public function validateSlotDefaults(Request $request): array
    {
        return $request->validate([
            'category_id' => ['nullable', 'string', 'max:26'],
            'min_facings' => ['required', 'integer', 'min:1', 'max:20'],
            'max_facings' => ['required', 'integer', 'min:1', 'max:20'],
            'priority' => ['required', 'integer', 'min:1', 'max:10'],
            'price_order' => ['required', 'string', 'in:asc,desc,none'],
            'size_order' => ['required', 'string', 'in:asc,desc,none'],
            'brand_exposure' => ['required', 'string', 'in:vertical,horizontal,mixed'],
            'flavor_exposure' => ['required', 'string', 'in:vertical,horizontal,mixed'],
            'space_fallback' => ['required', 'string', 'in:reduce_c,reduce_facings,skip'],
            'use_target_stock' => ['boolean'],
            'facing_expansion' => ['required', 'string', 'in:none,score,current_stock,equal'],
        ]);
    }

    /** @param array<string, mixed> $extra */
    public function createSubtemplate(Model $template, int $numModules, array $extra = []): void
    {
        $existing = $template->subtemplates()
            ->withTrashed()
            ->where('num_modules', $numModules)
            ->first();

        if ($existing !== null) {
            $existing->restore();
            $existing->update([
                ...$extra,
                'code' => $template->code.'-'.$numModules.'M',
                'is_active' => true,
            ]);

            return;
        }

        $template->subtemplates()->create([
            'num_modules' => $numModules,
            ...$extra,
            'code' => $template->code.'-'.$numModules.'M',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $extra
     */
    public function storeSlot(Model $subtemplate, array $validated, array $extra = []): void
    {
        $subtemplate->slots()
            ->where('module_number', $validated['module_number'])
            ->where('shelf_order', $validated['shelf_order'])
            ->delete();

        $nextOrdering = (int) ($subtemplate->slots()->max('ordering')) + 1;
        $normalized = $this->normalizeSlotPayload($validated);

        $subtemplate->slots()->create([
            ...$normalized,
            ...$extra,
            'ordering' => $nextOrdering,
        ]);

        $this->updateSubtemplateSlotDefaults($subtemplate, $normalized);
    }

    /** @param array<string, mixed> $validated */
    public function updateSlot(Model $slot, array $validated): void
    {
        $normalized = $this->normalizeSlotPayload($validated);

        $slot->update($normalized);

        $subtemplate = $slot->subtemplate;
        if ($subtemplate !== null) {
            $this->updateSubtemplateSlotDefaults($subtemplate, $normalized);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeSlotPayload(array $payload): array
    {
        $payload['category'] = is_string($payload['category'] ?? null)
            ? trim((string) $payload['category'])
            : '';

        $payload['subcategory'] = is_string($payload['subcategory'] ?? null)
            ? trim((string) $payload['subcategory'])
            : '';

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $slotPayload
     */
    private function updateSubtemplateSlotDefaults(Model $subtemplate, array $slotPayload): void
    {
        $subtemplate->update([
            'slot_defaults' => [
                'category_id' => is_string($slotPayload['category_id'] ?? null) && $slotPayload['category_id'] !== ''
                    ? $slotPayload['category_id']
                    : null,
                'min_facings' => (int) ($slotPayload['min_facings'] ?? 1),
                'max_facings' => (int) ($slotPayload['max_facings'] ?? 5),
                'priority' => (int) ($slotPayload['priority'] ?? 1),
                'price_order' => (string) ($slotPayload['price_order'] ?? 'none'),
                'size_order' => (string) ($slotPayload['size_order'] ?? 'none'),
                'brand_exposure' => (string) ($slotPayload['brand_exposure'] ?? 'horizontal'),
                'flavor_exposure' => (string) ($slotPayload['flavor_exposure'] ?? 'horizontal'),
                'space_fallback' => (string) ($slotPayload['space_fallback'] ?? 'reduce_c'),
                'use_target_stock' => (bool) ($slotPayload['use_target_stock'] ?? false),
                'facing_expansion' => (string) ($slotPayload['facing_expansion'] ?? 'none'),
            ],
        ]);
    }

    /** @param array<string, mixed> $validated */
    public function updateSlotDefaults(Model $subtemplate, array $validated): void
    {
        $this->updateSubtemplateSlotDefaults($subtemplate, $validated);
    }

    public function destroySlot(Model $slot): void
    {
        $slot->delete();
    }

    /** @param array<string, mixed> $validated */
    public function reorderSlots(Model $template, array $validated): void
    {
        $subtemplate = $template->subtemplates()->findOrFail($validated['subtemplate_id']);

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
    }

    /** @return array<string, mixed> */
    public function templateData(Model $template): array
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
    public function subtemplatesData(Model $template): array
    {
        $slotIds = $template->subtemplates
            ->flatMap(fn (Model $sub): array => $sub->slots->pluck('id')->all())
            ->all();

        $rejectedCounts = $slotIds !== []
            ? PlanogramRejectedProduct::whereIn('slot_id', $slotIds)
                ->selectRaw('slot_id, count(*) as total')
                ->groupBy('slot_id')
                ->pluck('total', 'slot_id')
            : collect();

        return $template->subtemplates
            ->map(fn (Model $sub): array => [
                'id' => $sub->id,
                'code' => $sub->code,
                'num_modules' => $sub->num_modules,
                'slot_defaults' => $sub->slot_defaults,
                'slots' => $sub->slots->map(function (Model $slot) use ($rejectedCounts): array {
                    $category = $slot->relationLoaded('category') ? $slot->getRelation('category') : null;

                    return [
                        'id' => $slot->id,
                        'subtemplate_id' => $slot->subtemplate_id,
                        'category_id' => $slot->category_id,
                        'category_name' => $category?->name,
                        'category_path' => $category?->full_path,
                        'module_number' => $slot->module_number,
                        'shelf_order' => $slot->shelf_order,
                        'min_facings' => $slot->min_facings,
                        'max_facings' => $slot->max_facings,
                        'priority' => $slot->priority,
                        'price_order' => $slot->price_order->value,
                        'size_order' => $slot->size_order->value,
                        'brand_exposure' => $slot->brand_exposure->value,
                        'flavor_exposure' => $slot->flavor_exposure->value,
                        'space_fallback' => $slot->space_fallback->value,
                        'use_target_stock' => $slot->use_target_stock,
                        'facing_expansion' => $slot->facing_expansion->value,
                        'ordering' => $slot->ordering,
                        'rejected_count' => (int) ($rejectedCounts->get($slot->id) ?? 0),
                    ];
                })->values()->all(),
            ])
            ->values()
            ->all();
    }
}
