<?php

use App\Models\PlanogramTemplateSlot;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\Placement\GreedyShelfPlacer;
use App\Services\AutoPlanogram\Placement\TemplatePlacementEngine;
use App\Services\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── helpers ──────────────────────────────────────────────────────────────────

function makeEngine(?array $descendantsCache = null): TemplatePlacementEngine
{
    $engine = new TemplatePlacementEngine(
        new ProductWidthResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
    );

    if ($descendantsCache !== null) {
        $ref = new ReflectionProperty($engine, 'descendantsCache');
        $ref->setAccessible(true);
        $ref->setValue($engine, $descendantsCache);
    }

    return $engine;
}

function callFindCandidates(
    TemplatePlacementEngine $engine,
    PlanogramTemplateSlot $slot,
    PlacementSettings $settings,
): Collection {
    $ref = new ReflectionMethod($engine, 'findCandidates');
    $ref->setAccessible(true);

    return $ref->invoke($engine, $slot, $settings);
}

function makeSlot(?string $categoryId): PlanogramTemplateSlot
{
    $slot = new PlanogramTemplateSlot;
    $slot->category_id = $categoryId;

    return $slot;
}

function makeProduct(string $categoryId, string $status = 'published'): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = "Produto {$categoryId}";
    $product->ean = '7890000000000';
    $product->width = 10.0;
    $product->category_id = $categoryId;
    $product->status = $status;

    return $product;
}

function makeSettings(Collection $products): PlacementSettings
{
    return new PlacementSettings(
        strategy: 'sales',
        useExistingAnalysis: false,
        startDate: null,
        endDate: null,
        templateId: 'template-test',
        products: $products,
    );
}

// ── tests ─────────────────────────────────────────────────────────────────────

test('findCandidates inclui produto com category_id igual ao slot', function (): void {
    $catId = (string) Str::ulid();
    $engine = makeEngine([$catId => [$catId]]);
    $product = makeProduct($catId);
    $slot = makeSlot($catId);

    $result = callFindCandidates($engine, $slot, makeSettings(collect([$product])));

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($product->id);
});

test('findCandidates inclui produto de categoria descendente', function (): void {
    $parentId = (string) Str::ulid();
    $childId = (string) Str::ulid();
    $engine = makeEngine([$parentId => [$parentId, $childId]]);

    $productParent = makeProduct($parentId);
    $productChild = makeProduct($childId);
    $slot = makeSlot($parentId);

    $result = callFindCandidates($engine, $slot, makeSettings(collect([$productParent, $productChild])));

    expect($result)->toHaveCount(2);
});

test('findCandidates exclui produto de categoria diferente', function (): void {
    $slotCatId = (string) Str::ulid();
    $otherCatId = (string) Str::ulid();
    $engine = makeEngine([$slotCatId => [$slotCatId]]);

    $product = makeProduct($otherCatId);
    $slot = makeSlot($slotCatId);

    $result = callFindCandidates($engine, $slot, makeSettings(collect([$product])));

    expect($result)->toBeEmpty();
});

test('findCandidates exclui produto draft mesmo com category_id correto', function (): void {
    $catId = (string) Str::ulid();
    $engine = makeEngine([$catId => [$catId]]);

    $product = makeProduct($catId, 'draft');
    $slot = makeSlot($catId);

    $result = callFindCandidates($engine, $slot, makeSettings(collect([$product])));

    expect($result)->toBeEmpty();
});

test('findCandidates inclui produto synced com category_id correto', function (): void {
    $catId = (string) Str::ulid();
    $engine = makeEngine([$catId => [$catId]]);

    $product = makeProduct($catId, 'synced');
    $slot = makeSlot($catId);

    $result = callFindCandidates($engine, $slot, makeSettings(collect([$product])));

    expect($result)->toHaveCount(1);
});

test('findCandidates retorna vazio quando slot sem category_id', function (): void {
    $engine = makeEngine([]);

    $product = makeProduct((string) Str::ulid());
    $slot = makeSlot(null);

    $result = callFindCandidates($engine, $slot, makeSettings(collect([$product])));

    expect($result)->toBeEmpty();
});

test('findCandidates retorna vazio quando não há produtos', function (): void {
    $catId = (string) Str::ulid();
    $engine = makeEngine([$catId => [$catId]]);

    $slot = makeSlot($catId);

    $result = callFindCandidates($engine, $slot, makeSettings(collect()));

    expect($result)->toBeEmpty();
});

test('cache de descendentes é reutilizado: getDescendantIds chamado uma vez por category_id único', function (): void {
    $catId = (string) Str::ulid();

    // Pre-popula o cache → getDescendantIds NUNCA será chamado
    $engine = makeEngine([$catId => [$catId]]);

    $ref = new ReflectionProperty($engine, 'descendantsCache');
    $ref->setAccessible(true);

    $product = makeProduct($catId);
    $slot = makeSlot($catId);
    $settings = makeSettings(collect([$product]));

    callFindCandidates($engine, $slot, $settings);
    callFindCandidates($engine, $slot, $settings);

    // Cache continua com apenas uma entrada
    expect($ref->getValue($engine))->toHaveKey($catId)
        ->and($ref->getValue($engine))->toHaveCount(1);
});
