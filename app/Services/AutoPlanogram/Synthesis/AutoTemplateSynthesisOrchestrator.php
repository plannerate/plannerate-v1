<?php

namespace App\Services\AutoPlanogram\Synthesis;

use App\Models\Category;
use App\Services\AutoPlanogram\DTO\CategoryAbcSummary;
use App\Services\AutoPlanogram\DTO\PlanogramInput;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramSubtemplate;
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

    /**
     * Taxa estimada de preenchimento efetivo de prateleira (75%).
     *
     * O placement engine não consegue usar 100% da largura da prateleira: espaçamentos
     * entre produtos, arredondamentos de frentes (facings), último produto que não cabe
     * no espaço restante e folgas de categorias levam a uma ocupação típica de 70–80%.
     *
     * Usado em computeNumModules para escalonar a largura efetiva por prateleira ao
     * estimar o número de módulos necessários — produz uma contagem conservadora que
     * evita que produtos sejam rejeitados por falta de espaço. Não afeta a distribuição
     * de slots dentro de cada módulo (o SlotPlanBuilder usa a largura real).
     */
    private const SHELF_FILL_RATE_ESTIMATE = 0.75;

    public function __construct(
        private readonly CategoryRoleInferrer $roleInferrer,
        private readonly SlotPlanBuilder $slotPlanBuilder,
        private readonly AutoTemplateSynthesizer $synthesizer,
    ) {}

    /**
     * @param  Collection<int, ScoredProduct>  $scored
     * @param  int  $shelvesPerModule  Número de prateleiras por módulo — definido pelo usuário no
     *                                 stepper e contado pelo AutoPlanogramService antes da síntese.
     *                                 Garante que os slots gerados espelhem exatamente a estrutura
     *                                 física criada: N módulos × $shelvesPerModule prateleiras.
     *
     * @throws ValidationException Se a categoria selecionada estiver fora do escopo do planograma.
     */
    public function orchestrate(
        PlanogramInput $input,
        Collection $scored,
        string $planogramBaseCategoryId,
        int $shelvesPerModule = self::MIN_SHELVES_PER_MODULE,
    ): PlanogramSubtemplate {
        $selectedCategoryId = $input->settings->categoryId ?? $planogramBaseCategoryId;

        $this->validateCategoryScope($selectedCategoryId, $planogramBaseCategoryId);

        $selectedCategory = Category::find($selectedCategoryId)
            ?? throw new \RuntimeException("Categoria selecionada não encontrada: {$selectedCategoryId}");

        // Filhos diretos servem como ponto de partida; depois expandimos até o nível
        // onde os produtos realmente vivem para que nós intermediários (ex.: "Flocão")
        // não agrupem subcategorias distintas ("De milho" e "De arroz") em uma só prateleira.
        $directChildren = Category::where('category_id', $selectedCategoryId)->get();
        $slotCategories = $this->expandToProductLevel($directChildren, $scored);

        $grouped = $this->groupBySubcategory($slotCategories, $scored);

        $summaries = $grouped->map(fn (Collection $prods, string $catId) => CategoryAbcSummary::fromScoredProducts($catId, $prods, $input->settings->abcClassMap)
        );

        $totalQ = max($summaries->sum('totalQuantity'), 1e-9);
        $totalM = max($summaries->sum('totalMargem'), 1e-9);

        $subcats = $summaries->map(function (CategoryAbcSummary $s) use ($slotCategories, $totalQ, $totalM) {
            $cat = $slotCategories->firstWhere('id', $s->categoryId);
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

        // numModules: fixo nas seções físicas do formulário (exatamente o que o usuário configurou).
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
            abcClassMap: $input->settings->abcClassMap,
            hotZonePriority: $input->settings->hotZonePriority,
            coldZonePriority: $input->settings->coldZonePriority,
            flowDirection: $input->settings->flowDirection,
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
     * Calcula o número de módulos necessários pela demanda real das subcategorias.
     *
     * Estratégia:
     * - Soma a demanda individual por subcategoria usando a largura efetiva da prateleira
     *   (shelfWidth × SHELF_FILL_RATE_ESTIMATE), que reflete a ocupação real do placement
     *   engine (~75%). Isso produz uma estimativa conservadora que evita rejeições de produtos.
     * - totalDemandedSlots = max(numSubcats, soma individual) — garante 1 slot por subcat.
     * - numModules = ceil(totalDemandedSlots / shelvesPerModule), cappado pelo físico.
     * - Seções excedentes (além de numModules) são deletadas pelo AutoPlanogramService.
     *
     * Por que usar largura efetiva (não a real):
     *   O cálculo de largura bruta (totalWidth) subestima a necessidade real porque ignora
     *   espaçamentos entre produtos, arredondamentos de facings, e itens que não cabem nos
     *   últimos centímetros de uma prateleira. A taxa de preenchimento observada é ~75%, logo
     *   usar shelfWidth × 0.75 como denominador produz ~33% mais slots que a conta bruta —
     *   suficiente para acomodar todos os produtos sem criar módulos desnecessários.
     *
     * Categoria folha (summaries vazio): usa os scored products diretamente para calcular a largura.
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
        // Largura efetiva por prateleira: taxa de preenchimento real do placement engine (~75%).
        // Produz estimativa conservadora de módulos — evita rejeições por falta de espaço.
        $effectiveShelfWidth = max($shelfWidth, 1.0) * self::SHELF_FILL_RATE_ESTIMATE;

        // Calcular total de slots demandados (soma das demandas individuais por subcat)
        if ($summaries->isEmpty()) {
            // Categoria folha: 1 categoria → largura × frentes médias (fator padrão '' = 2).
            $totalWidth = $scored->sum(function (ScoredProduct $sp): float {
                $w = (float) ($sp->product->width ?? 0);

                return ($w > 0 && $w <= 60) ? $w : 10.0;
            });
            $totalDemandedSlots = max(1, (int) ceil(($totalWidth * SlotPlanBuilder::ABC_MIN_FACINGS['']) / $effectiveShelfWidth));
        } else {
            // Soma per-subcat ponderada por frentes: largura × min_facings da classe dominante.
            // Reflete o espaço real que o placement consome (mesma fonte: SlotPlanBuilder::ABC_MIN_FACINGS).
            $totalDemandedSlots = (int) $summaries->sum(function (CategoryAbcSummary $s) use ($effectiveShelfWidth): int {
                $facings = SlotPlanBuilder::ABC_MIN_FACINGS[$s->dominantAbcClass ?? ''] ?? SlotPlanBuilder::ABC_MIN_FACINGS[''];

                return max(1, (int) ceil(($s->totalWidth * $facings) / $effectiveShelfWidth));
            });
            // Garante mínimo de 1 slot por subcategoria mesmo sem dados de largura
            $totalDemandedSlots = max($summaries->count(), $totalDemandedSlots);
        }

        // Modo automático usa EXATAMENTE os módulos do formulário (= seções físicas): não cresce
        // nem encolhe. O número de slots demandados (já ponderado por frentes) serve apenas como
        // diagnóstico — se exceder o físico, o relatório de capacidade reporta o excesso após o placement.
        $numModulesNeeded = max(1, (int) ceil($totalDemandedSlots / max($shelvesPerModule, 1)));
        $numModules = $numModulesPhysical;

        Log::info('AutoTemplateSynthesisOrchestrator: numModules', [
            'total_demanded_slots' => $totalDemandedSlots,
            'shelves_per_module' => $shelvesPerModule,
            'num_modules_needed' => $numModulesNeeded,
            'num_modules_physical' => $numModulesPhysical,
            'num_modules_used' => $numModules,
            'demanda_excede_fisico' => $numModulesNeeded > $numModulesPhysical,
        ]);

        // Quando a demanda excede o físico, loga quais categorias estão sendo comprimidas
        // para facilitar diagnóstico de produtos rejeitados e slots mal distribuídos.
        if ($numModulesNeeded > $numModulesPhysical && $summaries->isNotEmpty()) {
            $slotsDisponiveis = $numModulesPhysical * $shelvesPerModule;
            $compressao = $summaries->map(function (CategoryAbcSummary $s) use ($effectiveShelfWidth): array {
                $facings = SlotPlanBuilder::ABC_MIN_FACINGS[$s->dominantAbcClass ?? ''] ?? SlotPlanBuilder::ABC_MIN_FACINGS[''];

                return [
                    'category_id' => $s->categoryId,
                    'total_width_cm' => round($s->totalWidth, 1),
                    'slots_demandados' => max(1, (int) ceil(($s->totalWidth * $facings) / $effectiveShelfWidth)),
                ];
            })->sortByDesc('slots_demandados')->take(10)->values()->toArray();

            Log::warning('AutoTemplateSynthesisOrchestrator: compressão ativa — demanda excede capacidade física', [
                'slots_demandados' => $totalDemandedSlots,
                'slots_fisicos' => $slotsDisponiveis,
                'excesso_slots' => $totalDemandedSlots - $slotsDisponiveis,
                'top_categorias_por_demanda' => $compressao,
            ]);
        }

        return $numModules;
    }

    /**
     * Expande os filhos da categoria selecionada descendo a árvore até o nível onde os
     * produtos estão de fato atribuídos, garantindo um slot por subcategoria real.
     *
     * Comportamento por tipo de nó:
     * - Folha (sem filhos) → mantida como slot.
     * - Nó com produtos diretos (products.category_id = cat.id) → mantido como slot (não expande).
     * - Nó intermediário sem produtos diretos mas com produtos em descendentes →
     *   substituído pelos seus filhos diretos, que passam pela mesma avaliação recursivamente.
     * - Nó sem produtos em nenhum descendente → mantido como slot vazio (será descartado
     *   pelo SlotPlanBuilder por falta de demanda).
     *
     * Exemplo: Cereais → [Flocão → [De milho, De arroz], Farofa]
     * Se os produtos estão em "De milho" e "De arroz":
     *   resultado = [De milho, De arroz, Farofa] — cada filho de "Flocão" recebe prateleira própria.
     *
     * @param  Collection<int, Category>  $children  Filhos diretos da categoria selecionada.
     * @param  Collection<int, ScoredProduct>  $scored  Produtos pontuados (fonte de category_id real).
     * @return Collection<int, Category>
     */
    private function expandToProductLevel(Collection $children, Collection $scored): Collection
    {
        if ($children->isEmpty()) {
            return $children;
        }

        // Set dos category_ids que têm produtos diretamente atribuídos nos scored products.
        $productCatIds = $scored
            ->map(fn (ScoredProduct $sp) => $sp->product->category_id)
            ->filter()
            ->unique()
            ->flip()
            ->all();

        $result = collect();
        $queue = $children->all();

        while ($queue !== []) {
            $cat = array_shift($queue);

            // Categoria folha: sem filhos → inclui como slot diretamente.
            $catChildren = Category::where('category_id', $cat->id)->get();

            if ($catChildren->isEmpty()) {
                $result->push($cat);

                continue;
            }

            // Tem produtos atribuídos diretamente a este nó (ex.: Farofa com products na Farofa)
            // → usar como slot, não expandir para os filhos.
            if (isset($productCatIds[$cat->id])) {
                $result->push($cat);

                continue;
            }

            // Sem produtos diretos, mas tem filhos — verificar se algum descendente tem produto.
            $descIds = Category::getDescendantIds($cat->id);
            $hasProductsInDescendants = collect($descIds)->some(
                fn (string $id) => isset($productCatIds[$id])
            );

            if ($hasProductsInDescendants) {
                // Expande: substitui este nó pelos filhos diretos para nova avaliação recursiva.
                // Ex.: "Flocão" → queue recebe ["De milho", "De arroz"].
                foreach ($catChildren as $child) {
                    $queue[] = $child;
                }

                Log::debug('AutoTemplateSynthesisOrchestrator: categoria intermediária expandida para filhos', [
                    'category_id' => $cat->id,
                    'category_name' => $cat->name,
                    'filhos_adicionados' => $catChildren->count(),
                ]);
            } else {
                // Nenhum produto em descendentes → mantém como slot vazio; será descartado
                // pelo SlotPlanBuilder por skuCount=0 e totalQuantity=0.
                $result->push($cat);
            }
        }

        return $result;
    }

    /**
     * Agrupa ScoredProducts pela subcategoria de slot (após expansão pelo expandToProductLevel).
     *
     * Para cada categoria de slot, usa getDescendantIds para mapear todas as folhas ao
     * slot correto. Produtos sem correspondência são silenciosamente descartados.
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
