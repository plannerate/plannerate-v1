<?php

use App\Models\PlanogramTemplateSlot;
use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\Placement\GreedyShelfPlacer;
use App\Services\AutoPlanogram\Placement\TemplatePlacementEngine;
use App\Services\AutoPlanogram\ProductSizeResolver;
use App\Services\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── helpers ──────────────────────────────────────────────────────────────────

function makeEngine(?array $descendantsCache = null): TemplatePlacementEngine
{
    $engine = new TemplatePlacementEngine(
        new ProductWidthResolver,
        new ProductSizeResolver,
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

function makeTemplateSettings(Collection $products): PlacementSettings
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

    $result = callFindCandidates($engine, $slot, makeTemplateSettings(collect([$product])));

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

    $result = callFindCandidates($engine, $slot, makeTemplateSettings(collect([$productParent, $productChild])));

    expect($result)->toHaveCount(2);
});

test('findCandidates exclui produto de categoria diferente', function (): void {
    $slotCatId = (string) Str::ulid();
    $otherCatId = (string) Str::ulid();
    $engine = makeEngine([$slotCatId => [$slotCatId]]);

    $product = makeProduct($otherCatId);
    $slot = makeSlot($slotCatId);

    $result = callFindCandidates($engine, $slot, makeTemplateSettings(collect([$product])));

    expect($result)->toBeEmpty();
});

test('findCandidates exclui produto draft mesmo com category_id correto', function (): void {
    $catId = (string) Str::ulid();
    $engine = makeEngine([$catId => [$catId]]);

    $product = makeProduct($catId, 'draft');
    $slot = makeSlot($catId);

    $result = callFindCandidates($engine, $slot, makeTemplateSettings(collect([$product])));

    expect($result)->toBeEmpty();
});

test('findCandidates inclui produto synced com category_id correto', function (): void {
    $catId = (string) Str::ulid();
    $engine = makeEngine([$catId => [$catId]]);

    $product = makeProduct($catId, 'synced');
    $slot = makeSlot($catId);

    $result = callFindCandidates($engine, $slot, makeTemplateSettings(collect([$product])));

    expect($result)->toHaveCount(1);
});

test('findCandidates retorna vazio quando slot sem category_id', function (): void {
    $engine = makeEngine([]);

    $product = makeProduct((string) Str::ulid());
    $slot = makeSlot(null);

    $result = callFindCandidates($engine, $slot, makeTemplateSettings(collect([$product])));

    expect($result)->toBeEmpty();
});

test('findCandidates retorna vazio quando não há produtos', function (): void {
    $catId = (string) Str::ulid();
    $engine = makeEngine([$catId => [$catId]]);

    $slot = makeSlot($catId);

    $result = callFindCandidates($engine, $slot, makeTemplateSettings(collect()));

    expect($result)->toBeEmpty();
});

test('findCandidates exclui produto já posicionado em slot anterior da mesma categoria', function (): void {
    $catId = (string) Str::ulid();
    $engine = makeEngine([$catId => [$catId]]);

    $p1 = makeProduct($catId);
    $p2 = makeProduct($catId);
    $slot = makeSlot($catId);

    // Simula p1 já posicionado por um slot anterior
    $ref = new ReflectionProperty($engine, 'globalPlacedProductIds');
    $ref->setAccessible(true);
    $ref->setValue($engine, [$p1->id => true]);

    $result = callFindCandidates($engine, $slot, makeTemplateSettings(collect([$p1, $p2])));

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($p2->id);
});

// ── PlacementResult: campos de descasamento de módulos ───────────────────────

use App\Enums\FacingExpansion;
use App\Services\AutoPlanogram\DTO\PlacementResult;

test('PlacementResult expõe modulesMismatch false por padrão', function (): void {
    $result = new PlacementResult(collect(), collect());

    expect($result->modulesMismatch)->toBeFalse()
        ->and($result->templateModules)->toBe(0)
        ->and($result->gondolaModules)->toBe(0)
        ->and($result->subtemplateId)->toBeNull();
});

test('PlacementResult expõe modulesMismatch true quando gondola tem mais módulos', function (): void {
    $result = new PlacementResult(
        placedSegments: collect(),
        rejectedProducts: collect(),
        slotAnalysis: [],
        modulesMismatch: true,
        templateModules: 2,
        gondolaModules: 4,
        subtemplateId: 'sub-abc',
    );

    expect($result->modulesMismatch)->toBeTrue()
        ->and($result->templateModules)->toBe(2)
        ->and($result->gondolaModules)->toBe(4)
        ->and($result->subtemplateId)->toBe('sub-abc');
});

test('cache de descendentes é reutilizado: getDescendantIds chamado uma vez por category_id único', function (): void {
    $catId = (string) Str::ulid();

    // Pre-popula o cache → getDescendantIds NUNCA será chamado
    $engine = makeEngine([$catId => [$catId]]);

    $ref = new ReflectionProperty($engine, 'descendantsCache');
    $ref->setAccessible(true);

    $product = makeProduct($catId);
    $slot = makeSlot($catId);
    $settings = makeTemplateSettings(collect([$product]));

    callFindCandidates($engine, $slot, $settings);
    callFindCandidates($engine, $slot, $settings);

    // Cache continua com apenas uma entrada
    expect($ref->getValue($engine))->toHaveKey($catId)
        ->and($ref->getValue($engine))->toHaveCount(1);
});

test('expansionOrder TargetStock: maior déficit recebe facing extra primeiro', function (): void {
    $engine = makeEngine();

    // Produto A: target=100, current=90 → déficit=10
    $productA = makeProduct('cat1');
    $productA->current_stock = 90.0;

    // Produto B: target=100, current=20 → déficit=80 (deve ser o primeiro)
    $productB = makeProduct('cat1');
    $productB->current_stock = 20.0;

    // Produto C: sem target → vai para o fim
    $productC = makeProduct('cat1');
    $productC->current_stock = 50.0;

    $placedItems = [
        0 => ['product' => $productA, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 0],
        1 => ['product' => $productB, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 1],
        2 => ['product' => $productC, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 2],
    ];

    // Injetar targetStockMap via reflection
    $mapRef = new ReflectionProperty($engine, 'targetStockMap');
    $mapRef->setAccessible(true);
    $mapRef->setValue($engine, [
        $productA->id => 100.0,
        $productB->id => 100.0,
        // productC sem entrada → vai para o fim
    ]);

    $method = new ReflectionMethod($engine, 'expansionOrder');
    $method->setAccessible(true);

    $order = $method->invoke($engine, $placedItems, FacingExpansion::TargetStock);

    // Esperado: B primeiro (déficit 80), depois A (déficit 10), depois C (sem target)
    expect($order[0])->toBe(1)
        ->and($order[1])->toBe(0)
        ->and($order[2])->toBe(2);
});
