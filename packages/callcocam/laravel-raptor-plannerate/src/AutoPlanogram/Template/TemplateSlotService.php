<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Template;

use Callcocam\LaravelRaptorPlannerate\Concerns\UsesPlannerateTenantDatabase;
use Callcocam\LaravelRaptorPlannerate\Enums\FlowDirection;
use Callcocam\LaravelRaptorPlannerate\Enums\LayoutOrientation;
use Callcocam\LaravelRaptorPlannerate\Enums\ZonePriority;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramRejectedProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class TemplateSlotService
{
    use UsesPlannerateTenantDatabase;

    /**
     * Regras de validação de um slot, com prefixo opcional para validação em lote.
     *
     * @return array<string, array<int, string>>
     */
    private function slotRules(string $prefix = ''): array
    {
        return [
            "{$prefix}module_number" => ['required', 'integer', 'min:1', 'max:6'],
            "{$prefix}shelf_order" => ['required', 'integer', 'min:1', 'max:10'],
            "{$prefix}category_id" => ['nullable', 'string', 'max:26'],
            "{$prefix}min_facings" => ['required', 'integer', 'min:1', 'max:20'],
            "{$prefix}max_facings" => ['required', 'integer', 'min:1', 'max:20'],
            "{$prefix}priority" => ['required', 'integer', 'min:1', 'max:10'],
            "{$prefix}price_order" => ['required', 'string', 'in:asc,desc,none'],
            "{$prefix}size_order" => ['required', 'string', 'in:asc,desc,none'],
            "{$prefix}brand_exposure" => ['required', 'string', 'in:vertical,horizontal,mixed'],
            "{$prefix}flavor_exposure" => ['required', 'string', 'in:vertical,horizontal,mixed'],
            "{$prefix}space_fallback" => ['required', 'string', 'in:reduce_c,reduce_facings,skip,remove_dog'],
            "{$prefix}use_target_stock" => ['boolean'],
            "{$prefix}facing_expansion" => ['required', 'string', 'in:none,score,current_stock,target_stock,equal'],
            "{$prefix}role_override" => ['nullable', 'string', 'in:destino,rotina,conveniencia,impulso,sazonal,complementar'],
            "{$prefix}visual_criteria" => ['nullable', 'array'],
            "{$prefix}visual_criteria.*.key" => ['required_with:'."{$prefix}visual_criteria", 'string', 'in:marca,preco,tamanho,score_abc,margem,embalagem,tipo,sabor,atributo'],
            "{$prefix}visual_criteria.*.direction" => ['required_with:'."{$prefix}visual_criteria", 'string', 'in:asc,desc,none'],
            "{$prefix}visual_criteria.*.packaging_order" => ['nullable', 'array'],
            "{$prefix}visual_criteria.*.packaging_order.*" => ['string', 'max:100'],
            "{$prefix}max_share_per_sku" => ['nullable', 'integer', 'min:1', 'max:100'],
            "{$prefix}max_share_per_brand" => ['nullable', 'integer', 'min:1', 'max:100'],
            "{$prefix}max_share_per_subcategory" => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, mixed> */
    public function validateSlot(Request $request): array
    {
        $validated = $request->validate($this->slotRules());

        if ((int) ($validated['max_facings'] ?? 1) < (int) ($validated['min_facings'] ?? 1)) {
            throw ValidationException::withMessages([
                'max_facings' => ['Frentes máximas deve ser maior ou igual às frentes mínimas.'],
            ]);
        }

        $this->validateAbcFirst($validated['visual_criteria'] ?? null, 'visual_criteria');

        return $validated;
    }

    /**
     * Valida a criação em lote de slots (cópia em cascata para prateleiras/módulos).
     *
     * @return array{slots: array<int, array<string, mixed>>}
     */
    public function validateBulkSlots(Request $request): array
    {
        $rules = [
            'slots' => ['required', 'array', 'min:1', 'max:60'],
            ...$this->slotRules('slots.*.'),
        ];

        $validated = $request->validate($rules);

        foreach ($validated['slots'] as $index => $slot) {
            if ((int) ($slot['max_facings'] ?? 1) < (int) ($slot['min_facings'] ?? 1)) {
                throw ValidationException::withMessages([
                    "slots.{$index}.max_facings" => ['Frentes máximas deve ser maior ou igual às frentes mínimas.'],
                ]);
            }

            $this->validateAbcFirst($slot['visual_criteria'] ?? null, "slots.{$index}.visual_criteria");
        }

        return $validated;
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
        $zonePriorityValues = implode(',', array_column(ZonePriority::cases(), 'value'));

        $validated = $request->validate([
            'category_id' => ['nullable', 'string', 'max:26'],
            'min_facings' => ['required', 'integer', 'min:1', 'max:20'],
            'max_facings' => ['required', 'integer', 'min:1', 'max:20'],
            'priority' => ['required', 'integer', 'min:1', 'max:10'],
            'price_order' => ['required', 'string', 'in:asc,desc,none'],
            'size_order' => ['required', 'string', 'in:asc,desc,none'],
            'brand_exposure' => ['required', 'string', 'in:vertical,horizontal,mixed'],
            'flavor_exposure' => ['required', 'string', 'in:vertical,horizontal,mixed'],
            'space_fallback' => ['required', 'string', 'in:reduce_c,reduce_facings,skip,remove_dog'],
            'use_target_stock' => ['boolean'],
            'facing_expansion' => ['required', 'string', 'in:none,score,current_stock,target_stock,equal'],
            'hot_zone_priority' => ['nullable', 'string', "in:{$zonePriorityValues}"],
            'cold_zone_priority' => ['nullable', 'string', "in:{$zonePriorityValues}"],
            'flow_direction' => ['nullable', 'string', 'in:'.implode(',', array_column(FlowDirection::cases(), 'value'))],
            'layout_orientation' => ['nullable', 'string', 'in:'.implode(',', array_column(LayoutOrientation::cases(), 'value'))],
        ]);

        if ((int) ($validated['max_facings'] ?? 1) < (int) ($validated['min_facings'] ?? 1)) {
            throw ValidationException::withMessages([
                'max_facings' => ['Frentes máximas deve ser maior ou igual às frentes mínimas.'],
            ]);
        }

        return $validated;
    }

    /**
     * Lança ValidationException se visual_criteria não-nulo não começar com score_abc.
     * score_abc na posição 0 é requisito do spec (Etapa 9) e garante que a
     * inteligência ABC sempre domina o visual sort no placement engine.
     *
     * @param  array<int, array{key: string, direction: string}>|null  $criteria
     */
    private function validateAbcFirst(?array $criteria, string $field): void
    {
        if ($criteria === null || $criteria === []) {
            return;
        }

        if (($criteria[0]['key'] ?? '') !== 'score_abc') {
            throw ValidationException::withMessages([
                $field => ['O critério "Curva ABC" deve ser sempre o primeiro na lista de ordenação visual.'],
            ]);
        }
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

        $subtemplate->slots()->create([
            ...$validated,
            ...$extra,
            'ordering' => $nextOrdering,
        ]);

        $this->updateSubtemplateSlotDefaults($subtemplate, $validated);
    }

    /**
     * Cria/sobrescreve vários slots de uma vez (cópia em cascata).
     *
     * @param  array<int, array<string, mixed>>  $slots
     * @param  array<string, mixed>  $extra
     */
    public function bulkStoreSlots(Model $subtemplate, array $slots, array $extra = []): void
    {
        if ($slots === []) {
            return;
        }

        $this->plannerateTenantDatabase()->transaction(function () use ($subtemplate, $slots, $extra): void {
            $nextOrdering = (int) ($subtemplate->slots()->max('ordering')) + 1;

            foreach ($slots as $slot) {
                $subtemplate->slots()
                    ->where('module_number', $slot['module_number'])
                    ->where('shelf_order', $slot['shelf_order'])
                    ->delete();

                $subtemplate->slots()->create([
                    ...$slot,
                    ...$extra,
                    'ordering' => $nextOrdering,
                ]);

                $nextOrdering++;
            }

            $this->updateSubtemplateSlotDefaults($subtemplate, $slots[0]);
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateSlot(Model $slot, array $validated): void
    {
        $slot->update($validated);

        $subtemplate = $slot->subtemplate;
        if ($subtemplate !== null) {
            $this->updateSubtemplateSlotDefaults($subtemplate, $validated);
        }
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

        // Zone priorities, flow direction e layout orientation são colunas dedicadas, não parte do slot_defaults JSON
        $subtemplate->update([
            'hot_zone_priority' => $validated['hot_zone_priority'] ?? null,
            'cold_zone_priority' => $validated['cold_zone_priority'] ?? null,
            'flow_direction' => $validated['flow_direction'] ?? null,
            'layout_orientation' => $validated['layout_orientation'] ?? null,
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
            'category_id' => $template->category_id ?? null,
            'category_name' => $template->category?->name ?? null,
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
                'hot_zone_priority' => $sub->hot_zone_priority?->value,
                'cold_zone_priority' => $sub->cold_zone_priority?->value,
                'flow_direction' => $sub->flow_direction?->value,
                'layout_orientation' => $sub->layout_orientation?->value,
                'slots' => $sub->slots->map(function (Model $slot) use ($rejectedCounts): array {
                    $category = $slot->relationLoaded('category') ? $slot->getRelation('category') : null;

                    return [
                        'id' => $slot->id,
                        'subtemplate_id' => $slot->subtemplate_id,
                        'category_id' => $slot->category_id,
                        'category_name' => $category?->name,
                        'category_path' => $category?->full_path,
                        'category_role' => $category?->role?->value,
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
                        'role_override' => $slot->role_override?->value,
                        'visual_criteria' => $slot->visual_criteria,
                        'max_share_per_sku' => $slot->max_share_per_sku,
                        'max_share_per_brand' => $slot->max_share_per_brand,
                        'max_share_per_subcategory' => $slot->max_share_per_subcategory,
                        'ordering' => $slot->ordering,
                        'rejected_count' => (int) ($rejectedCounts->get($slot->id) ?? 0),
                    ];
                })->values()->all(),
            ])
            ->values()
            ->all();
    }
}
