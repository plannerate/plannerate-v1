<?php

use App\Services\AutoPlanogram\Adjacency\RuleBasedResolver;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\Grouping\HierarchicalBlockGrouper;
use App\Services\AutoPlanogram\Placement\GreedyShelfPlacer;
use App\Services\AutoPlanogram\Scoring\ScoringWeightsValue;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate\MerchandisingRulesService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

function blockCategoryChain(string $segmentId, string $segmentName = 'Segmento'): Category
{
    $level4 = new Category;
    $level4->id = (string) Str::ulid();
    $level4->name = 'Categoria';
    $level4->hierarchy_position = 4;
    $level4->is_placeholder = false;

    $level5 = new Category;
    $level5->id = (string) Str::ulid();
    $level5->name = 'Subcategoria';
    $level5->hierarchy_position = 5;
    $level5->category_id = $level4->id;
    $level5->is_placeholder = false;
    $level5->setRelation('parent', $level4);

    $level6 = new Category;
    $level6->id = $segmentId;
    $level6->name = $segmentName;
    $level6->hierarchy_position = 6;
    $level6->category_id = $level5->id;
    $level6->is_placeholder = false;
    $level6->setRelation('parent', $level5);

    return $level6;
}

function blockProduct(string $name, float $width, Category $category, float $score): ScoredProduct
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = $name;
    $product->ean = '7890000000000';
    $product->codigo_erp = Str::slug($name);
    $product->width = $width;
    $product->height = 20;
    $product->depth = 20;
    $product->setRelation('category', $category);

    return new ScoredProduct(
        productId: $product->id,
        ean: $product->ean,
        score: $score,
        product: $product,
        metadata: [
            'sales_total' => $score * 10,
            'estimated_facing' => 1,
        ],
    );
}

function blockSection(int $shelvesCount, float $width = 40): Section
{
    $section = new Section;
    $section->id = (string) Str::ulid();
    $section->width = $width;

    $shelves = collect();
    for ($index = 0; $index < $shelvesCount; $index++) {
        $shelf = new Shelf;
        $shelf->id = (string) Str::ulid();
        $shelf->shelf_width = $width;
        $shelf->shelf_height = 30;
        $shelf->shelf_depth = 30;
        $shelf->ordering = $index;
        $shelves->push($shelf);
    }

    $section->setRelation('shelves', $shelves);

    return $section;
}

function blockSettings(): PlacementSettings
{
    return new PlacementSettings(
        strategy: 'mix',
        useExistingAnalysis: false,
        startDate: null,
        endDate: null,
        minFacings: 1,
        maxFacings: 1,
        weights: new ScoringWeightsValue(0.4, 0.3, 0.2, 0.1, 4, 6, 4),
    );
}

function placeBlocks(Collection $scoredProducts, Collection $sections): Collection
{
    $settings = blockSettings();
    $blocks = (new HierarchicalBlockGrouper)->group($scoredProducts, $settings);
    $ordered = (new RuleBasedResolver)->resolve($blocks, $settings);

    return (new GreedyShelfPlacer(new MerchandisingRulesService))->place($ordered, $sections, $settings);
}

test('bloco nunca e quebrado entre sections quando cabe em uma section', function (): void {
    $segmentA = blockCategoryChain((string) Str::ulid(), 'Hidratacao');
    $segmentB = blockCategoryChain((string) Str::ulid(), 'Anticaspa');

    $scored = collect([
        blockProduct('A1', 15, $segmentA, 100),
        blockProduct('A2', 15, $segmentA, 95),
        blockProduct('A3', 15, $segmentA, 90),
        blockProduct('B1', 15, $segmentB, 80),
    ]);

    $placed = placeBlocks($scored, collect([
        blockSection(2, 40),
        blockSection(2, 40),
    ]));

    $segmentAProductIds = $scored->take(3)->pluck('product.id');
    $sectionsForBlock = $placed
        ->filter(fn ($segment) => $segment->layers->pluck('productId')->intersect($segmentAProductIds)->isNotEmpty())
        ->pluck('sectionId')
        ->unique();

    expect($sectionsForBlock)->toHaveCount(1);
});

test('bloco maior que a section pode ser quebrado entre sections', function (): void {
    $segmentA = blockCategoryChain((string) Str::ulid(), 'Gigante');

    $scored = collect([
        blockProduct('A1', 35, $segmentA, 100),
        blockProduct('A2', 35, $segmentA, 95),
        blockProduct('A3', 35, $segmentA, 90),
    ]);

    $placed = placeBlocks($scored, collect([
        blockSection(1, 40),
        blockSection(1, 40),
        blockSection(1, 40),
    ]));

    $productIds = $scored->pluck('product.id');
    $sectionsForBlock = $placed
        ->filter(fn ($segment) => $segment->layers->pluck('productId')->intersect($productIds)->isNotEmpty())
        ->pluck('sectionId')
        ->unique();

    expect($sectionsForBlock->count())->toBeGreaterThan(1);
});
