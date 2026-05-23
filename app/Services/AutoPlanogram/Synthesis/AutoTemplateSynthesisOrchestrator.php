<?php

namespace App\Services\AutoPlanogram\Synthesis;

use App\Models\Category;
use App\Models\PlanogramSubtemplate;
use App\Services\AutoPlanogram\DTO\CategoryAbcSummary;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Orquestra a síntese de um template automático a partir dos produtos pontuados.
 *
 * Pipeline:
 * 1. Valida que a categoria selecionada é a base do planograma ou descendente dela.
 * 2. Carrega as subcategorias diretas da categoria selecionada.
 * 3. Agrupa os produtos pontuados por subcategoria.
 * 4. Constrói CategoryAbcSummary com o abcClassMap injetado (não usa metadata tardio).
 * 5. Normaliza participações (0–1) para o CategoryRoleInferrer.
 * 6. Infere o papel (CategoryRole) de cada subcategoria.
 * 7. Constrói o plano de slots via SlotPlanBuilder.
 * 8. Persiste o template via AutoTemplateSynthesizer.
 */
final class AutoTemplateSynthesisOrchestrator
{
    public function __construct(
        private readonly CategoryRoleInferrer $roleInferrer,
        private readonly SlotPlanBuilder $slotPlanBuilder,
        private readonly AutoTemplateSynthesizer $synthesizer,
    ) {}

    /**
     * @param  Collection<int, ScoredProduct>  $scored
     *
     * @throws ValidationException Se a categoria selecionada estiver fora do escopo do planograma.
     */
    public function orchestrate(
        PlanogramInput $input,
        Collection $scored,
        string $planogramBaseCategoryId,
    ): PlanogramSubtemplate {
        $selectedCategoryId = $input->settings->categoryId ?? $planogramBaseCategoryId;

        $this->validateCategoryScope($selectedCategoryId, $planogramBaseCategoryId);

        $selectedCategory = Category::find($selectedCategoryId)
            ?? throw new \RuntimeException("Categoria selecionada não encontrada: {$selectedCategoryId}");

        $children = Category::where('category_id', $selectedCategoryId)->get();

        $grouped = $this->groupBySubcategory($children, $scored);

        $summaries = $grouped->map(fn (Collection $prods, string $catId) => CategoryAbcSummary::fromScoredProducts($catId, $prods, $input->settings->abcClassMap)
        );

        $totalQ = max($summaries->sum('totalQuantity'), 1e-9);
        $totalM = max($summaries->sum('totalMargem'), 1e-9);

        $subcats = $summaries->map(function (CategoryAbcSummary $s) use ($children, $totalQ, $totalM) {
            $cat = $children->firstWhere('id', $s->categoryId);
            $norm = $s->withParticipation(
                $s->totalQuantity / $totalQ,
                $s->totalMargem / $totalM,
            );

            return [
                'category' => $cat,
                'summary' => $s,
                'role' => $this->roleInferrer->infer($cat, $norm),
            ];
        })->values();

        $numModules = max($input->sections->count(), 1);
        $shelvesPerModule = $input->sections->map(fn ($s) => $s->shelves->count())->max() ?: 4;

        $slotPlan = $this->slotPlanBuilder->build(
            selectedCategory: $selectedCategory,
            subcategories: $subcats,
            numModules: $numModules,
            shelvesPerModule: $shelvesPerModule,
            settings: $input->settings,
        );

        return $this->synthesizer->synthesize(
            planogramBaseCategoryId: $planogramBaseCategoryId,
            selectedCategory: $selectedCategory,
            slotPlan: $slotPlan,
            numModules: $numModules,
            gondolaId: $input->gondolaId,
        );
    }

    /**
     * Lança ValidationException se a categoria selecionada não for a base nem descendente dela.
     */
    private function validateCategoryScope(string $selectedCategoryId, string $planogramBaseCategoryId): void
    {
        if ($selectedCategoryId === $planogramBaseCategoryId) {
            return;
        }

        $validIds = Category::getDescendantIds($planogramBaseCategoryId);

        if (! in_array($selectedCategoryId, $validIds, true)) {
            throw ValidationException::withMessages([
                'category_id' => [__('A categoria selecionada está fora do escopo do planograma.')],
            ]);
        }
    }

    /**
     * Agrupa ScoredProducts pela subcategoria direta (filha da categoria selecionada).
     *
     * Para cada filho, usa getDescendantIds para mapear todas as folhas ao filho correto.
     * Produtos sem correspondência são silenciosamente descartados.
     *
     * @param  Collection<int, Category>  $children
     * @param  Collection<int, ScoredProduct>  $scored
     * @return Collection<string, Collection<int, ScoredProduct>>
     */
    private function groupBySubcategory(Collection $children, Collection $scored): Collection
    {
        if ($children->isEmpty()) {
            return collect();
        }

        // Mapeia cada category_id (folha ou intermediário) → id do filho direto dono do bloco
        $productCatToChild = [];
        foreach ($children as $child) {
            foreach (Category::getDescendantIds($child->id) as $descId) {
                $productCatToChild[$descId] = $child->id;
            }
        }

        $grouped = collect();

        foreach ($scored as $sp) {
            $catId = $sp->product->category_id ?? null;
            if ($catId === null) {
                continue;
            }

            $childId = $productCatToChild[$catId] ?? null;
            if ($childId === null) {
                continue;
            }

            if (! $grouped->has($childId)) {
                $grouped->put($childId, collect());
            }

            $grouped[$childId]->push($sp);
        }

        return $grouped;
    }
}
