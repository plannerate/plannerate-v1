<?php

use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\Grouping\HierarchicalBlockGrouper;
use App\Services\AutoPlanogram\Scoring\ScoringWeightsValue;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Support\Str;

function hierarchySettings(): PlacementSettings
{
    return new PlacementSettings(
        strategy: 'mix',
        useExistingAnalysis: false,
        startDate: null,
        endDate: null,
        weights: new ScoringWeightsValue(0.4, 0.3, 0.2, 0.1, 4, 6, 4),
    );
}

function makeCategoryNode(int $level, ?Category $parent = null, bool $placeholder = false): Category
{
    $category = new Category;
    $category->id = (string) Str::ulid();
    $category->name = 'Categoria '.$level;
    $category->hierarchy_position = $level;
    $category->category_id = $parent?->id;
    $category->is_placeholder = $placeholder;

    if ($parent !== null) {
        $category->setRelation('parent', $parent);
    }

    return $category;
}

function makeGroupedProduct(Category $category, int $index): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = 'Produto '.$index;
    $product->width = 10;
    $product->setRelation('category', $category);

    return $product;
}

function makeProductWithoutCategory(int $index): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = 'Produto '.$index;
    $product->width = 10;

    return $product;
}

function makeScored(Product $product, float $score = 1.0): ScoredProduct
{
    return new ScoredProduct(
        productId: $product->id,
        ean: '7890000000000',
        score: $score,
        product: $product,
        metadata: ['estimated_facing' => 1],
    );
}

test('produto categorizado ate nivel 7 com teto 6 agrupa no nivel 6', function (): void {
    $level4 = makeCategoryNode(4);
    $level5 = makeCategoryNode(5, $level4);
    $level6 = makeCategoryNode(6, $level5);
    $level7 = makeCategoryNode(7, $level6);

    $blocks = (new HierarchicalBlockGrouper)->group(
        collect([makeScored(makeGroupedProduct($level7, 1))]),
        hierarchySettings(),
    );

    expect($blocks)->toHaveCount(1)
        ->and($blocks->first()->groupingKey)->toBe(sprintf('block:lvl6:%s', $level6->id))
        ->and($blocks->first()->blockHierarchyLevel)->toBe(6)
        ->and($blocks->first()->adjacencyCategoryId)->toBe($level4->id);
});

test('produto categorizado ate nivel 6 com teto 6 agrupa no nivel 6', function (): void {
    $level4 = makeCategoryNode(4);
    $level5 = makeCategoryNode(5, $level4);
    $level6 = makeCategoryNode(6, $level5);

    $blocks = (new HierarchicalBlockGrouper)->group(
        collect([makeScored(makeGroupedProduct($level6, 1))]),
        hierarchySettings(),
    );

    expect($blocks)->toHaveCount(1)
        ->and($blocks->first()->groupingKey)->toBe(sprintf('block:lvl6:%s', $level6->id))
        ->and($blocks->first()->blockHierarchyLevel)->toBe(6)
        ->and($blocks->first()->adjacencyCategoryId)->toBe($level4->id);
});

test('produto categorizado ate nivel 4 com teto 6 agrupa na folha nivel 4', function (): void {
    $level4 = makeCategoryNode(4);

    $blocks = (new HierarchicalBlockGrouper)->group(
        collect([makeScored(makeGroupedProduct($level4, 1))]),
        hierarchySettings(),
    );

    expect($blocks)->toHaveCount(1)
        ->and($blocks->first()->groupingKey)->toBe(sprintf('block:lvl4:%s', $level4->id))
        ->and($blocks->first()->blockHierarchyLevel)->toBe(4)
        ->and($blocks->first()->adjacencyCategoryId)->toBe($level4->id);
});

test('produto categorizado ate nivel 3 com teto 6 agrupa na folha nivel 3', function (): void {
    $level3 = makeCategoryNode(3);

    $blocks = (new HierarchicalBlockGrouper)->group(
        collect([makeScored(makeGroupedProduct($level3, 1))]),
        hierarchySettings(),
    );

    expect($blocks)->toHaveCount(1)
        ->and($blocks->first()->groupingKey)->toBe(sprintf('block:lvl3:%s', $level3->id))
        ->and($blocks->first()->blockHierarchyLevel)->toBe(3)
        ->and($blocks->first()->adjacencyCategoryId)->toBe($level3->id);
});

test('produtos no mesmo ramo mas com profundidades efetivas diferentes viram blocos distintos', function (): void {
    $level4 = makeCategoryNode(4);
    $level5 = makeCategoryNode(5, $level4);
    $level6 = makeCategoryNode(6, $level5);

    $products = collect([
        makeScored(makeGroupedProduct($level6, 1), 10),
        makeScored(makeGroupedProduct($level4, 2), 9),
    ]);

    $blocks = (new HierarchicalBlockGrouper)->group($products, hierarchySettings());

    expect($blocks)->toHaveCount(2)
        ->and($blocks->pluck('groupingKey')->all())->toContain(
            sprintf('block:lvl6:%s', $level6->id),
            sprintf('block:lvl4:%s', $level4->id),
        );
});

test('dois produtos na mesma categoria nivel 4 viram um bloco', function (): void {
    $level4 = makeCategoryNode(4);

    $products = collect([
        makeScored(makeGroupedProduct($level4, 1), 10),
        makeScored(makeGroupedProduct($level4, 2), 9),
    ]);

    $blocks = (new HierarchicalBlockGrouper)->group($products, hierarchySettings());

    expect($blocks)->toHaveCount(1)
        ->and($blocks->first()->groupingKey)->toBe(sprintf('block:lvl4:%s', $level4->id))
        ->and($blocks->first()->children)->toHaveCount(2);
});

test('4 produtos do mesmo segmento nivel 6 viram um bloco', function (): void {
    $level4 = makeCategoryNode(4);
    $level5 = makeCategoryNode(5, $level4);
    $segment = makeCategoryNode(6, $level5);

    $products = collect(range(1, 4))
        ->map(fn (int $index) => makeScored(makeGroupedProduct($segment, $index), 10 - $index));

    $blocks = (new HierarchicalBlockGrouper)->group($products, hierarchySettings());

    expect($blocks)->toHaveCount(1)
        ->and($blocks->first()->groupingKey)->toBe(sprintf('block:lvl6:%s', $segment->id))
        ->and($blocks->first()->adjacencyCategoryId)->toBe($level4->id);
});

test('2 segmentos diferentes na mesma categoria viram 2 blocos', function (): void {
    $level4 = makeCategoryNode(4);
    $level5 = makeCategoryNode(5, $level4);
    $segmentA = makeCategoryNode(6, $level5);
    $segmentB = makeCategoryNode(6, $level5);

    $products = collect([
        makeScored(makeGroupedProduct($segmentA, 1), 9),
        makeScored(makeGroupedProduct($segmentA, 2), 8),
        makeScored(makeGroupedProduct($segmentB, 3), 7),
    ]);

    $blocks = (new HierarchicalBlockGrouper)->group($products, hierarchySettings());

    expect($blocks)->toHaveCount(2)
        ->and($blocks->pluck('groupingKey')->all())->toContain(
            sprintf('block:lvl6:%s', $segmentA->id),
            sprintf('block:lvl6:%s', $segmentB->id),
        );
});

test('categoria mais rasa que o nivel alvo usa folha em vez de singleton', function (): void {
    $level4 = makeCategoryNode(4);
    $level5 = makeCategoryNode(5, $level4);
    $product = makeGroupedProduct($level5, 1);

    $blocks = (new HierarchicalBlockGrouper)->group(collect([makeScored($product)]), hierarchySettings());

    expect($blocks)->toHaveCount(1)
        ->and($blocks->first()->groupingKey)->toBe(sprintf('block:lvl5:%s', $level5->id))
        ->and($blocks->first()->isPlaceholder)->toBeFalse();
});

test('categoria placeholder no nivel alvo agrupa no ancestral nao placeholder', function (): void {
    $level4 = makeCategoryNode(4);
    $level5 = makeCategoryNode(5, $level4);
    $placeholderSegment = makeCategoryNode(6, $level5, true);
    $product = makeGroupedProduct($placeholderSegment, 1);

    $blocks = (new HierarchicalBlockGrouper)->group(collect([makeScored($product)]), hierarchySettings());

    expect($blocks)->toHaveCount(1)
        ->and($blocks->first()->groupingKey)->toBe(sprintf('block:lvl5:%s', $level5->id))
        ->and($blocks->first()->isPlaceholder)->toBeFalse();
});

test('produto sem categoria vira bloco singleton', function (): void {
    $product = makeProductWithoutCategory(1);

    $blocks = (new HierarchicalBlockGrouper)->group(collect([makeScored($product)]), hierarchySettings());

    expect($blocks)->toHaveCount(1)
        ->and($blocks->first()->groupingKey)->toBe('singleton:'.$product->id)
        ->and($blocks->first()->isPlaceholder)->toBeTrue();
});
