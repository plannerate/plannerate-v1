<?php

use App\Models\Category;
use App\Services\AutoPlanogram\DTO\CategoryAbcSummary;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\Synthesis\SlotPlanBuilder;
use Callcocam\LaravelRaptorPlannerate\Enums\CategoryRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Cria PlacementSettings mínimas para os testes do builder.
 */
function makeSlotPlanSettings(int $min = 1, int $max = 5, array $abcClassMap = []): PlacementSettings
{
    return new PlacementSettings(
        strategy: 'abc',
        useExistingAnalysis: false,
        startDate: null,
        endDate: null,
        minFacings: $min,
        maxFacings: $max,
        abcClassMap: $abcClassMap,
    );
}

function makeCat(?CategoryRole $role = null, string $id = ''): Category
{
    $cat = new Category;
    $cat->id = $id ?: Str::ulid()->toBase32();
    $cat->role = $role;

    return $cat;
}

function makeSubcatItem(Category $cat, CategoryAbcSummary $summary, CategoryRole $role): array
{
    return ['category' => $cat, 'summary' => $summary, 'role' => $role];
}

/**
 * Cria CategoryAbcSummary para testes.
 *
 * @param  float  $totalWidth  Largura total dos produtos (cm). 0 = sem dados de width (fallback por quantity).
 */
function makeSummaryWith(
    ?string $abc,
    float $qty = 0.5,
    float $margem = 0.5,
    float $totalWidth = 0.0,
    int $skuCount = 3,
): CategoryAbcSummary {
    return new CategoryAbcSummary(
        categoryId: Str::ulid()->toBase32(),
        totalQuantity: $qty,
        totalMargem: $margem,
        skuCount: $skuCount,
        dominantAbcClass: $abc,
        totalWidth: $totalWidth,
    );
}

// ── Testes: partição uniforme (fallback por quantity, sem dados de width) ────

test('sem dados de largura distribui todos os slots (fallback por quantity)', function (): void {
    // Sem totalWidth → hasSomeWidth = false → usa capacidade total
    $builder = new SlotPlanBuilder;

    $subcats = Collection::make([
        makeSubcatItem(makeCat(), makeSummaryWith('A', qty: 0.9), CategoryRole::Destino),
        makeSubcatItem(makeCat(), makeSummaryWith('B', qty: 0.5), CategoryRole::Rotina),
        makeSubcatItem(makeCat(), makeSummaryWith('C', qty: 0.1), CategoryRole::Complementar),
    ]);

    $plan = $builder->build(
        selectedCategory: makeCat(),
        subcategories: $subcats,
        numModules: 2,
        shelvesPerModule: 4,
        settings: makeSlotPlanSettings(),
        shelfWidth: 100.0,
    );

    // Sem dados de largura → capacidade total é usada (não super-provisiona mas não reduz)
    expect($plan)->toHaveCount(8);

    // Sem colisões: cada (módulo, prateleira) aparece exatamente uma vez
    $pairs = array_map(fn ($e) => "{$e->moduleNumber}:{$e->shelfOrder}", $plan);
    expect(array_unique($pairs))->toHaveCount(8);
});

// ── Testes: partição proporcional por demanda (com totalWidth) ───────────────

test('extras vão para categoria com overflow, não para a de maior demanda', function (): void {
    // catGrande: 200 cm → demanda 2 slots, overflow = 200 % 100 = 0 (ambas prateleiras 100% cheias).
    // catPequena: 50 cm → demanda 1 slot, overflow = 50 % 100 = 50 (última prateleira 50% cheia).
    // Capacidade: 1 módulo × 4 = 4; demanda total = 3; extra = 1.
    // Overflow-routing: catGrande (overflow=0) não recebe o extra; catPequena (overflow=50) recebe.
    // Resultado esperado: catGrande=2 (demanda exata), catPequena=2 (1 + 1 extra). Total=4.
    $builder = new SlotPlanBuilder;

    $catGrande = makeCat(id: 'cat-grande');
    $catPequena = makeCat(id: 'cat-pequena');

    $subcats = Collection::make([
        makeSubcatItem($catGrande, makeSummaryWith('A', qty: 200.0, totalWidth: 200.0), CategoryRole::Destino),
        makeSubcatItem($catPequena, makeSummaryWith('C', qty: 50.0, totalWidth: 50.0), CategoryRole::Complementar),
    ]);

    $plan = $builder->build(
        selectedCategory: makeCat(),
        subcategories: $subcats,
        numModules: 1,
        shelvesPerModule: 4,
        settings: makeSlotPlanSettings(),
        shelfWidth: 100.0,
    );

    $slotsGrande = collect($plan)->where('categoryId', 'cat-grande')->count();
    $slotsPequena = collect($plan)->where('categoryId', 'cat-pequena')->count();

    // Usa toda a capacidade (4 slots)
    expect(count($plan))->toBe(4);

    // catGrande (overflow=0) recebe exatamente o demandado, sem extras.
    expect($slotsGrande)->toBe(2);

    // catPequena (overflow=50) absorve o slot extra.
    expect($slotsPequena)->toBe(2);
});

test('subcategoria sem produto elegível não gera slot', function (): void {
    $builder = new SlotPlanBuilder;

    $catComDemanda = makeCat(id: 'cat-com');
    $catSemDemanda = makeCat(id: 'cat-sem');

    $subcats = Collection::make([
        makeSubcatItem($catComDemanda, makeSummaryWith('A', qty: 100.0, totalWidth: 80.0, skuCount: 5), CategoryRole::Destino),
        // skuCount=0, totalQuantity=0 → sem produto elegível → excluída
        makeSubcatItem($catSemDemanda, makeSummaryWith(null, qty: 0.0, totalWidth: 0.0, skuCount: 0), CategoryRole::Complementar),
    ]);

    $plan = $builder->build(
        selectedCategory: makeCat(),
        subcategories: $subcats,
        numModules: 2,
        shelvesPerModule: 4,
        settings: makeSlotPlanSettings(),
        shelfWidth: 100.0,
    );

    // Somente a categoria com demanda gera slots
    $idsNoPlan = array_unique(array_map(fn ($e) => $e->categoryId, $plan));
    expect($idsNoPlan)->not->toContain('cat-sem');
    expect(count($plan))->toBeGreaterThan(0);
});

test('slots excedentes distribuídos igualmente entre subcategorias de mesma demanda', function (): void {
    // 2 subcategorias com 30 cm cada → demanda = [1, 1] = 2 slots.
    // Capacidade física: 2 módulos × 4 prateleiras = 8 slots. Excedente = 6 slots.
    // Approach B: usa 8 slots; com demanda igual (empate no topo), round-robin distribui 4+4.
    $builder = new SlotPlanBuilder;

    $catIguaisA = makeCat(id: 'cat-iguais-a');
    $catIguaisB = makeCat(id: 'cat-iguais-b');

    $subcats = Collection::make([
        makeSubcatItem($catIguaisA, makeSummaryWith('A', qty: 100.0, totalWidth: 30.0), CategoryRole::Destino),
        makeSubcatItem($catIguaisB, makeSummaryWith('C', qty: 20.0, totalWidth: 30.0), CategoryRole::Complementar),
    ]);

    $plan = $builder->build(
        selectedCategory: makeCat(),
        subcategories: $subcats,
        numModules: 2,
        shelvesPerModule: 4,
        settings: makeSlotPlanSettings(),
        shelfWidth: 100.0,
    );

    // Approach B: usa toda a capacidade disponível (8 slots)
    expect(count($plan))->toBe(8);

    // Demanda igual → extras distribuídos igualmente entre as duas (4 cada)
    $slotsA = collect($plan)->where('categoryId', 'cat-iguais-a')->count();
    $slotsB = collect($plan)->where('categoryId', 'cat-iguais-b')->count();
    expect($slotsA)->toBe(4);
    expect($slotsB)->toBe(4);
});

test('maior demanda sem overflow não recebe extras; categoria com overflow os absorve', function (): void {
    // catAlta: 300 cm → demanda 3 slots, overflow = 300 % 100 = 0 (todas prateleiras 100% cheias).
    // catBaixa: 50 cm → demanda 1 slot, overflow = 50 % 100 = 50 (última prateleira 50% cheia).
    // Capacidade: 2 módulos × 4 = 8 slots. Excedente: 4 slots.
    // Overflow-routing: catAlta (overflow=0) NÃO recebe extras (evita slots garantidamente vazios);
    //   catBaixa (overflow=50) absorve todos os 4 extras.
    // Resultado esperado: catAlta = 3 (demanda exata); catBaixa = 1 + 4 = 5. Total = 8.
    $builder = new SlotPlanBuilder;

    $catAlta = makeCat(id: 'cat-alta-demanda');
    $catBaixa = makeCat(id: 'cat-baixa-demanda');

    $subcats = Collection::make([
        makeSubcatItem($catAlta, makeSummaryWith('A', qty: 300.0, totalWidth: 300.0), CategoryRole::Destino),
        makeSubcatItem($catBaixa, makeSummaryWith('C', qty: 50.0, totalWidth: 50.0), CategoryRole::Complementar),
    ]);

    $plan = $builder->build(
        selectedCategory: makeCat(),
        subcategories: $subcats,
        numModules: 2,
        shelvesPerModule: 4,
        settings: makeSlotPlanSettings(),
        shelfWidth: 100.0,
    );

    // Usa toda a capacidade (8 slots)
    expect(count($plan))->toBe(8);

    $slotsAlta = collect($plan)->where('categoryId', 'cat-alta-demanda')->count();
    $slotsBaixa = collect($plan)->where('categoryId', 'cat-baixa-demanda')->count();

    // catAlta (overflow=0) recebe apenas os 3 slots demandados — sem extras que ficariam vazios.
    expect($slotsAlta)->toBe(3);

    // catBaixa (overflow=50) absorve os 4 extras: 1 demandado + 4 = 5 slots.
    expect($slotsBaixa)->toBe(5);
});

test('sem overflow em nenhuma categoria, extras vão para maior demanda (fallback round-robin)', function (): void {
    // catA: 200 cm → demanda 2 slots, overflow = 0.
    // catB: 400 cm → demanda 4 slots, overflow = 0.
    // Capacidade: 2 módulos × 4 = 8. Excedente: 2 slots.
    // Todos overflow=0 → fallback: extras em round-robin para maior demanda (catB=4).
    // Resultado esperado: catA = 2; catB = 4 + 2 = 6. Total = 8.
    $builder = new SlotPlanBuilder;

    $catA = makeCat(id: 'cat-a-exact');
    $catB = makeCat(id: 'cat-b-exact');

    $subcats = Collection::make([
        makeSubcatItem($catA, makeSummaryWith('B', qty: 200.0, totalWidth: 200.0), CategoryRole::Rotina),
        makeSubcatItem($catB, makeSummaryWith('A', qty: 400.0, totalWidth: 400.0), CategoryRole::Destino),
    ]);

    $plan = $builder->build(
        selectedCategory: makeCat(),
        subcategories: $subcats,
        numModules: 2,
        shelvesPerModule: 4,
        settings: makeSlotPlanSettings(),
        shelfWidth: 100.0,
    );

    expect(count($plan))->toBe(8);

    $slotsA = collect($plan)->where('categoryId', 'cat-a-exact')->count();
    $slotsB = collect($plan)->where('categoryId', 'cat-b-exact')->count();

    // Fallback: catB (maior demanda) absorve os extras.
    expect($slotsA)->toBe(2);
    expect($slotsB)->toBe(6);
});

// ── Testes: zona térmica ────────────────────────────────────────────────────

test('subcategoria curva A cai em prateleira de zona quente', function (): void {
    $builder = new SlotPlanBuilder;

    $catA = makeCat();
    $subcats = Collection::make([
        makeSubcatItem($catA, makeSummaryWith('A', qty: 0.9), CategoryRole::Destino),
        makeSubcatItem(makeCat(), makeSummaryWith('C', qty: 0.1), CategoryRole::Complementar),
    ]);

    $plan = $builder->build(
        selectedCategory: makeCat(),
        subcategories: $subcats,
        numModules: 1,
        shelvesPerModule: 4,
        settings: makeSlotPlanSettings(),
    );

    // Entradas da categoria A devem estar em zona quente
    $catAEntries = array_filter($plan, fn ($e) => $e->categoryId === $catA->id);
    foreach ($catAEntries as $entry) {
        expect($entry->zone)->toBe('hot');
    }
});

// ── Testes: min_facings por ABC ──────────────────────────────────────────────

test('min_facings é 1 para todas as classes ABC (expansão prioriza A→B→C na Phase 2)', function (): void {
    $builder = new SlotPlanBuilder;

    $catA = makeCat(id: 'cat-a');
    $catC = makeCat(id: 'cat-c');

    $subcats = Collection::make([
        makeSubcatItem($catA, makeSummaryWith('A'), CategoryRole::Destino),
        makeSubcatItem($catC, makeSummaryWith('C'), CategoryRole::Complementar),
    ]);

    $plan = $builder->build(
        selectedCategory: makeCat(),
        subcategories: $subcats,
        numModules: 2,
        shelvesPerModule: 2,
        settings: makeSlotPlanSettings(min: 1, max: 5),
    );

    $facingsA = collect($plan)->where('categoryId', 'cat-a')->first()?->minFacings;
    $facingsC = collect($plan)->where('categoryId', 'cat-c')->first()?->minFacings;

    // Todos começam com 1 frente; a prioridade A→B→C é resolvida na expansão (Phase 2)
    expect($facingsA)->toBe(1);
    expect($facingsC)->toBe(1);
});

test('leaf: min_facings é 1 independente da classe ABC dominante do abcClassMap', function (): void {
    $builder = new SlotPlanBuilder;

    $leaf = makeCat(id: 'leaf-cat');

    // abcClassMap com dominância de 'A'
    $abcMap = ['prod-1' => 'A', 'prod-2' => 'A', 'prod-3' => 'B'];

    $plan = $builder->build(
        selectedCategory: $leaf,
        subcategories: collect(),
        numModules: 1,
        shelvesPerModule: 2,
        settings: makeSlotPlanSettings(min: 1, max: 5, abcClassMap: $abcMap),
    );

    // Todos começam com 1 frente; expansão A→B→C é Phase 2 do placement engine
    expect($plan)->not->toBeEmpty();
    $facings = $plan[0]->minFacings;
    expect($facings)->toBe(1);
});

// ── Testes: categoria folha ──────────────────────────────────────────────────

test('categoria folha retorna plano sem erro cobrindo todos os slots', function (): void {
    $builder = new SlotPlanBuilder;

    $leaf = makeCat(id: 'leaf-cat');

    // Coleção vazia = folha
    $plan = $builder->build(
        selectedCategory: $leaf,
        subcategories: collect(),
        numModules: 2,
        shelvesPerModule: 3,
        settings: makeSlotPlanSettings(),
    );

    // 6 slots, todos com a categoria folha
    expect($plan)->toHaveCount(6);
    foreach ($plan as $entry) {
        expect($entry->categoryId)->toBe('leaf-cat');
    }

    // Sem colisões
    $pairs = array_map(fn ($e) => "{$e->moduleNumber}:{$e->shelfOrder}", $plan);
    expect(array_unique($pairs))->toHaveCount(6);
});

// ── Testes: visual_criteria ─────────────────────────────────────────────────

test('visual_criteria tem score_abc como primeiro critério', function (): void {
    $builder = new SlotPlanBuilder;

    $subcats = Collection::make([
        makeSubcatItem(makeCat(), makeSummaryWith('B'), CategoryRole::Rotina),
    ]);

    $plan = $builder->build(
        selectedCategory: makeCat(),
        subcategories: $subcats,
        numModules: 1,
        shelvesPerModule: 2,
        settings: makeSlotPlanSettings(),
    );

    expect($plan)->not->toBeEmpty();
    $criteria = $plan[0]->visualCriteria;
    expect($criteria[0]['key'])->toBe('score_abc')
        ->and($criteria[0]['direction'])->toBe('desc');
});
