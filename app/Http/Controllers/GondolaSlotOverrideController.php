<?php

namespace App\Http\Controllers;

use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\GondolaSlotOverride;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Gerencia overrides locais de configuração de geração por categoria por gôndola.
 */
class GondolaSlotOverrideController extends Controller
{
    /**
     * Salva ou atualiza o override de configuração de geração para uma categoria na gôndola.
     * Campos não enviados ficam null (significa "usar template").
     */
    public function upsert(Request $request, string $gondola): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'string', 'size:26'],
            'min_facings' => ['nullable', 'integer', 'min:1', 'max:255'],
            'max_facings' => ['nullable', 'integer', 'min:1', 'max:255'],
            'price_order' => ['nullable', 'string', 'in:asc,desc,none'],
            'size_order' => ['nullable', 'string', 'in:asc,desc,none'],
            'brand_exposure' => ['nullable', 'string', 'in:vertical,horizontal,mixed'],
            'flavor_exposure' => ['nullable', 'string', 'in:vertical,horizontal,mixed'],
            'space_fallback' => ['nullable', 'string', 'in:reduce_c,reduce_facings,skip,remove_dog'],
            'facing_expansion' => ['nullable', 'string', 'in:none,score,current_stock,target_stock,equal'],
            'use_target_stock' => ['nullable', 'boolean'],
            'role_override' => ['nullable', 'string', 'in:destino,rotina,conveniencia,impulso,sazonal,complementar'],
            'max_share_per_sku' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_share_per_brand' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_share_per_subcategory' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $gondolaModel = Gondola::findOrFail($gondola);

        $override = GondolaSlotOverride::withTrashed()
            ->where('gondola_id', $gondolaModel->id)
            ->where('category_id', $validated['category_id'])
            ->first();

        if ($override) {
            $override->restore();
            $override->fill(array_merge(['gondola_id' => $gondolaModel->id], $validated));
            $override->save();
        } else {
            GondolaSlotOverride::create(array_merge(
                ['gondola_id' => $gondolaModel->id],
                $validated,
            ));
        }

        return back();
    }

    /**
     * Remove o override (soft delete), revertendo para os defaults do template.
     */
    public function destroy(string $gondola, string $categoryId): RedirectResponse
    {
        $gondolaModel = Gondola::findOrFail($gondola);

        GondolaSlotOverride::where('gondola_id', $gondolaModel->id)
            ->where('category_id', $categoryId)
            ->delete();

        return back();
    }

    /**
     * Aplica os valores do override local de volta para todos os template slots
     * da categoria correspondente no subtemplate da gôndola.
     * Apenas campos não-nulos são propagados.
     */
    public function applyToTemplate(string $gondola, string $categoryId): RedirectResponse
    {
        $gondolaModel = Gondola::with(['sections'])->findOrFail($gondola);

        if (! $gondolaModel->template_id) {
            throw ValidationException::withMessages(['message' => 'Gôndola sem template vinculado.']);
        }

        $override = GondolaSlotOverride::where('gondola_id', $gondolaModel->id)
            ->where('category_id', $categoryId)
            ->first();

        if (! $override) {
            throw ValidationException::withMessages(['message' => 'Override não encontrado.']);
        }

        $subtemplate = $this->resolveSubtemplate($gondolaModel);

        if (! $subtemplate) {
            throw ValidationException::withMessages(['message' => 'Subtemplate não encontrado para esta gôndola.']);
        }

        $slots = PlanogramTemplateSlot::where('subtemplate_id', $subtemplate->id)
            ->where('category_id', $categoryId)
            ->get();

        if ($slots->isEmpty()) {
            throw ValidationException::withMessages(['message' => 'Nenhum slot do template encontrado para esta categoria.']);
        }

        $updateData = $this->buildTemplateUpdateData($override);

        foreach ($slots as $slot) {
            $slot->update($updateData);
        }

        return back();
    }

    /**
     * Resolve o subtemplate correspondente ao número de módulos da gôndola.
     */
    private function resolveSubtemplate(Gondola $gondola): ?PlanogramSubtemplate
    {
        $numModules = $gondola->sections->count();

        return PlanogramSubtemplate::query()
            ->where('template_id', $gondola->template_id)
            ->where('num_modules', '<=', $numModules)
            ->orderByDesc('num_modules')
            ->first();
    }

    /**
     * Monta array de campos a atualizar no template slot, ignorando campos null do override.
     *
     * @return array<string, mixed>
     */
    private function buildTemplateUpdateData(GondolaSlotOverride $override): array
    {
        $fields = [
            'min_facings',
            'max_facings',
            'price_order',
            'size_order',
            'brand_exposure',
            'flavor_exposure',
            'space_fallback',
            'facing_expansion',
            'use_target_stock',
            'role_override',
            'max_share_per_sku',
            'max_share_per_brand',
            'max_share_per_subcategory',
        ];

        $data = [];

        foreach ($fields as $field) {
            $value = $override->getRawOriginal($field);

            if ($value !== null) {
                $data[$field] = $value;
            }
        }

        return $data;
    }
}
