<?php

namespace App\Services\AutoPlanogram\Synthesis;

use App\Models\Category;
use App\Services\AutoPlanogram\DTO\SlotPlanEntry;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplate;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
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
     * @param  array<string, string>  $abcClassMap  Mapa product_id → classe ABC usado na síntese.
     *                                              Vazio sinaliza que a inteligência ABC não chegou.
     */
    public function synthesize(
        string $planogramBaseCategoryId,
        Category $selectedCategory,
        array $slotPlan,
        int $numModules,
        string $gondolaId,
        array $abcClassMap = [],
        ?string $hotZonePriority = null,
        ?string $coldZonePriority = null,
        ?string $flowDirection = null,
    ): PlanogramSubtemplate {
        return DB::transaction(function () use (
            $planogramBaseCategoryId,
            $selectedCategory,
            $slotPlan,
            $numModules,
            $gondolaId,
            $abcClassMap,
            $hotZonePriority,
            $coldZonePriority,
            $flowDirection,
        ): PlanogramSubtemplate {
            $this->warnIfNoAbcIntelligence($slotPlan, $abcClassMap);

            $template = $this->findOrCreateTemplate(
                planogramBaseCategoryId: $planogramBaseCategoryId,
                selectedCategory: $selectedCategory,
                gondolaId: $gondolaId,
            );

            $subtemplate = $this->findOrReplaceSubtemplate(
                template: $template,
                numModules: $numModules,
                hotZonePriority: $hotZonePriority,
                coldZonePriority: $coldZonePriority,
                flowDirection: $flowDirection,
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
        ?string $hotZonePriority = null,
        ?string $coldZonePriority = null,
        ?string $flowDirection = null,
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

            $existing->update([
                'code' => $code,
                'is_active' => true,
                'hot_zone_priority' => $hotZonePriority,
                'cold_zone_priority' => $coldZonePriority,
                'flow_direction' => $flowDirection,
            ]);

            // Remover slots antigos para recriação limpa (idempotência)
            $existing->slots()->withTrashed()->forceDelete();

            return $existing;
        }

        return PlanogramSubtemplate::create([
            'template_id' => $template->getKey(),
            'code' => $code,
            'num_modules' => $numModules,
            'is_active' => true,
            'hot_zone_priority' => $hotZonePriority,
            'cold_zone_priority' => $coldZonePriority,
            'flow_direction' => $flowDirection,
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
     * Emite warning apenas quando o abcClassMap está vazio em um plano com múltiplas
     * categorias — o sinal real de que a inteligência ABC não chegou à síntese.
     *
     * Não infere a partir de min_facings uniforme: várias categorias podem legitimamente
     * compartilhar a mesma classe ABC dominante (ou empates resolverem para a mesma classe),
     * o que gerava falsos positivos. Categoria folha (único category_id) também não dispara.
     *
     * @param  list<SlotPlanEntry>  $slotPlan
     * @param  array<string, string>  $abcClassMap
     */
    private function warnIfNoAbcIntelligence(array $slotPlan, array $abcClassMap): void
    {
        if ($abcClassMap !== []) {
            return;
        }

        if (count($slotPlan) < 2) {
            return;
        }

        // Folha: único category_id → ausência de ABC é irrelevante (um bloco só)
        $uniqueCategories = array_unique(array_map(
            fn (SlotPlanEntry $e) => $e->categoryId,
            $slotPlan
        ));

        if (count($uniqueCategories) === 1) {
            return;
        }

        Log::warning(
            'AutoTemplateSynthesizer: abcClassMap vazio com múltiplas categorias — '.
            'a síntese não recebeu a classificação ABC; min_facings e ordenação ficam sem inteligência.',
            ['num_slots' => count($slotPlan), 'num_categories' => count($uniqueCategories)]
        );
    }
}
