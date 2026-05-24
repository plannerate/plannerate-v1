<?php

namespace App\Services\AutoPlanogram\Synthesis;

use App\Models\Category;
use App\Models\PlanogramSubtemplate;
use App\Models\PlanogramTemplate;
use App\Models\PlanogramTemplateSlot;
use App\Services\AutoPlanogram\DTO\SlotPlanEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Materializa um plano de slots (SlotPlanEntry[]) em registros persistidos:
 * PlanogramTemplate + PlanogramSubtemplate + PlanogramTemplateSlots.
 *
 * Templates sintetizados nascem com origin='auto' e is_active=false.
 * Idempotente por source_gondola_id: regeração da mesma gôndola reutiliza
 * o template existente e recria os slots do subtemplate correspondente.
 */
final class AutoTemplateSynthesizer
{
    /**
     * @param  list<SlotPlanEntry>  $slotPlan  Plano produzido pelo SlotPlanBuilder.
     */
    public function synthesize(
        string $planogramBaseCategoryId,
        Category $selectedCategory,
        array $slotPlan,
        int $numModules,
        string $gondolaId,
    ): PlanogramSubtemplate {
        return DB::transaction(function () use (
            $planogramBaseCategoryId,
            $selectedCategory,
            $slotPlan,
            $numModules,
            $gondolaId,
        ): PlanogramSubtemplate {
            $this->warnIfNoAbcIntelligence($slotPlan);

            $template = $this->findOrCreateTemplate(
                planogramBaseCategoryId: $planogramBaseCategoryId,
                selectedCategory: $selectedCategory,
                gondolaId: $gondolaId,
            );

            $subtemplate = $this->findOrReplaceSubtemplate(
                template: $template,
                numModules: $numModules,
            );

            $this->createSlots($subtemplate, $slotPlan);

            Log::info('AutoTemplateSynthesizer: template sintetizado', [
                'template_id' => $template->getKey(),
                'template_code' => $template->code,
                'subtemplate_id' => $subtemplate->getKey(),
                'num_modules' => $numModules,
                'slots_criados' => count($slotPlan),
                'gondola_id' => $gondolaId,
            ]);

            return $subtemplate->refresh();
        });
    }

    /**
     * Encontra template existente (por source_gondola_id + origin='auto') ou cria um novo.
     * Restaura automaticamente se estava soft-deleted.
     */
    private function findOrCreateTemplate(
        string $planogramBaseCategoryId,
        Category $selectedCategory,
        string $gondolaId,
    ): PlanogramTemplate {
        $existing = PlanogramTemplate::withTrashed()
            ->where('source_gondola_id', $gondolaId)
            ->where('origin', 'auto')
            ->lockForUpdate()
            ->first();

        // department é NOT NULL no schema; usa o nome da categoria do escopo (já carregada).
        $department = $selectedCategory->name;

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->update([
                'name' => $selectedCategory->name.' (auto)',
                'department' => $department,
                'category_id' => $planogramBaseCategoryId,
                'is_active' => false,
            ]);

            return $existing;
        }

        $code = 'AUTO-'.strtoupper(substr((string) Str::ulid(), -8));

        return PlanogramTemplate::create([
            'code' => $code,
            'name' => $selectedCategory->name.' (auto)',
            'department' => $department,
            'category_id' => $planogramBaseCategoryId,
            'origin' => 'auto',
            'is_active' => false,
            'source_gondola_id' => $gondolaId,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Encontra subtemplate (por template_id + num_modules) ou cria um novo.
     * Se já existe, force-deleta os slots antigos antes de retornar.
     */
    private function findOrReplaceSubtemplate(
        PlanogramTemplate $template,
        int $numModules,
    ): PlanogramSubtemplate {
        $existing = PlanogramSubtemplate::withTrashed()
            ->where('template_id', $template->getKey())
            ->where('num_modules', $numModules)
            ->lockForUpdate()
            ->first();

        $code = $template->code.'-'.$numModules.'M';

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->update(['code' => $code, 'is_active' => true]);

            // Remover slots antigos para recriação limpa (idempotência)
            $existing->slots()->withTrashed()->forceDelete();

            return $existing;
        }

        return PlanogramSubtemplate::create([
            'template_id' => $template->getKey(),
            'code' => $code,
            'num_modules' => $numModules,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<SlotPlanEntry>  $slotPlan
     */
    private function createSlots(PlanogramSubtemplate $subtemplate, array $slotPlan): void
    {
        foreach ($slotPlan as $index => $entry) {
            PlanogramTemplateSlot::create([
                'subtemplate_id' => $subtemplate->getKey(),
                'category_id' => $entry->categoryId,
                'module_number' => $entry->moduleNumber,
                'shelf_order' => $entry->shelfOrder,
                'min_facings' => $entry->minFacings,
                'max_facings' => $entry->maxFacings,
                'visual_criteria' => $entry->visualCriteria ?: null,
                'role_override' => $entry->roleOverride,
                'ordering' => $index + 1,
                'priority' => 1,
                'facing_expansion' => $entry->facingExpansion ?? 'none',
                'use_target_stock' => $entry->useTargetStock,
                'space_fallback' => $entry->spaceFallback ?? 'reduce_c',
                'max_share_per_sku' => $entry->maxSharePerSku,
                'max_share_per_brand' => $entry->maxSharePerBrand,
                'max_share_per_subcategory' => $entry->maxSharePerSubcategory,
            ]);
        }
    }

    /**
     * Emite warning quando todos os slots têm o mesmo min_facings em um plano com
     * múltiplas categorias — sinal de que abcClassMap não chegou ao SlotPlanBuilder.
     *
     * Não dispara para categorias folha (plano com única categoria), pois nesse caso
     * todos os slots pertencerem ao mesmo category_id e terem o mesmo min_facings é correto.
     *
     * @param  list<SlotPlanEntry>  $slotPlan
     */
    private function warnIfNoAbcIntelligence(array $slotPlan): void
    {
        if (count($slotPlan) < 2) {
            return;
        }

        // Folha: único category_id → mesmo min_facings é o comportamento esperado
        $uniqueCategories = array_unique(array_map(
            fn (SlotPlanEntry $e) => $e->categoryId,
            $slotPlan
        ));

        if (count($uniqueCategories) === 1) {
            return;
        }

        $uniqueFacings = array_unique(array_map(
            fn (SlotPlanEntry $e) => $e->minFacings,
            $slotPlan
        ));

        if (count($uniqueFacings) === 1) {
            Log::warning(
                'AutoTemplateSynthesizer: todos os slots têm o mesmo min_facings — '.
                'verifique se abcClassMap foi injetado corretamente antes da síntese.',
                ['min_facings' => reset($uniqueFacings), 'num_slots' => count($slotPlan), 'num_categories' => count($uniqueCategories)]
            );
        }
    }
}
