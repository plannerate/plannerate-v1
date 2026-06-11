<?php

use App\Services\AutoPlanogram\DTO\OrderedBlock;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ProductBlock;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\Placement\GreedyShelfPlacer;
use App\Services\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Enums\PlacementFailureReason;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── helpers ──────────────────────────────────────────────────────────────────

function makePlacerProduct(string $id, float $width = 10.0, float $height = 25.0): Product
{
    $p = new Product;
    $p->id = $id;
    $p->name = "Product {$id}";
    $p->ean = str_pad($id, 13, '0', STR_PAD_LEFT);
    $p->width = $width;
    $p->height = $height;
    $p->depth = 30.0;

    return $p;
}

function makeScoredProduct(Product $product, float $score = 50.0): ScoredProduct
{
    return new ScoredProduct(
        productId: $product->id,
        ean: (string) ($product->ean ?? ''),
        score: $score,
        product: $product,
        metadata: [
            'abc_class' => 'B',
            'sales_total' => 500,
            'margin' => 100,
        ],
    );
}

/**
 * Builds a section with shelves that reflect the real domain model:
 *   - shelf_height = physical board thickness (cm)
 *   - shelf_position = distance from gondola top to the board (cm)
 *
 * Each shelf is spaced so that the clearance above it equals $clearancePerShelf.
 * shelf[0].position = $clearancePerShelf
 * shelf[i].position = clearancePerShelf + i * (clearancePerShelf + boardHeight)
 */
function makeSection(
    float $clearancePerShelf = 30.0,
    float $width = 100.0,
    int $numShelves = 4,
    float $boardHeight = 4.0
): Section {
    $section = new Section;
    $section->id = (string) Str::ulid();
    $section->width = $width;
    $section->height = 200;
    $section->cremalheira_width = 0;

    $shelves = collect();
    for ($i = 0; $i < $numShelves; $i++) {
        $shelf = new Shelf;
        $shelf->id = (string) Str::ulid();
        $shelf->section_id = $section->id;
        $shelf->shelf_height = $boardHeight;
        $shelf->shelf_depth = 40;
        $shelf->shelf_position = $clearancePerShelf + $i * ($clearancePerShelf + $boardHeight);
        $shelves->push($shelf);
    }

    $section->setRelation('shelves', $shelves);

    return $section;
}

function makePlacer(): GreedyShelfPlacer
{
    return new GreedyShelfPlacer(new ProductWidthResolver);
}

function makeSettings(): PlacementSettings
{
    return new PlacementSettings(
        strategy: 'abc',
        useExistingAnalysis: false,
        startDate: null,
        endDate: null,
        minFacings: 1,
        maxFacings: 3,
        tenantId: null,
        weights: null,
    );
}

function makeOrderedBlocks(array $scoredProducts): Collection
{
    $sequence = 0;

    return collect($scoredProducts)->map(function (ScoredProduct $sp) use (&$sequence) {
        $block = new ProductBlock(
            children: collect([$sp]),
            aggregateScore: $sp->score,
            groupingKey: 'singleton:'.$sp->productId,
            totalWidthEstimate: (float) ($sp->product->width ?? 10),
        );

        return new OrderedBlock(block: $block, sequenceOrder: $sequence++);
    });
}

function makeOrderedBlockFromProducts(array $scoredProducts, string $key = 'block:lvl4:test'): Collection
{
    $children = collect($scoredProducts);
    $block = new ProductBlock(
        children: $children,
        aggregateScore: 100,
        groupingKey: $key,
        totalWidthEstimate: $children->sum(fn (ScoredProduct $sp): float => (float) ($sp->product->width ?? 10)),
        blockHierarchyLevel: 4,
    );

    return collect([new OrderedBlock(block: $block, sequenceOrder: 0)]);
}

function placedProductIds(Collection $segments): array
{
    return $segments
        ->map(fn ($segment) => $segment->layers->first()->productId)
        ->values()
        ->all();
}

// ── tests ─────────────────────────────────────────────────────────────────────

describe('GreedyShelfPlacer', function () {
    it('places fitting products even when an earlier product is too tall for the clearance', function () {
        // clearance = 10cm per shelf, board = 4cm
        // TALL product height=16 > 10cm clearance → won't fit
        // SHORT products height=3 < 10cm clearance → fit
        $section = makeSection(clearancePerShelf: 10.0, boardHeight: 4.0, numShelves: 4);
        $placer = makePlacer();
        $settings = makeSettings();

        $blocks = makeOrderedBlocks([
            makeScoredProduct(makePlacerProduct('A', height: 16.0), score: 100.0),
            makeScoredProduct(makePlacerProduct('B', height: 3.0), score: 80.0),
            makeScoredProduct(makePlacerProduct('C', height: 3.0), score: 60.0),
        ]);

        $result = $placer->place($blocks, collect([$section]), $settings);

        $placedIds = placedProductIds($result->placedSegments);
        expect($placedIds)->toContain('B');
        expect($placedIds)->toContain('C');
        expect($placedIds)->not->toContain('A');
        expect($result->rejectedProducts)->toHaveCount(1)
            ->and($result->rejectedProducts->first()['reason'])->toBe(PlacementFailureReason::HeightExceedsShelf);
    });

    it('places products from later blocks even when all of an earlier block cannot fit', function () {
        // clearance = 10cm, TALL=16cm won't fit, SHORT=3cm will
        $section = makeSection(clearancePerShelf: 10.0, boardHeight: 4.0, numShelves: 4);
        $placer = makePlacer();
        $settings = makeSettings();

        $blocks = makeOrderedBlocks([
            makeScoredProduct(makePlacerProduct('TALL', height: 16.0), score: 90.0),
            makeScoredProduct(makePlacerProduct('SHORT', height: 3.0), score: 50.0),
        ]);

        $result = $placer->place($blocks, collect([$section]), $settings);

        $placedIds = placedProductIds($result->placedSegments);
        expect($placedIds)->toContain('SHORT');
        expect($placedIds)->not->toContain('TALL');
    });

    it('places all products when they fit within clearance', function () {
        // clearance = 30cm per shelf, products are 20cm tall → all fit
        $section = makeSection(clearancePerShelf: 30.0, width: 200.0, numShelves: 4, boardHeight: 4.0);
        $placer = makePlacer();
        $settings = makeSettings();

        $blocks = makeOrderedBlocks([
            makeScoredProduct(makePlacerProduct('P1', width: 10.0, height: 20.0), score: 100.0),
            makeScoredProduct(makePlacerProduct('P2', width: 10.0, height: 20.0), score: 90.0),
            makeScoredProduct(makePlacerProduct('P3', width: 10.0, height: 20.0), score: 80.0),
            makeScoredProduct(makePlacerProduct('P4', width: 10.0, height: 20.0), score: 70.0),
        ]);

        $result = $placer->place($blocks, collect([$section]), $settings);

        expect($result->placedSegments)->toHaveCount(4)
            ->and($result->rejectedProducts)->toBeEmpty();
    });

    it('returns empty collection when there are no sections', function () {
        $placer = makePlacer();
        $settings = makeSettings();

        $blocks = makeOrderedBlocks([
            makeScoredProduct(makePlacerProduct('P1')),
        ]);

        $result = $placer->place($blocks, collect(), $settings);

        expect($result->placedSegments)->toBeEmpty()
            ->and($result->rejectedProducts)->toHaveCount(1);
    });

    it('keeps a fitting block whole in one section', function () {
        $section = makeSection(clearancePerShelf: 50.0, width: 100.0, numShelves: 1);
        $placer = makePlacer();

        $blocks = makeOrderedBlockFromProducts([
            makeScoredProduct(makePlacerProduct('P01', width: 10.0, height: 20.0)),
            makeScoredProduct(makePlacerProduct('P02', width: 10.0, height: 20.0)),
            makeScoredProduct(makePlacerProduct('P03', width: 10.0, height: 20.0)),
        ]);

        $result = $placer->place($blocks, collect([$section]), makeSettings());

        expect($result->placedSegments)->toHaveCount(3)
            ->and($result->rejectedProducts)->toBeEmpty()
            ->and($result->placedSegments->pluck('sectionId')->unique())->toHaveCount(1);
    });

    it('splits a large block into contiguous runs across adjacent sections', function () {
        $sections = collect([
            makeSection(clearancePerShelf: 50.0, width: 140.0, numShelves: 1),
            makeSection(clearancePerShelf: 50.0, width: 60.0, numShelves: 1),
        ]);
        $products = collect(range(1, 20))
            ->map(fn (int $index) => makeScoredProduct(makePlacerProduct(sprintf('P%02d', $index), width: 10.0, height: 20.0)))
            ->all();

        $result = makePlacer()->place(makeOrderedBlockFromProducts($products), $sections, makeSettings());

        expect($result->placedSegments)->toHaveCount(20)
            ->and($result->rejectedProducts)->toBeEmpty()
            ->and(placedProductIds($result->placedSegments))->toBe(array_map(fn (int $index) => sprintf('P%02d', $index), range(1, 20)))
            ->and($result->placedSegments->where('sectionId', $sections[0]->id))->toHaveCount(14)
            ->and($result->placedSegments->where('sectionId', $sections[1]->id))->toHaveCount(6);
    });

    it('records remaining products as horizontal-space rejections when adjacent sections run out', function () {
        $sections = collect([
            makeSection(clearancePerShelf: 50.0, width: 140.0, numShelves: 1),
            makeSection(clearancePerShelf: 50.0, width: 40.0, numShelves: 1),
        ]);
        $products = collect(range(1, 20))
            ->map(fn (int $index) => makeScoredProduct(makePlacerProduct(sprintf('P%02d', $index), width: 10.0, height: 20.0)))
            ->all();

        $result = makePlacer()->place(makeOrderedBlockFromProducts($products), $sections, makeSettings());

        expect($result->placedSegments)->toHaveCount(18)
            ->and($result->rejectedProducts)->toHaveCount(2)
            ->and($result->rejectedProducts->pluck('reason')->unique()->all())->toBe([PlacementFailureReason::NoHorizontalSpace])
            ->and(placedProductIds($result->placedSegments))->toBe(array_map(fn (int $index) => sprintf('P%02d', $index), range(1, 18)));
    });

    it('rejects products taller than the maximum gondola clearance before placement', function () {
        $section = makeSection(clearancePerShelf: 30.0, width: 100.0, numShelves: 2);
        $product = makeScoredProduct(makePlacerProduct('TALL', width: 10.0, height: 38.0));

        $result = makePlacer()->place(makeOrderedBlockFromProducts([$product]), collect([$section]), makeSettings());

        expect($result->placedSegments)->toBeEmpty()
            ->and($result->rejectedProducts)->toHaveCount(1)
            ->and($result->rejectedProducts->first()['reason'])->toBe(PlacementFailureReason::HeightExceedsShelf);
    });
});
