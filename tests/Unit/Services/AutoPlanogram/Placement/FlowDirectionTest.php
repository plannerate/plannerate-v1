<?php

/**
 * Testes do sentido de leitura do cliente (prompt 40).
 *
 * Verifica que RightToLeft espelha as posições físicas mantendo a ordem
 * lógica dos critérios de ordenação, e que LeftToRight é um no-op.
 */

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\GreedyShelfPlacer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\TemplatePlacementEngine;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductOrderingService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductSizeResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Enums\BrandExposure;
use Callcocam\LaravelRaptorPlannerate\Enums\FacingExpansion;
use Callcocam\LaravelRaptorPlannerate\Enums\FlowDirection;
use Callcocam\LaravelRaptorPlannerate\Enums\PriceOrder;
use Callcocam\LaravelRaptorPlannerate\Enums\SizeOrder;
use Callcocam\LaravelRaptorPlannerate\Enums\SpaceFallback;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── helpers ───────────────────────────────────────────────────────────────────

function makeFlowEngine(): TemplatePlacementEngine
{
    return new TemplatePlacementEngine(
        new ProductWidthResolver,
        new ProductSizeResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
        new ProductOrderingService(new ProductSizeResolver),
    );
}

/**
 * Constrói produto com width configurável.
 */
function makeFlowProduct(float $width = 10.0, ?string $id = null): Product
{
    $product = new Product;
    $product->id = $id ?? (string) Str::ulid();
    $product->name = 'Produto Teste';
    $product->ean = '7890000000000';
    $product->width = $width;
    $product->brand = 'Marca';
    $product->category_id = (string) Str::ulid();
    $product->status = 'published';

    return $product;
}

/**
 * Constrói slot mínimo com configurações padrão (sem critérios visuais).
 */
function makeFlowSlot(
    int $minFacings = 1,
    int $maxFacings = 1,
    FacingExpansion $facingExpansion = FacingExpansion::None,
): PlanogramTemplateSlot {
    $slot = new PlanogramTemplateSlot;
    $slot->min_facings = $minFacings;
    $slot->max_facings = $maxFacings;
    $slot->price_order = PriceOrder::None;
    $slot->size_order = SizeOrder::None;
    $slot->brand_exposure = BrandExposure::Mixed;
    $slot->space_fallback = SpaceFallback::Skip;
    $slot->facing_expansion = $facingExpansion;
    $slot->visual_criteria = null;
    $slot->max_share_per_sku = null;
    $slot->max_share_per_brand = null;
    $slot->max_share_per_subcategory = null;

    return $slot;
}

/**
 * Cria Section fake com uma Shelf fake de largura configurável.
 */
function makeFlowSection(float $width = 100.0): Section
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
 * Injeta o sentido de leitura no campo privado do engine via reflection.
 */
function injectFlowDirection(TemplatePlacementEngine $engine, FlowDirection $direction): void
{
    $prop = new ReflectionProperty($engine, 'flowDirection');
    $prop->setAccessible(true);
    $prop->setValue($engine, $direction);
}

/**
 * Chama distributeInShelf via reflection e retorna apenas os PlacedSegments.
 *
 * @return Collection<int, PlacedSegment>
 */
function callDistributeInShelf(
    TemplatePlacementEngine $engine,
    Collection $products,
    Section $section,
    Shelf $shelf,
    PlanogramTemplateSlot $slot,
    float $available,
): Collection {
    $ref = new ReflectionMethod($engine, 'distributeInShelf');
    $ref->setAccessible(true);
    $result = $ref->invoke($engine, $products, $section, $shelf, $slot, $available);

    return $result['placed'];
}

// ── regressão: LeftToRight preserva posições da esquerda para direita ─────────

test('FlowDirection LeftToRight: posições começam em 0 e crescem (comportamento atual)', function (): void {
    $engine = makeFlowEngine();
    injectFlowDirection($engine, FlowDirection::LeftToRight);

    $p1 = makeFlowProduct(10.0);
    $p2 = makeFlowProduct(15.0);
    $p3 = makeFlowProduct(20.0);

    $section = makeFlowSection(100.0);
    $shelf = $section->shelves->first();
    $slot = makeFlowSlot();

    $placed = callDistributeInShelf($engine, collect([$p1, $p2, $p3]), $section, $shelf, $slot, 100.0);

    expect($placed)->toHaveCount(3);

    $positions = $placed->pluck('position')->all();
    expect($positions[0])->toBe(0);
    expect($positions[1])->toBe(10);
    expect($positions[2])->toBe(25);
});

// ── RightToLeft espelha posições ──────────────────────────────────────────────

test('FlowDirection RightToLeft: posições são espelhadas dentro da largura total usada', function (): void {
    $engine = makeFlowEngine();
    injectFlowDirection($engine, FlowDirection::RightToLeft);

    // P1 = 10cm (primeiro no fluxo → deve ficar na direita)
    // P2 = 15cm (segundo no fluxo → meio)
    // P3 = 20cm (terceiro no fluxo → deve ficar na esquerda)
    // total usado = 45cm
    $p1 = makeFlowProduct(10.0);
    $p2 = makeFlowProduct(15.0);
    $p3 = makeFlowProduct(20.0);

    $section = makeFlowSection(100.0);
    $shelf = $section->shelves->first();
    $slot = makeFlowSlot();

    $placed = callDistributeInShelf($engine, collect([$p1, $p2, $p3]), $section, $shelf, $slot, 100.0);

    expect($placed)->toHaveCount(3);

    // Total usado = 45. Espelhamento: pos_espelhada = 45 - pos_original - largura
    // P1 (pos=0, w=10) → 45 - 0 - 10 = 35 (direita)
    // P2 (pos=10, w=15) → 45 - 10 - 15 = 20
    // P3 (pos=25, w=20) → 45 - 25 - 20 = 0 (esquerda)
    $byOrdering = $placed->keyBy('ordering');

    expect($byOrdering[0]->position)->toBe(35); // P1 agora na direita
    expect($byOrdering[1]->position)->toBe(20); // P2 no meio
    expect($byOrdering[2]->position)->toBe(0);  // P3 agora na esquerda
});

// ── ordering lógico é preservado após espelhamento ────────────────────────────

test('FlowDirection RightToLeft: ordering lógico dos produtos não muda', function (): void {
    $engine = makeFlowEngine();
    injectFlowDirection($engine, FlowDirection::RightToLeft);

    $p1 = makeFlowProduct(10.0, 'id-A');
    $p2 = makeFlowProduct(10.0, 'id-B');

    $section = makeFlowSection(100.0);
    $shelf = $section->shelves->first();
    $slot = makeFlowSlot();

    $placed = callDistributeInShelf($engine, collect([$p1, $p2]), $section, $shelf, $slot, 100.0);

    // ordering 0 deve corresponder ao produto A (primeiro no fluxo), mesmo espelhado
    $first = $placed->firstWhere('ordering', 0);
    $second = $placed->firstWhere('ordering', 1);

    expect($first?->layers->first()?->productId)->toBe('id-A');
    expect($second?->layers->first()?->productId)->toBe('id-B');
});

// ── produto único: pos_espelhada = 0 ─────────────────────────────────────────

test('FlowDirection RightToLeft com um produto: posição espelhada é 0', function (): void {
    $engine = makeFlowEngine();
    injectFlowDirection($engine, FlowDirection::RightToLeft);

    $p = makeFlowProduct(10.0);
    $section = makeFlowSection(100.0);
    $shelf = $section->shelves->first();
    $slot = makeFlowSlot();

    $placed = callDistributeInShelf($engine, collect([$p]), $section, $shelf, $slot, 100.0);

    expect($placed)->toHaveCount(1)
        ->and($placed->first()->position)->toBe(0); // total=10, 10-0-10=0
});

// ── widths são preservadas após espelhamento ──────────────────────────────────

test('FlowDirection RightToLeft: widths são idênticas às do modo LeftToRight', function (): void {
    $products = collect([
        makeFlowProduct(10.0),
        makeFlowProduct(15.0),
        makeFlowProduct(20.0),
    ]);

    $section = makeFlowSection(100.0);
    $shelf = $section->shelves->first();
    $slot = makeFlowSlot();

    $ltrEngine = makeFlowEngine();
    injectFlowDirection($ltrEngine, FlowDirection::LeftToRight);
    $ltrPlaced = callDistributeInShelf($ltrEngine, $products, $section, $shelf, $slot, 100.0);

    $rtlEngine = makeFlowEngine();
    injectFlowDirection($rtlEngine, FlowDirection::RightToLeft);
    $rtlPlaced = callDistributeInShelf($rtlEngine, $products, $section, $shelf, $slot, 100.0);

    $ltrWidths = $ltrPlaced->sortBy('ordering')->pluck('width')->all();
    $rtlWidths = $rtlPlaced->sortBy('ordering')->pluck('width')->all();

    expect($ltrWidths)->toBe($rtlWidths);
});
