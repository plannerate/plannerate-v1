<?php

/**
 * Testes da rejeição por altura no TemplatePlacementEngine.
 *
 * Produtos mais altos que o vão livre da prateleira (clearance) devem ser
 * rejeitados com HeightExceedsShelf — mesma regra do GreedyShelfPlacer.
 * Clearance ausente (null) desativa a checagem (compatibilidade com gôndolas legadas).
 */

use App\Models\PlanogramTemplateSlot;
use App\Services\AutoPlanogram\Placement\GreedyShelfPlacer;
use App\Services\AutoPlanogram\Placement\TemplatePlacementEngine;
use App\Services\AutoPlanogram\ProductOrderingService;
use App\Services\AutoPlanogram\ProductSizeResolver;
use App\Services\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Enums\BrandExposure;
use Callcocam\LaravelRaptorPlannerate\Enums\FacingExpansion;
use Callcocam\LaravelRaptorPlannerate\Enums\PlacementFailureReason;
use Callcocam\LaravelRaptorPlannerate\Enums\PriceOrder;
use Callcocam\LaravelRaptorPlannerate\Enums\SizeOrder;
use Callcocam\LaravelRaptorPlannerate\Enums\SpaceFallback;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── helpers ───────────────────────────────────────────────────────────────────

function makeHeightEngine(): TemplatePlacementEngine
{
    return new TemplatePlacementEngine(
        new ProductWidthResolver,
        new ProductSizeResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
        new ProductOrderingService(new ProductSizeResolver),
    );
}

function makeHeightProduct(float $width = 10.0, ?float $height = null): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = 'Produto Altura';
    $product->ean = '7890000000000';
    $product->width = $width;
    $product->height = $height;
    $product->brand = 'Marca';
    $product->category_id = (string) Str::ulid();
    $product->status = 'published';

    return $product;
}

function makeHeightSlot(): PlanogramTemplateSlot
{
    $slot = new PlanogramTemplateSlot;
    $slot->min_facings = 1;
    $slot->max_facings = 1;
    $slot->price_order = PriceOrder::None;
    $slot->size_order = SizeOrder::None;
    $slot->brand_exposure = BrandExposure::Mixed;
    $slot->space_fallback = SpaceFallback::Skip;
    $slot->facing_expansion = FacingExpansion::None;
    $slot->visual_criteria = null;
    $slot->max_share_per_sku = null;
    $slot->max_share_per_brand = null;
    $slot->max_share_per_subcategory = null;

    return $slot;
}

function makeHeightSection(float $width = 100.0): Section
{
    $shelf = new Shelf;
    $shelf->id = (string) Str::ulid();
    $shelf->shelf_position = 0;

    $section = new Section;
    $section->id = (string) Str::ulid();
    $section->width = $width;
    $section->cremalheira_width = 0.0;
    $section->setRelation('shelves', collect([$shelf]));

    return $section;
}

/**
 * Chama distributeInShelf via reflection com clearance configurável.
 *
 * @return array{placed: Collection, rejected: Collection, placed_explanations: array}
 */
function callDistributeWithHeight(
    TemplatePlacementEngine $engine,
    Collection $products,
    Section $section,
    Shelf $shelf,
    PlanogramTemplateSlot $slot,
    float $available,
    ?float $maxProductHeight,
): array {
    $ref = new ReflectionMethod($engine, 'distributeInShelf');
    $ref->setAccessible(true);

    return $ref->invoke($engine, $products, $section, $shelf, $slot, $available, 0, $maxProductHeight);
}

// ── testes ────────────────────────────────────────────────────────────────────

test('produto mais alto que o vão é rejeitado com HeightExceedsShelf', function (): void {
    $engine = makeHeightEngine();
    $section = makeHeightSection();
    $shelf = $section->shelves->first();
    $slot = makeHeightSlot();

    $alto = makeHeightProduct(10.0, 35.0);

    $result = callDistributeWithHeight($engine, collect([$alto]), $section, $shelf, $slot, 100.0, 30.0);

    expect($result['placed'])->toBeEmpty()
        ->and($result['rejected'])->toHaveCount(1)
        ->and($result['rejected']->first()['reason'])->toBe(PlacementFailureReason::HeightExceedsShelf)
        ->and($result['rejected']->first()['product']->id)->toBe($alto->id);
});

test('produto com altura igual ou menor que o vão é posicionado', function (): void {
    $engine = makeHeightEngine();
    $section = makeHeightSection();
    $shelf = $section->shelves->first();
    $slot = makeHeightSlot();

    $igual = makeHeightProduct(10.0, 30.0);
    $menor = makeHeightProduct(10.0, 15.0);

    $result = callDistributeWithHeight($engine, collect([$igual, $menor]), $section, $shelf, $slot, 100.0, 30.0);

    expect($result['placed'])->toHaveCount(2)
        ->and($result['rejected'])->toBeEmpty();
});

test('clearance null desativa a checagem de altura (gôndola legada)', function (): void {
    $engine = makeHeightEngine();
    $section = makeHeightSection();
    $shelf = $section->shelves->first();
    $slot = makeHeightSlot();

    $alto = makeHeightProduct(10.0, 999.0);

    $result = callDistributeWithHeight($engine, collect([$alto]), $section, $shelf, $slot, 100.0, null);

    expect($result['placed'])->toHaveCount(1)
        ->and($result['rejected'])->toBeEmpty();
});

test('produto sem altura cadastrada não é rejeitado pela checagem de altura', function (): void {
    $engine = makeHeightEngine();
    $section = makeHeightSection();
    $shelf = $section->shelves->first();
    $slot = makeHeightSlot();

    $semAltura = makeHeightProduct(10.0, null);

    $result = callDistributeWithHeight($engine, collect([$semAltura]), $section, $shelf, $slot, 100.0, 30.0);

    expect($result['placed'])->toHaveCount(1)
        ->and($result['rejected'])->toBeEmpty();
});

test('mix de alturas: só os mais altos que o vão são rejeitados', function (): void {
    $engine = makeHeightEngine();
    $section = makeHeightSection();
    $shelf = $section->shelves->first();
    $slot = makeHeightSlot();

    $cabe = makeHeightProduct(10.0, 20.0);
    $naoCabe = makeHeightProduct(10.0, 40.0);
    $cabeTambem = makeHeightProduct(10.0, 29.9);

    $result = callDistributeWithHeight(
        $engine,
        collect([$cabe, $naoCabe, $cabeTambem]),
        $section,
        $shelf,
        $slot,
        100.0,
        30.0,
    );

    expect($result['placed'])->toHaveCount(2)
        ->and($result['rejected'])->toHaveCount(1)
        ->and($result['rejected']->first()['product']->id)->toBe($naoCabe->id)
        ->and($result['rejected']->first()['reason'])->toBe(PlacementFailureReason::HeightExceedsShelf);
});
