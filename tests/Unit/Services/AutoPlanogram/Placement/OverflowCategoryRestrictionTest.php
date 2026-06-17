<?php

/**
 * Testes da restrição por categoria no overflow pass do TemplatePlacementEngine.
 *
 * O overflow reposiciona produtos rejeitados por falta de espaço, mas SOMENTE em
 * prateleiras cujos slots pertençam à mesma categoria (ou descendente). Garante que
 * a geração por template nunca misture categorias distintas — inclusive categorias
 * homônimas (mesmo nome, category_id diferente), tratadas como separadas por ID.
 */

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedLayer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\GreedyShelfPlacer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\TemplatePlacementEngine;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductOrderingService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductSizeResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Enums\PlacementFailureReason;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── helpers ───────────────────────────────────────────────────────────────────

function makeOverflowEngine(array $descendantsCache = []): TemplatePlacementEngine
{
    $engine = new TemplatePlacementEngine(
        new ProductWidthResolver,
        new ProductSizeResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
        new ProductOrderingService(new ProductSizeResolver),
    );

    $ref = new ReflectionProperty($engine, 'descendantsCache');
    $ref->setAccessible(true);
    $ref->setValue($engine, $descendantsCache);

    return $engine;
}

function makeOverflowProduct(string $categoryId, float $width = 10.0): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = "Produto {$categoryId}";
    $product->ean = '7890000000000';
    $product->width = $width;
    $product->category_id = $categoryId;
    $product->status = 'published';

    return $product;
}

function makeOverflowSection(float $width, string $shelfId): Section
{
    $shelf = new Shelf;
    $shelf->id = $shelfId;
    $shelf->shelf_position = 0;
    $shelf->shelf_height = 0.0;

    $section = new Section;
    $section->id = (string) Str::ulid();
    $section->width = $width;
    $section->cremalheira_width = 0.0;
    $section->setRelation('shelves', collect([$shelf]));

    return $section;
}

/** Segmento já posicionado que ocupa espaço numa prateleira. */
function makeOccupyingSegment(Section $section, string $shelfId, int $width): PlacedSegment
{
    return new PlacedSegment(
        sectionId: $section->getKey(),
        shelfId: $shelfId,
        ordering: 0,
        position: 0,
        width: $width,
        distributedWidth: $width,
        layers: collect([new PlacedLayer(productId: (string) Str::ulid(), ean: '', quantity: 1, height: 1)]),
    );
}

function callPlaceOverflow(
    TemplatePlacementEngine $engine,
    Collection $placed,
    Collection $rejected,
    Collection $sections,
    array $allowedCategoriesByShelf,
): array {
    $ref = new ReflectionMethod($engine, 'placeOverflow');
    $ref->setAccessible(true);

    return $ref->invoke($engine, $placed, $rejected, $sections, $allowedCategoriesByShelf);
}

// ── testes ────────────────────────────────────────────────────────────────────

test('overflow NÃO realoca produto em prateleira de outra categoria', function (): void {
    $catA = (string) Str::ulid();
    $catB = (string) Str::ulid();
    $engine = makeOverflowEngine([$catA => [$catA], $catB => [$catB]]);

    $shelfA = (string) Str::ulid();
    $shelfB = (string) Str::ulid();

    // Prateleira A (catA): quase cheia (95/100). Prateleira B (catB): vazia.
    $sectionA = makeOverflowSection(100.0, $shelfA);
    $sectionB = makeOverflowSection(100.0, $shelfB);
    $placed = collect([makeOccupyingSegment($sectionA, $shelfA, 95)]);

    // Produto catA rejeitado por espaço — caberia em B, mas B é de outra categoria.
    $product = makeOverflowProduct($catA, 10.0);
    $rejected = collect([[
        'product' => $product,
        'reason' => PlacementFailureReason::NoHorizontalSpace,
        'slot_id' => 'slot-a',
    ]]);

    [$resultPlaced, $resultRejected] = callPlaceOverflow(
        $engine,
        $placed,
        $rejected,
        collect([$sectionA, $sectionB]),
        [$shelfA => [$catA => true], $shelfB => [$catB => true]],
    );

    // Nada novo posicionado; produto permanece rejeitado (não invadiu a prateleira B).
    expect($resultPlaced)->toHaveCount(1)
        ->and($resultRejected->pluck('product.id'))->toContain($product->id);
});

test('overflow realoca produto em outra prateleira da MESMA categoria', function (): void {
    $catA = (string) Str::ulid();
    $engine = makeOverflowEngine([$catA => [$catA]]);

    $shelfFull = (string) Str::ulid();
    $shelfFree = (string) Str::ulid();

    // Duas prateleiras catA: uma cheia, outra com espaço livre.
    $sectionFull = makeOverflowSection(100.0, $shelfFull);
    $sectionFree = makeOverflowSection(100.0, $shelfFree);
    $placed = collect([makeOccupyingSegment($sectionFull, $shelfFull, 95)]);

    $product = makeOverflowProduct($catA, 10.0);
    $rejected = collect([[
        'product' => $product,
        'reason' => PlacementFailureReason::NoHorizontalSpace,
        'slot_id' => 'slot-full',
    ]]);

    [$resultPlaced, $resultRejected] = callPlaceOverflow(
        $engine,
        $placed,
        $rejected,
        collect([$sectionFull, $sectionFree]),
        [$shelfFull => [$catA => true], $shelfFree => [$catA => true]],
    );

    // Produto realocado na prateleira livre da própria categoria.
    expect($resultPlaced)->toHaveCount(2)
        ->and($resultRejected)->toBeEmpty()
        ->and($resultPlaced->last()->shelfId)->toBe($shelfFree);
});

test('overflow realoca produto de categoria descendente na prateleira do pai', function (): void {
    $parent = (string) Str::ulid();
    $child = (string) Str::ulid();
    // O slot é do pai; sua prateleira aceita pai + descendentes.
    $engine = makeOverflowEngine([$parent => [$parent, $child]]);

    $shelfFull = (string) Str::ulid();
    $shelfFree = (string) Str::ulid();

    $sectionFull = makeOverflowSection(100.0, $shelfFull);
    $sectionFree = makeOverflowSection(100.0, $shelfFree);
    $placed = collect([makeOccupyingSegment($sectionFull, $shelfFull, 95)]);

    // Produto da subcategoria (child) rejeitado por espaço.
    $product = makeOverflowProduct($child, 10.0);
    $rejected = collect([[
        'product' => $product,
        'reason' => PlacementFailureReason::NoHorizontalSpace,
        'slot_id' => 'slot-parent',
    ]]);

    [$resultPlaced, $resultRejected] = callPlaceOverflow(
        $engine,
        $placed,
        $rejected,
        collect([$sectionFull, $sectionFree]),
        [$shelfFull => [$parent => true, $child => true], $shelfFree => [$parent => true, $child => true]],
    );

    expect($resultPlaced)->toHaveCount(2)
        ->and($resultRejected)->toBeEmpty();
});

test('buildAllowedCategoriesByShelf mapeia prateleira para categoria do slot e descendentes', function (): void {
    $catA = (string) Str::ulid();
    $childA = (string) Str::ulid();
    $catB = (string) Str::ulid();
    $engine = makeOverflowEngine([$catA => [$catA, $childA], $catB => [$catB]]);

    $shelfTop = (string) Str::ulid();
    $shelfBottom = (string) Str::ulid();

    // Seção com 2 prateleiras: shelf_order 2 = topo (índice 0), shelf_order 1 = chão (índice 1).
    $top = new Shelf;
    $top->id = $shelfTop;
    $top->shelf_position = 0;
    $bottom = new Shelf;
    $bottom->id = $shelfBottom;
    $bottom->shelf_position = 50;

    $section = new Section;
    $section->id = (string) Str::ulid();
    $section->width = 100.0;
    $section->cremalheira_width = 0.0;
    $section->setRelation('shelves', collect([$top, $bottom]));

    // Slot catA no topo (shelf_order 2), slot catB no chão (shelf_order 1).
    $slotTop = new PlanogramTemplateSlot;
    $slotTop->category_id = $catA;
    $slotTop->module_number = 1;
    $slotTop->shelf_order = 2;

    $slotBottom = new PlanogramTemplateSlot;
    $slotBottom->category_id = $catB;
    $slotBottom->module_number = 1;
    $slotBottom->shelf_order = 1;

    $ref = new ReflectionMethod($engine, 'buildAllowedCategoriesByShelf');
    $ref->setAccessible(true);
    $map = $ref->invoke($engine, collect([$slotTop, $slotBottom]), collect([$section]));

    expect($map[$shelfTop])->toBe([$catA => true, $childA => true])
        ->and($map[$shelfBottom])->toBe([$catB => true]);
});
