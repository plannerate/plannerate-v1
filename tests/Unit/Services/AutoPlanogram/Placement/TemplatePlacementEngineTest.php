<?php

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementSettings;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\GreedyShelfPlacer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\TemplatePlacementEngine;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductOrderingService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductSizeResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── helpers ──────────────────────────────────────────────────────────────────

function makeEngine(?array $descendantsCache = null): TemplatePlacementEngine
{
    $engine = new TemplatePlacementEngine(
        new ProductWidthResolver,
        new ProductSizeResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
        new ProductOrderingService(new ProductSizeResolver),
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

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementResult;
use Callcocam\LaravelRaptorPlannerate\Enums\BrandExposure;
use Callcocam\LaravelRaptorPlannerate\Enums\FacingExpansion;
use Callcocam\LaravelRaptorPlannerate\Enums\PriceOrder;
use Callcocam\LaravelRaptorPlannerate\Enums\SizeOrder;

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

// ── visual_criteria: cascade sort ─────────────────────────────────────────────

/**
 * Chama orderCandidates (private) via reflection, sem seção/prateleira (zone = neutral).
 */
function callOrderCandidates(
    TemplatePlacementEngine $engine,
    Collection $products,
    PlanogramTemplateSlot $slot,
): Collection {
    $ref = new ReflectionMethod($engine, 'orderCandidates');
    $ref->setAccessible(true);

    return $ref->invoke($engine, $products, $slot, null, null);
}

/**
 * Cria um slot configurado com campos legado e visual_criteria opcionais.
 *
 * @param  array{key: string, direction: string}[]|null  $visualCriteria
 */
function makeOrderSlot(
    PriceOrder $priceOrder = PriceOrder::None,
    SizeOrder $sizeOrder = SizeOrder::None,
    BrandExposure $brandExposure = BrandExposure::Horizontal,
    ?array $visualCriteria = null,
): PlanogramTemplateSlot {
    $slot = new PlanogramTemplateSlot;
    $slot->price_order = $priceOrder;
    $slot->size_order = $sizeOrder;
    $slot->brand_exposure = $brandExposure;
    $slot->visual_criteria = $visualCriteria;

    return $slot;
}

test('visual_criteria null usa comportamento legado: price_order desc', function (): void {
    $engine = makeEngine();

    $p1 = makeProduct('cat1');
    $p1->price = 5.0;
    $p2 = makeProduct('cat1');
    $p2->price = 10.0;

    $slot = makeOrderSlot(priceOrder: PriceOrder::Desc, visualCriteria: null);
    $result = callOrderCandidates($engine, collect([$p1, $p2]), $slot);

    // legado price desc: p2 (10) primeiro
    expect($result->first()->price)->toBe(10.0);
});

test('visual_criteria vazio não altera a ordem dos produtos', function (): void {
    $engine = makeEngine();

    $p1 = makeProduct('cat1');
    $p1->price = 5.0;
    $p2 = makeProduct('cat1');
    $p2->price = 10.0;

    $slot = makeOrderSlot(visualCriteria: []);
    $result = callOrderCandidates($engine, collect([$p1, $p2]), $slot);

    // sem critérios: ordem original preservada
    expect($result->first()->id)->toBe($p1->id);
});

test('visual_criteria preco desc ordena por preço decrescente', function (): void {
    $engine = makeEngine();

    $p1 = makeProduct('cat1');
    $p1->price = 5.0;
    $p2 = makeProduct('cat1');
    $p2->price = 10.0;
    $p3 = makeProduct('cat1');
    $p3->price = 2.0;

    $slot = makeOrderSlot(
        priceOrder: PriceOrder::None, // ignorado quando visual_criteria set
        visualCriteria: [['key' => 'preco', 'direction' => 'desc']],
    );
    $result = callOrderCandidates($engine, collect([$p1, $p2, $p3]), $slot);

    expect($result->pluck('price')->values()->all())->toBe([10.0, 5.0, 2.0]);
});

test('visual_criteria preco asc ordena por preço crescente', function (): void {
    $engine = makeEngine();

    $p1 = makeProduct('cat1');
    $p1->price = 5.0;
    $p2 = makeProduct('cat1');
    $p2->price = 10.0;
    $p3 = makeProduct('cat1');
    $p3->price = 2.0;

    $slot = makeOrderSlot(visualCriteria: [['key' => 'preco', 'direction' => 'asc']]);
    $result = callOrderCandidates($engine, collect([$p1, $p2, $p3]), $slot);

    expect($result->pluck('price')->values()->all())->toBe([2.0, 5.0, 10.0]);
});

test('visual_criteria tamanho asc ordena por tamanho crescente (packaging_content)', function (): void {
    $engine = makeEngine();

    // ProductSizeResolver usa packaging_content como fonte primária
    $pSmall = makeProduct('cat1');
    $pSmall->packaging_content = '500ml'; // 0.5 kg
    $pBig = makeProduct('cat1');
    $pBig->packaging_content = '2000ml'; // 2.0 kg
    $pMed = makeProduct('cat1');
    $pMed->packaging_content = '1000ml'; // 1.0 kg

    $slot = makeOrderSlot(visualCriteria: [['key' => 'tamanho', 'direction' => 'asc']]);
    $result = callOrderCandidates($engine, collect([$pBig, $pMed, $pSmall]), $slot);

    // crescente: small(0.5), med(1.0), big(2.0)
    expect($result->pluck('packaging_content')->values()->all())->toBe(['500ml', '1000ml', '2000ml']);
});

test('visual_criteria cascata: preco desc como primário e tamanho asc como secundário', function (): void {
    $engine = makeEngine();

    // Dois produtos com mesmo preço (5.0), tamanho diferente → tamanho (packaging_content) decide
    $pA = makeProduct('cat1');
    $pA->price = 5.0;
    $pA->packaging_content = '2000ml'; // tamanho grande

    $pB = makeProduct('cat1');
    $pB->price = 5.0;
    $pB->packaging_content = '500ml'; // tamanho pequeno

    // Produto mais caro — preço decide
    $pC = makeProduct('cat1');
    $pC->price = 10.0;
    $pC->packaging_content = '100ml';

    $slot = makeOrderSlot(visualCriteria: [
        ['key' => 'preco', 'direction' => 'desc'],
        ['key' => 'tamanho', 'direction' => 'asc'],
    ]);
    $result = callOrderCandidates($engine, collect([$pA, $pB, $pC]), $slot);

    // pC vem primeiro (preço 10), depois pB (preço 5, tamanho 0.5 < 2.0), depois pA (preço 5, tamanho 2.0)
    expect($result->pluck('id')->values()->all())->toBe([$pC->id, $pB->id, $pA->id]);
});

test('visual_criteria score_abc asc ordena A antes de B antes de C', function (): void {
    $engine = makeEngine();

    $pA = makeProduct('cat1');
    $pB = makeProduct('cat1');
    $pC = makeProduct('cat1');

    // Injetar abcClassMap
    $mapRef = new ReflectionProperty($engine, 'abcClassMap');
    $mapRef->setAccessible(true);
    $mapRef->setValue($engine, [
        $pA->id => 'A',
        $pB->id => 'B',
        $pC->id => 'C',
    ]);

    $slot = makeOrderSlot(visualCriteria: [['key' => 'score_abc', 'direction' => 'asc']]);
    // Passar em ordem C, B, A — deve sair A, B, C
    $result = callOrderCandidates($engine, collect([$pC, $pB, $pA]), $slot);

    $abcResult = $result->map(fn ($p) => ['A' => 'A', 'B' => 'B', 'C' => 'C'][$p->id === $pA->id ? 'A' : ($p->id === $pB->id ? 'B' : 'C')])->values()->all();
    expect($abcResult)->toBe(['A', 'B', 'C']);
});

test('visual_criteria margem desc ordena maior margem primeiro', function (): void {
    $engine = makeEngine();

    $pHigh = makeProduct('cat1');
    $pLow = makeProduct('cat1');

    // Injetar zoneMetricsMap
    $mapRef = new ReflectionProperty($engine, 'zoneMetricsMap');
    $mapRef->setAccessible(true);
    $mapRef->setValue($engine, [
        $pHigh->id => ['giro' => 1.0, 'margem' => 0.45],
        $pLow->id => ['giro' => 1.0, 'margem' => 0.10],
    ]);

    $slot = makeOrderSlot(visualCriteria: [['key' => 'margem', 'direction' => 'desc']]);
    $result = callOrderCandidates($engine, collect([$pLow, $pHigh]), $slot);

    expect($result->first()->id)->toBe($pHigh->id);
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
