<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Synthesis;

use App\Models\Category;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\CategoryAbcSummary;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementSettings;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\SlotPlanEntry;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ShelfZoneResolver;
use Callcocam\LaravelRaptorPlannerate\Enums\CategoryRole;
use Callcocam\LaravelRaptorPlannerate\Enums\LayoutOrientation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Produz o plano de slots do template sintetizado: para cada (módulo, prateleira),
 * qual subcategoria ocupa, com min_facings e visual_criteria.
 *
 * Algoritmo (block-partition):
 * 1. Ordenar subcategorias: hot-preferring primeiro (destino/impulso), por ABC e giro.
 * 2. Construir slots ordenados por zona dentro de cada módulo (hot first) → blocagem vertical.
 * 3. Particionar slots em blocos proporcionais à demanda de cada subcategoria (Approach B):
 *    - Demanda principal: largura total dos produtos (totalWidth) → ceil(totalWidth / shelfWidth).
 *    - Fallback quando sem dados de largura: proporcional ao volume (totalQuantity).
 *    - Usa TODA a capacidade disponível; slots excedentes (teto de módulo) fluem para as
 *      subcategorias com OVERFLOW (totalWidth % shelfWidth > 0): aquelas cuja última
 *      prateleira demandada não está completamente cheia e podem absorver espaço extra.
 *      Categorias sem overflow (ex.: DE TRIGO a 600 cm exatos) NÃO recebem extras, pois
 *      qualquer slot além da demanda exata ficaria garantidamente vazio.
 *    - Fallback quando não há overflow em nenhuma categoria: round-robin nas de maior demanda.
 *    - O controle de não super-provisionar opera no nível de seções (AutoPlanogramService).
 *    - Subcategoria sem produto elegível (skuCount=0 e totalQuantity=0) não gera slot.
 *
 * ABC é SEMPRE o primeiro critério visual (score_abc desc) — não é configurável.
 */
class SlotPlanBuilder
{
    /**
     * Número mínimo de frentes por classe ABC.
     *
     * Estes são valores absolutos de piso de exibição — NÃO escalados até maxFacings.
     * O maxFacings é teto de expansão (Phase 2 de placement) e não deve ser confundido
     * com o piso mínimo de frentes necessário para um produto aparecer na gôndola.
     *
     * Todos as classes começam com 1 frente mínima. A expansão de frentes (Phase 2 de
     * placement) prioriza classe A antes de B, e B antes de C — via visual_criteria
     * score_abc desc — garantindo que produtos classe A recebam frentes extras primeiro.
     *
     * Público para que o cálculo de demanda de espaço (largura × frentes) use a mesma
     * fonte de verdade aqui e no AutoTemplateSynthesisOrchestrator.
     */
    public const ABC_MIN_FACINGS = ['A' => 1, 'B' => 1, 'C' => 1, '' => 1];

    /**
     * Limiar de micro-categoria: subcategoria cujo totalWidth é inferior a esta fração da largura
     * da prateleira é considerada "micro" e compartilha a prateleira da categoria precedente,
     * adensando o uso do espaço livre em vez de monopolizar uma prateleira inteira.
     *
     * Exemplo (shelfWidth=96cm): threshold = 96 × 0.35 = 33.6 cm.
     * Uma categoria com 10 produtos × 2 cm cada (totalWidth=20 cm) é micro → compartilhada.
     *
     * O compartilhamento só ocorre quando:
     *  - slotCount == 1 (a categoria não recebeu slots extras via overflow-routing)
     *  - totalWidth > 0 (há dados de largura — sem fallback por quantity)
     *  - há uma categoria precedente com slot disponível para compartilhar
     * Apenas UMA micro-categoria compartilha por slot predecessor (evita superlotação).
     */
    public const MICRO_CATEGORY_WIDTH_THRESHOLD = 0.35;

    /**
     * Papéis que preferem zona quente (eye/hand).
     */
    private const HOT_ROLES = [
        CategoryRole::Destino,
        CategoryRole::Impulso,
        CategoryRole::Sazonal,
    ];

    /**
     * Constrói o plano de slots para a gôndola.
     *
     * @param  Collection<int, array{category: Category, summary: CategoryAbcSummary, role: CategoryRole}>  $subcategories
     *                                                                                                                      Filhas elegíveis da categoria selecionada, já com papel e resumo ABC calculados.
     *                                                                                                                      Coleção vazia = categoria selecionada é folha → um único bloco cobre tudo.
     * @param  float  $shelfWidth  Largura útil da prateleira (cm). Usada para calcular demanda real.
     * @return list<SlotPlanEntry>
     */
    public function build(
        Category $selectedCategory,
        Collection $subcategories,
        int $numModules,
        int $shelvesPerModule,
        PlacementSettings $settings,
        float $shelfWidth = 100.0,
        bool $useFullCapacity = false,
    ): array {
        $verticalLayout = $settings->layoutOrientation === LayoutOrientation::Vertical->value;
        $orderedSlots = $this->buildOrderedSlots($numModules, $shelvesPerModule, $verticalLayout);

        if ($subcategories->isEmpty()) {
            return $this->buildLeafPlan($selectedCategory, $orderedSlots, $settings);
        }

        $sorted = $this->sortSubcategories($subcategories);

        return $this->partitionIntoBlocks($sorted, $orderedSlots, $settings, $shelfWidth);
    }

    /**
     * Caso folha: preenche todos os slots com a própria categoria selecionada.
     *
     * Aplica deriveMinFacings com a classe ABC dominante extraída do abcClassMap,
     * garantindo que o WARNING de "todos os slots têm mesmo min_facings" não dispare
     * quando há diversidade ABC nos produtos da categoria folha.
     *
     * @param  list<array{module: int, shelf_order: int, zone: 'hot'|'cold'|'neutral'}>  $orderedSlots
     * @return list<SlotPlanEntry>
     */
    private function buildLeafPlan(
        Category $selectedCategory,
        array $orderedSlots,
        PlacementSettings $settings,
    ): array {
        // Deriva a classe ABC dominante dos produtos conhecidos via abcClassMap
        $dominantAbc = $this->deriveDominantAbcFromMap($settings->abcClassMap);
        $minFacings = $this->deriveMinFacings($dominantAbc, $settings->minFacings, $settings->maxFacings);

        return array_map(fn (array $slot): SlotPlanEntry => new SlotPlanEntry(
            categoryId: $selectedCategory->id,
            moduleNumber: $slot['module'],
            shelfOrder: $slot['shelf_order'],
            minFacings: $minFacings,
            visualCriteria: $this->buildVisualCriteria($settings->secondaryCriteria),
            zone: $slot['zone'],
            roleOverride: $selectedCategory->role,
            maxFacings: $settings->maxFacings > 0 ? $settings->maxFacings : null,
            facingExpansion: $settings->facingExpansion,
            useTargetStock: $settings->useTargetStock,
            spaceFallback: $settings->spaceFallback,
            maxSharePerSku: $settings->maxSharePerSku,
            maxSharePerBrand: $settings->maxSharePerBrand,
            maxSharePerSubcategory: $settings->maxSharePerSubcategory,
        ), $orderedSlots);
    }

    /**
     * Ordena subcategorias: hot-preferring primeiro, depois cold; por ABC e giro dentro de cada grupo.
     *
     * @param  Collection<int, array{category: Category, summary: CategoryAbcSummary, role: CategoryRole}>  $subcategories
     * @return Collection<int, array{category: Category, summary: CategoryAbcSummary, role: CategoryRole}>
     */
    private function sortSubcategories(Collection $subcategories): Collection
    {
        return $subcategories->sortBy([
            fn ($item) => $this->prefersHot($item['role']) ? 0 : 1,
            fn ($item) => match ($item['summary']->dominantAbcClass) {
                'A' => 0,
                'C' => 2,
                default => 1,
            },
            fn ($item) => -$item['summary']->totalQuantity,
        ])->values();
    }

    /**
     * Gera todos os pares (módulo, prateleira) em ordem de prioridade:
     * dentro de cada módulo, hot shelves primeiro; módulos em sequência (1, 2, …, N).
     *
     * Em layout vertical a ordem dentro do módulo é FÍSICA (shelf_order 1..N):
     * uma categoria que consome k slots recebe k prateleiras consecutivas — pré-requisito
     * para a blocagem por marca atravessar o bloco. A prioridade por zona dispersaria
     * os slots (ex.: prateleiras 3 e 1) e quebraria a elegibilidade do grupo vertical.
     *
     * @return list<array{module: int, shelf_order: int, zone: 'hot'|'cold'|'neutral'}>
     */
    private function buildOrderedSlots(int $numModules, int $shelvesPerModule, bool $verticalLayout = false): array
    {
        $slots = [];

        for ($mod = 1; $mod <= $numModules; $mod++) {
            $moduleSlots = [];

            for ($shelfOrder = 1; $shelfOrder <= $shelvesPerModule; $shelfOrder++) {
                $position = $shelvesPerModule - $shelfOrder; // 0=topo, N-1=chão
                $zone = ShelfZoneResolver::resolve($position, $shelvesPerModule);
                // hot=0 vem antes de cold=1 dentro do módulo
                $zonePriority = $zone === 'hot' ? 0 : 1;

                $moduleSlots[] = ['module' => $mod, 'shelf_order' => $shelfOrder, 'zone' => $zone, 'zone_priority' => $zonePriority];
            }

            if (! $verticalLayout) {
                usort($moduleSlots, fn ($a, $b) => $a['zone_priority'] <=> $b['zone_priority']);
            }

            $slots = array_merge($slots, $moduleSlots);
        }

        return $slots;
    }

    /**
     * Particiona slots em blocos proporcionais à demanda de cada subcategoria.
     *
     * Estratégia de demanda (hasSomeWidth = true) — Approach B com overflow-routing:
     * - Computa demanda individual: ceil(totalWidth / shelfWidth), mínimo 1 slot por subcat.
     * - Usa TODA a capacidade disponível (não super-provisionamento é responsabilidade do
     *   AutoPlanogramService que deleta seções excedentes antes desta etapa).
     * - Capacidade suficiente (capacity ≥ demand): cada subcat recebe sua demanda exata;
     *   slots excedentes fluem (Hare) para as subcategorias com overflow > 0
     *   (totalWidth % shelfWidth > 0), i.e., aquelas cuja última prateleira demandada não
     *   está completamente cheia — o placement engine pode distribuir produtos nelas.
     *   Categorias sem overflow (ex.: DE TRIGO a 600 cm exatos) NÃO recebem extras, pois
     *   qualquer slot além da demanda exata ficaria garantidamente vazio.
     *   Fallback quando overflow = 0 em todas: round-robin pelas de maior demanda.
     * - Capacidade insuficiente (capacity < demand): Hare puro escala demandas para baixo.
     *
     * Fallback quando sem dados de largura: usa totalQuantity como peso proporcional (Hare com
     * garantia de 1 slot por subcategoria). Toda a capacidade disponível é usada.
     *
     * Subcategorias sem produto elegível (skuCount = 0 e totalQuantity = 0) são excluídas.
     *
     * @param  Collection<int, array{category: Category, summary: CategoryAbcSummary, role: CategoryRole}>  $sorted
     * @param  list<array{module: int, shelf_order: int, zone: string, zone_priority: int}>  $orderedSlots
     * @return list<SlotPlanEntry>
     */
    private function partitionIntoBlocks(
        Collection $sorted,
        array $orderedSlots,
        PlacementSettings $settings,
        float $shelfWidth = 100.0,
    ): array {
        $totalCapacity = count($orderedSlots);

        // 1. Filtrar subcategorias com demanda real (algum produto elegível)
        $withDemand = $sorted->filter(
            fn ($item) => $item['summary']->skuCount > 0 || $item['summary']->totalQuantity > 0
        );

        if ($withDemand->isEmpty()) {
            // Fallback: sem dados de demanda alguma, distribuir igualmente entre todas
            $withDemand = $sorted;
        }

        $numWithDemand = $withDemand->count();

        // 2. Calcular demanda por subcategoria
        $hasSomeWidth = $withDemand->some(fn ($item) => $item['summary']->totalWidth > 0);

        if ($hasSomeWidth) {
            // Demanda individual: ceil(totalWidth / shelfWidth), mínimo 1.
            // Subcategoria com 267 cm → 3 slots; com 44 cm → 1 slot; com 5 cm → 1 slot.
            $subcatSlotsNeeded = $this->computePerSubcatSlots($withDemand, $shelfWidth);
        } else {
            // Sem dados de largura: fallback por quantidade (Hare com garantia de 1)
            $subcatSlotsNeeded = null;
        }

        // 3. Usar sempre toda a capacidade disponível (Approach B).
        //    O controle de não super-provisionar opera no nível de seções (AutoPlanogramService:
        //    seções sem slots no template são deletadas antes desta etapa).
        $totalUsed = $totalCapacity;
        $activeSlots = $orderedSlots;

        // 4. Distribuir slots por subcategoria (Approach B + overflow-routing).
        if ($hasSomeWidth && $subcatSlotsNeeded !== null) {
            // Overflow = fração ocupada da última prateleira demandada por subcategoria.
            // overflow[i] = totalWidth[i] % shelfWidth; 0 → última prateleira 100% cheia
            // (um extra seria garantidamente vazio); >0 → última prateleira parcialmente cheia
            // (o placement engine pode acomodar produtos espalhados num slot extra).
            $overflowWeights = $withDemand->values()->map(
                fn ($item) => fmod($item['summary']->totalWidth, max($shelfWidth, 1.0))
            )->all();

            // Overflow = fração ocupada da última prateleira: subcategorias com fração > 0
            // podem absorver slots extras (o placement engine distribui os produtos extras
            // no espaço sobrante). Subcategorias com totalWidth múltiplo exato de shelfWidth
            // têm overflow=0 → a última prateleira já está 100% cheia, slot extra seria vazio.

            // Capacidade ≥ demanda: base garantida + extras para subcategorias com overflow.
            // Capacidade < demanda: Hare puro escala proporcionalmente para baixo.
            $slotCounts = $this->distributeWithExtraToOverflow($subcatSlotsNeeded, $overflowWeights, $totalUsed);
        } else {
            // Fallback por quantidade: Hare com garantia de 1 slot por subcategoria
            $demandWeights = $withDemand->values()->map(
                fn ($item) => max(1.0, $item['summary']->totalQuantity)
            )->all();
            $slotCounts = $this->distributeProportionally($demandWeights, $totalUsed);
        }

        // 5. Log de subcategorias excluídas por falta de demanda
        $excluded = $sorted->count() - $numWithDemand;
        if ($excluded > 0) {
            Log::info('SlotPlanBuilder: subcategorias excluídas por falta de produto elegível', [
                'excluídas' => $excluded,
                'com_demanda' => $numWithDemand,
            ]);
        }

        // 6. Montar entradas do plano
        $entries = [];
        $slotIndex = 0;
        /**
         * Último slot físico consumido — usado para compartilhamento de micro-categorias.
         * null = nenhum slot disponível para compartilhar ainda.
         * Resetado para null depois de cada compartilhamento, para que no máximo UMA
         * micro-categoria por prateleira compartilhe (evita superlotação).
         *
         * @var array{module: int, shelf_order: int, zone: string, zone_priority: int}|null
         */
        $lastConsumedSlot = null;

        foreach ($withDemand->values() as $i => $item) {
            $count = $slotCounts[$i] ?? 1;

            $minFacings = $this->deriveMinFacings(
                $item['summary']->dominantAbcClass,
                $settings->minFacings,
                $settings->maxFacings,
            );

            // Micro-categoria: compartilha a prateleira da categoria precedente para adensar
            // o espaço livre em vez de monopolizar uma prateleira inteira.
            // Condição: exatamente 1 slot demandado (sem extras via overflow), largura real
            // conhecida e menor que o limiar, e há uma prateleira precedente disponível.
            $isMicro = $hasSomeWidth
                && $count === 1
                && $item['summary']->totalWidth > 0
                && $item['summary']->totalWidth < max($shelfWidth, 1.0) * self::MICRO_CATEGORY_WIDTH_THRESHOLD
                && $lastConsumedSlot !== null;

            if ($isMicro) {
                // Compartilha o slot anterior: mesmas coordenadas físicas, categoria diferente.
                // O engine posiciona os produtos desta categoria a partir do espaço livre
                // deixado pela categoria anterior (via $occupiedPerShelf no TemplatePlacementEngine).
                $entries[] = new SlotPlanEntry(
                    categoryId: $item['category']->id,
                    moduleNumber: $lastConsumedSlot['module'],
                    shelfOrder: $lastConsumedSlot['shelf_order'],
                    minFacings: $minFacings,
                    visualCriteria: $this->buildVisualCriteria($settings->secondaryCriteria),
                    zone: $lastConsumedSlot['zone'],
                    roleOverride: $item['role'],
                    maxFacings: $settings->maxFacings > 0 ? $settings->maxFacings : null,
                    facingExpansion: $settings->facingExpansion,
                    useTargetStock: $settings->useTargetStock,
                    spaceFallback: $settings->spaceFallback,
                    maxSharePerSku: $this->deriveMaxSharePerSku($item['role'], $settings->maxSharePerSku),
                    maxSharePerBrand: $settings->maxSharePerBrand,
                    maxSharePerSubcategory: $settings->maxSharePerSubcategory,
                );

                Log::debug('SlotPlanBuilder: micro-categoria adensada em prateleira compartilhada', [
                    'category_id' => $item['category']->id,
                    'total_width_cm' => $item['summary']->totalWidth,
                    'threshold_cm' => round(max($shelfWidth, 1.0) * self::MICRO_CATEGORY_WIDTH_THRESHOLD, 1),
                    'shared_module' => $lastConsumedSlot['module'],
                    'shared_shelf' => $lastConsumedSlot['shelf_order'],
                ]);

                // Após compartilhar, reseta o slot disponível: apenas 1 micro-categoria por prateleira.
                $lastConsumedSlot = null;

                continue;
            }

            for ($s = 0; $s < $count; $s++) {
                if ($slotIndex >= count($activeSlots)) {
                    break;
                }

                $slot = $activeSlots[$slotIndex++];
                $lastConsumedSlot = $slot; // atualiza para possível compartilhamento posterior

                $entries[] = new SlotPlanEntry(
                    categoryId: $item['category']->id,
                    moduleNumber: $slot['module'],
                    shelfOrder: $slot['shelf_order'],
                    minFacings: $minFacings,
                    visualCriteria: $this->buildVisualCriteria($settings->secondaryCriteria),
                    zone: $slot['zone'],
                    roleOverride: $item['role'],
                    maxFacings: $settings->maxFacings > 0 ? $settings->maxFacings : null,
                    facingExpansion: $settings->facingExpansion,
                    useTargetStock: $settings->useTargetStock,
                    spaceFallback: $settings->spaceFallback,
                    maxSharePerSku: $this->deriveMaxSharePerSku($item['role'], $settings->maxSharePerSku),
                    maxSharePerBrand: $settings->maxSharePerBrand,
                    maxSharePerSubcategory: $settings->maxSharePerSubcategory,
                );
            }
        }

        return $entries;
    }

    /**
     * Distribui $total slots entre N subcategorias proporcionalmente aos pesos fornecidos.
     *
     * Estratégia em duas etapas (garante soma exata = $total sem violar invariante):
     * 1. Pré-alocação: cada subcategoria recebe 1 slot base (garante mínimo sem violar soma).
     * 2. Remainder: distribui os ($total - N) slots restantes via Hare (resto-maior).
     *
     * Caso especial: se $total < N, as primeiras $total subcategorias recebem 1 slot
     * e as demais ficam com 0 (aceita que algumas subcategorias fiquem sem slot).
     *
     * @param  array<int, float>  $weights  Peso de cada subcategoria (índices 0..N-1).
     * @return array<int, int> Quantidade de slots por índice (soma = $total).
     */
    private function distributeProportionally(array $weights, int $total): array
    {
        $n = count($weights);

        if ($n === 0 || $total <= 0) {
            return [];
        }

        // Capacidade insuficiente para 1 por subcat: atribuir às primeiras $total
        if ($total < $n) {
            $result = array_fill(0, $n, 0);
            for ($i = 0; $i < $total; $i++) {
                $result[$i] = 1;
            }

            return $result;
        }

        // Pré-alocação: 1 slot garantido para cada subcategoria
        $remaining = $total - $n;
        $result = array_fill(0, $n, 1);

        if ($remaining === 0) {
            return $result;
        }

        // Hare LR aplicado apenas sobre os slots restantes
        $totalWeight = (float) max(array_sum($weights), 1e-9);
        $floors = [];
        $remainders = [];
        $floorSum = 0;

        foreach ($weights as $i => $w) {
            $rawShare = $remaining * ($w / $totalWeight);
            $floor = (int) floor($rawShare);
            $floors[$i] = $floor;
            $remainders[$i] = $rawShare - $floor;
            $floorSum += $floor;
        }

        // Distribuir os slots de arredondamento pelos maiores restos
        $extraRemaining = $remaining - $floorSum;
        arsort($remainders);
        $count = 0;

        foreach (array_keys($remainders) as $i) {
            if ($count >= $extraRemaining) {
                break;
            }

            $floors[$i]++;
            $count++;
        }

        // Somar os extras à pré-alocação
        foreach ($floors as $i => $extra) {
            $result[$i] += $extra;
        }

        ksort($result);

        return array_values($result);
    }

    /**
     * Calcula a demanda individual de slots por subcategoria: ceil(totalWidth / shelfWidth), mínimo 1.
     *
     * Resolve a regra-mor: subcategoria com mix de 267 cm → 3 prateleiras de 100 cm;
     * subcategoria com 44 cm → 1 prateleira. Sem ambiguidade de "soma global ÷ total subcats".
     *
     * @param  Collection<int, array{category: Category, summary: CategoryAbcSummary, role: CategoryRole}>  $withDemand
     * @return array<int, int> Contagem de slots por índice (valores ≥ 1).
     */
    private function computePerSubcatSlots(Collection $withDemand, float $shelfWidth): array
    {
        return $withDemand->values()->map(
            fn ($item) => max(1, (int) ceil($item['summary']->totalWidth / max($shelfWidth, 1.0)))
        )->all();
    }

    /**
     * Distribui $total slots proporcionalmente aos pesos via Hare puro (sem pré-alocação de 1).
     *
     * Diferença do distributeProportionally: não garante 1 slot por subcategoria.
     * Isso preserva a proporção correta ao escalar para baixo (capacidade < demanda):
     *   - Ex.: pesos=[3,1,1], total=3 → [1.8,0.6,0.6] → floors=[1,0,0] + extras=[1,1] = [2,1,0]
     *   - A subcategoria pequena com 0 slots não entra no plano (aceitável sob restrição).
     *
     * Deve ser usado apenas quando hasSomeWidth=true e a capacidade é insuficiente para demanda plena.
     *
     * @param  array<int, float>  $weights  Pesos (slots demandados por subcategoria).
     * @return array<int, int> Soma exata = $total.
     */
    private function distributeByPureHare(array $weights, int $total): array
    {
        $n = count($weights);

        if ($n === 0 || $total <= 0) {
            return [];
        }

        $totalWeight = (float) max(array_sum($weights), 1e-9);
        $result = array_fill(0, $n, 0);
        $remainders = [];
        $floorSum = 0;

        foreach ($weights as $i => $w) {
            $rawShare = $total * ($w / $totalWeight);
            $floor = (int) floor($rawShare);
            $result[$i] = $floor;
            $remainders[$i] = $rawShare - $floor;
            $floorSum += $floor;
        }

        // Distribuir os slots de arredondamento pelos maiores restos
        $extra = $total - $floorSum;
        arsort($remainders);
        $count = 0;

        foreach (array_keys($remainders) as $i) {
            if ($count >= $extra) {
                break;
            }

            $result[$i]++;
            $count++;
        }

        return array_values($result);
    }

    /**
     * Distribui $totalCapacity slots entre subcategorias — Approach B com overflow-routing.
     *
     * Três estratégias conforme a relação capacidade/demanda e overflow por subcategoria:
     *
     * 1. Capacidade insuficiente (totalCapacity < demandedTotal):
     *    Hare puro escala a demanda proporcionalmente para baixo.
     *    Subcategorias de maior demanda perdem menos prateleiras que as de menor demanda.
     *
     * 2. Capacidade suficiente, com alguma categoria de overflow (overflowSum > 0):
     *    a) Cada subcategoria recebe exatamente o que demanda (base garantida).
     *    b) Slots excedentes fluem (Hare) proporcionalmente ao overflow de cada subcategoria:
     *       overflow[i] = totalWidth[i] % shelfWidth.
     *       Categorias com overflow = 0 (ex.: DE TRIGO a 600 cm exatos) NÃO recebem extras,
     *       pois qualquer slot além dos demandados ficaria garantidamente vazio.
     *       Categorias com overflow > 0 (última prateleira parcialmente cheia) absorvem os
     *       extras; o placement engine pode espalhar os produtos remanescentes por essas slots.
     *
     *    Exemplo: demands=[6,1,1,1], overflows=[0, 80, 40, 60], capacity=12 → extra=3.
     *    overflowSum=180 → Hare([0,80,40,60], 3) = [0,1,1,1].
     *    result = [6, 2, 2, 2] — DE TRIGO não recebe extras; as demais absorvem 1 cada.
     *
     * 3. Capacidade suficiente, nenhuma categoria tem overflow (overflowSum = 0):
     *    Fallback: extras em round-robin pelas subcategorias empatadas no TOPO de demanda.
     *
     * @param  array<int, int>  $demanded  Slots demandados por índice (valores ≥ 1).
     * @param  array<int, float>  $overflowWeights  Peso de overflow por índice (totalWidth % shelfWidth).
     * @return array<int, int> Contagens finais; soma exata = $totalCapacity.
     */
    private function distributeWithExtraToOverflow(array $demanded, array $overflowWeights, int $totalCapacity): array
    {
        $n = count($demanded);

        if ($n === 0 || $totalCapacity <= 0) {
            return [];
        }

        $demandedTotal = (int) array_sum($demanded);

        // Capacidade insuficiente: Hare puro escala demandas para baixo (preserva proporção).
        if ($demandedTotal >= $totalCapacity) {
            return $this->distributeByPureHare(array_map('floatval', $demanded), $totalCapacity);
        }

        // Capacidade suficiente: cada subcat recebe sua demanda exata como base.
        $result = $demanded;
        $extra = $totalCapacity - $demandedTotal;
        $overflowSum = (float) array_sum($overflowWeights);

        if ($overflowSum > 0.0) {
            // Distribui extras proporcionalmente ao overflow via Hare puro.
            // Categorias com overflow=0 recebem 0 extras; evita slots garantidamente vazios.
            $extraAllocated = $this->distributeByPureHare($overflowWeights, $extra);

            foreach ($extraAllocated as $i => $add) {
                $result[$i] += $add;
            }
        } else {
            // Fallback (todas as demandas são múltiplos exatos de shelfWidth):
            // round-robin pelas subcategorias empatadas no topo de demanda.
            $maxDemand = max($demanded);
            $topIndices = array_values(array_filter(
                array_keys($demanded),
                fn (int $i) => $demanded[$i] === $maxDemand
            ));

            for ($i = 0; $i < $extra; $i++) {
                $result[$topIndices[$i % count($topIndices)]]++;
            }
        }

        return $result;
    }

    /**
     * Deriva a classe ABC dominante a partir do abcClassMap (product_id → classe).
     * Retorna null se o mapa estiver vazio.
     *
     * @param  array<string, string>  $abcClassMap
     */
    private function deriveDominantAbcFromMap(array $abcClassMap): ?string
    {
        if (empty($abcClassMap)) {
            return null;
        }

        $counts = [];

        foreach ($abcClassMap as $abc) {
            $counts[$abc] = ($counts[$abc] ?? 0) + 1;
        }

        arsort($counts);
        $first = array_key_first($counts);

        return $first !== null ? (string) $first : null;
    }

    /**
     * Deriva min_facings para um slot baseado na classe ABC dominante.
     *
     * Usa valores fixos por ABC (ABC_MIN_FACINGS) em vez de escalar até maxFacings.
     * Isso evita que o piso mínimo atinja o teto de expansão (maxFacings), o que tornaria
     * produtos de largura realista impossíveis de posicionar em prateleiras padrão.
     *
     * Exemplos (min=1, max=10):
     *   A → 3  (3 × 23cm = 69cm ≤ 96cm ✓)
     *   B → 2
     *   C → 1
     *
     * O resultado é sempre clamped em [min, max] para respeitar os limites configurados.
     */
    private function deriveMinFacings(?string $abcClass, int $min, int $max): int
    {
        $abcMin = self::ABC_MIN_FACINGS[$abcClass ?? ''] ?? self::ABC_MIN_FACINGS[''];

        return max($min, min($max, $abcMin));
    }

    /**
     * Visual criteria com ABC sempre em primeiro lugar (não substituível).
     * Os critérios secundários fornecidos são anexados após score_abc.
     * Quando nenhum secundário é passado, usa margem desc como padrão.
     *
     * @param  list<array{key: string, direction: string}>  $secondaryCriteria
     * @return list<array{key: string, direction: string}>
     */
    private function buildVisualCriteria(array $secondaryCriteria = []): array
    {
        $primary = ['key' => 'score_abc', 'direction' => 'desc'];

        if ($secondaryCriteria === []) {
            return [$primary, ['key' => 'margem', 'direction' => 'desc']];
        }

        return array_merge([$primary], $secondaryCriteria);
    }

    private function prefersHot(CategoryRole $role): bool
    {
        return in_array($role, self::HOT_ROLES, true);
    }

    /**
     * Limite de participação por SKU derivado do papel da categoria.
     *
     * Destino → 40%: categoria de destino atrai tráfego, mas nenhum SKU deve dominar.
     * Impulso → 35%: impulso depende de variedade visível para converter.
     * Outros papéis → null (sem limite, comportamento padrão).
     *
     * O limite configurado explicitamente pelo usuário em $settings->maxSharePerSku
     * sempre tem precedência sobre o padrão por papel.
     */
    private function deriveMaxSharePerSku(CategoryRole $role, ?int $configuredLimit): ?int
    {
        if ($configuredLimit !== null) {
            return $configuredLimit;
        }

        return match ($role) {
            CategoryRole::Destino => 40,
            CategoryRole::Impulso => 35,
            default => null,
        };
    }
}
