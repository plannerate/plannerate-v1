<?php

namespace App\Services\AutoPlanogram\Synthesis;

use App\Models\Category;
use App\Models\PlanogramSubtemplate;
use App\Services\AutoPlanogram\DTO\CategoryAbcSummary;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
    /**
     * Número mínimo de prateleiras por módulo para novas gôndolas.
     *
     * Garante que cada módulo tenha slots suficientes para expor múltiplas categorias
     * e evita gôndolas com apenas 1-2 prateleiras por seção quando a demanda calculada
     * é pequena. Não se aplica a regenerações (a estrutura existente é preservada).
     */
    public const MIN_SHELVES_PER_MODULE = 4;

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

        $numModulesPhysical = max($input->sections->count(), 1);
        $shelfWidth = (float) ($input->sections->first()?->width ?? 100.0);

        // shelvesPerModule: padrão físico fixo — cada módulo tem sempre MIN_SHELVES_PER_MODULE prateleiras.
        // O número de módulos é ajustado pela demanda (ver abaixo); o tamanho de cada módulo é constante.
        $shelvesPerModule = self::MIN_SHELVES_PER_MODULE;

        // numModules: dirigido pela demanda real, não pela grade física.
        // Cria apenas os módulos necessários para acomodar a demanda (slot mínimo = ceil(demand/4)).
        // Cappado pela quantidade de seções físicas disponíveis.
        $numModules = $this->computeNumModules(
            $summaries,
            $scored,
            $numModulesPhysical,
            $shelvesPerModule,
            $shelfWidth,
        );

        Log::info('AutoTemplateSynthesisOrchestrator: estrutura calculada', [
            'num_modules_physical' => $numModulesPhysical,
            'num_modules_demand' => $numModules,
            'shelves_per_module' => $shelvesPerModule,
        ]);

        $slotPlan = $this->slotPlanBuilder->build(
            selectedCategory: $selectedCategory,
            subcategories: $subcats,
            numModules: $numModules,
            shelvesPerModule: $shelvesPerModule,
            settings: $input->settings,
            shelfWidth: $shelfWidth,
            useFullCapacity: true,  // usa toda a capacidade dos módulos criados (4 prateleiras/módulo)
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
     * Calcula o número de módulos a usar na síntese do template.
     *
     * Estratégia: usa SEMPRE toda a capacidade física configurada pelo usuário (numModulesPhysical).
     * O usuário criou a gôndola com N seções intencionalmente; o auto-planograma deve respeitar
     * essa escolha para maximizar a distribuição dos produtos disponíveis.
     *
     * A demanda calculada (totalDemandedSlots) é registrada apenas como referência diagnóstica —
     * ela pode subestimar a necessidade real quando:
     *   - Produtos têm largura 0 (cai no fallback de 10 cm), mas ocupam mais espaço na prática.
     *   - O mix de produtos excede o que o cálculo de largura antecipa.
     *   - O placement engine rejeita produtos por falta de espaço, indicando subdimensionamento.
     *
     * Seções fisicamente existentes que ficarem sem produto ao final do placement são tratadas
     * pela lógica de deleção em AutoPlanogramService (após, não antes, do placement).
     *
     * @param  Collection<string, CategoryAbcSummary>  $summaries  Indexado por category_id.
     * @param  Collection<int, ScoredProduct>  $scored
     */
    private function computeNumModules(
        Collection $summaries,
        Collection $scored,
        int $numModulesPhysical,
        int $shelvesPerModule,
        float $shelfWidth,
    ): int {
        // Calcula demanda apenas para log diagnóstico (não limita o módulo count)
        if ($summaries->isEmpty()) {
            $totalWidth = $scored->sum(function (ScoredProduct $sp): float {
                $w = (float) ($sp->product->width ?? 0);

                return ($w > 0 && $w <= 60) ? $w : 10.0;
            });
            $totalDemandedSlots = max(1, (int) ceil($totalWidth / max($shelfWidth, 1.0)));
        } else {
            $totalDemandedSlots = (int) $summaries->sum(fn (CategoryAbcSummary $s) => max(1, (int) ceil($s->totalWidth / max($shelfWidth, 1.0))));
            $totalDemandedSlots = max($summaries->count(), $totalDemandedSlots);
        }

        $numModulesNeeded = max(1, (int) ceil($totalDemandedSlots / max($shelvesPerModule, 1)));

        // Usa toda a capacidade física: respeita a gôndola configurada pelo usuário.
        // numModulesNeeded é registrado para diagnóstico mas NÃO limita o resultado.
        $numModules = $numModulesPhysical;

        Log::info('AutoTemplateSynthesisOrchestrator: numModules demand-based', [
            'total_demanded_slots' => $totalDemandedSlots,
            'shelves_per_module' => $shelvesPerModule,
            'num_modules_needed' => $numModulesNeeded,
            'num_modules_physical' => $numModulesPhysical,
            'num_modules_used' => $numModules,
        ]);

        return $numModules;
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
