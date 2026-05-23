<?php

use App\Enums\CategoryRole;
use App\Models\Category;
use App\Services\AutoPlanogram\DTO\CategoryAbcSummary;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\Synthesis\SlotPlanBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

function makeSlotPlanSettings(int $min = 1, int $max = 5): PlacementSettings
{
    return new PlacementSettings(
        strategy: 'abc',
        useExistingAnalysis: false,
        startDate: null,
        endDate: null,
        minFacings: $min,
        maxFacings: $max,
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

function makeSummaryWith(?string $abc, float $qty = 0.5, float $margem = 0.5): CategoryAbcSummary
{
    return new CategoryAbcSummary(
        categoryId: Str::ulid()->toBase32(),
        totalQuantity: $qty,
        totalMargem: $margem,
        skuCount: 3,
        dominantAbcClass: $abc,
    );
}

test('2 módulos x 4 prateleiras com 3 subcats cobre todos os slots sem colisão', function (): void {
    $builder = new SlotPlanBuilder;

    $subcats = Collection::make([
        makeSubcatItem(makeCat(), makeSummaryWith('A', 0.9), CategoryRole::Destino),
        makeSubcatItem(makeCat(), makeSummaryWith('B', 0.5), CategoryRole::Rotina),
        makeSubcatItem(makeCat(), makeSummaryWith('C', 0.1), CategoryRole::Complementar),
    ]);

    $plan = $builder->build(
        selectedCategory: makeCat(),
        subcategories: $subcats,
        numModules: 2,
        shelvesPerModule: 4,
        settings: makeSlotPlanSettings(),
    );

    // 2 módulos × 4 prateleiras = 8 slots
    expect($plan)->toHaveCount(8);

    // Sem colisões: cada (módulo, prateleira) aparece exatamente uma vez
    $pairs = array_map(fn ($e) => "{$e->moduleNumber}:{$e->shelfOrder}", $plan);
    expect(array_unique($pairs))->toHaveCount(8);
});

test('subcategoria curva A cai em prateleira de zona quente', function (): void {
    $builder = new SlotPlanBuilder;

    $catA = makeCat();
    $subcats = Collection::make([
        makeSubcatItem($catA, makeSummaryWith('A', 0.9), CategoryRole::Destino),
        makeSubcatItem(makeCat(), makeSummaryWith('C', 0.1), CategoryRole::Complementar),
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
