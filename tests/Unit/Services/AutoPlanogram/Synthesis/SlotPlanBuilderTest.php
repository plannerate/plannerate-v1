<?php

use App\Enums\CategoryRole;
use App\Models\Category;
use App\Services\AutoPlanogram\DTO\CategoryAbcSummary;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\Synthesis\SlotPlanBuilder;
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

test('subcategoria grande recebe mais prateleiras que a pequena (proporcional por width)', function (): void {
    // Categoria grande: 200 cm → demanda 2 prateleiras (ceil(200/100))
    // Categoria pequena: 50 cm → demanda 1 prateleira (ceil(50/100))
    // Total: 3 prateleiras, capacidade 4 → usa 3
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

    // Categoria grande recebe mais prateleiras
    expect($slotsGrande)->toBeGreaterThan($slotsPequena);

    // Não super-provisiona: total = ceil(250/100) = 3, não 4
    expect(count($plan))->toBe(3);
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

test('não super-provisiona quando demanda < capacidade física', function (): void {
    // 2 subcategorias com 30 cm cada → total 60 cm → ceil(60/100) = 1 prateleira
    // Mínimo = 2 subcats → usa 2 prateleiras de 8 disponíveis
    $builder = new SlotPlanBuilder;

    $subcats = Collection::make([
        makeSubcatItem(makeCat(), makeSummaryWith('A', qty: 100.0, totalWidth: 30.0), CategoryRole::Destino),
        makeSubcatItem(makeCat(), makeSummaryWith('C', qty: 20.0, totalWidth: 30.0), CategoryRole::Complementar),
    ]);

    $plan = $builder->build(
        selectedCategory: makeCat(),
        subcategories: $subcats,
        numModules: 2,
        shelvesPerModule: 4,
        settings: makeSlotPlanSettings(),
        shelfWidth: 100.0,
    );

    // Demanda = max(2, ceil(60/100)) = max(2, 1) = 2 slots usados, não 8
    expect(count($plan))->toBe(2);
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

test('min_facings maior para curva A do que para curva C', function (): void {
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

    expect($facingsA)->toBeGreaterThan($facingsC);
});

test('leaf: min_facings deriva da classe ABC dominante do abcClassMap', function (): void {
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

    // Dominante = 'A' → min_facings deve ser maior que o mínimo (1)
    expect($plan)->not->toBeEmpty();
    $facings = $plan[0]->minFacings;
    expect($facings)->toBeGreaterThan(1); // A > mínimo
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
