<?php

namespace App\Services\AutoPlanogram\Template;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TemplateSlotService
{
    /** @return array<string, mixed> */
    public function validateSlot(Request $request): array
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

    public function normalizeGrouping(string $value): string
    {
        return Str::slug($value);
    }

    /** @param array<string, mixed> $extra */
    public function createSubtemplate(Model $template, int $numModules, array $extra = []): void
    {
        $template->subtemplates()->firstOrCreate(
            ['num_modules' => $numModules],
            [
                ...$extra,
                'code' => $template->code.'-'.$numModules.'M',
                'is_active' => true,
            ],
        );
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

        $subtemplate->slots()->create([
            ...$validated,
            ...$extra,
            'grouping_normalized' => $this->normalizeGrouping($validated['grouping']),
            'ordering' => $nextOrdering,
        ]);
    }

    /** @param array<string, mixed> $validated */
    public function updateSlot(Model $slot, array $validated): void
    {
        $slot->update([
            ...$validated,
            'grouping_normalized' => $this->normalizeGrouping($validated['grouping']),
        ]);
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
        return $template->subtemplates
            ->map(fn (Model $sub): array => [
                'id' => $sub->id,
                'code' => $sub->code,
                'num_modules' => $sub->num_modules,
                'slots' => $sub->slots->map(fn (Model $slot): array => [
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
}
