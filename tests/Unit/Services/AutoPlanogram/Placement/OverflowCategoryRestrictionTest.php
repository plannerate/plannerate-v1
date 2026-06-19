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

function makeOverflowEngine(array $descendantsCache = [], array $targetStockMap = []): TemplatePlacementEngine
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

    if ($targetStockMap !== []) {
        $mapRef = new ReflectionProperty($engine, 'targetStockMap');
        $mapRef->setAccessible(true);
        $mapRef->setValue($engine, $targetStockMap);
    }

    return $engine;
}

function makeOverflowProduct(string $categoryId, float $width = 10.0, ?float $depth = null): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = "Produto {$categoryId}";
    $product->ean = '7890000000000';
    $product->width = $width;
    $product->depth = $depth;
    $product->category_id = $categoryId;
    $product->status = 'published';

    return $product;
}

function makeOverflowSection(float $width, string $shelfId, float $shelfDepth = 0.0): Section
{
    $shelf = new Shelf;
    $shelf->id = $shelfId;
    $shelf->shelf_position = 0;
    $shelf->shelf_height = 0.0;
    $shelf->shelf_depth = $shelfDepth;

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

test('overflow ocupa prateleira sobrando (sem slot) dedicando-a à categoria', function (): void {
    $catA = (string) Str::ulid();
    $engine = makeOverflowEngine([$catA => [$catA]]);

    $ownShelf = (string) Str::ulid();
    $leftoverShelf = (string) Str::ulid();

    // Prateleira da categoria cheia; prateleira sobrando (sem slot) vazia.
    $sectionOwn = makeOverflowSection(100.0, $ownShelf);
    $sectionLeftover = makeOverflowSection(100.0, $leftoverShelf);
    $placed = collect([makeOccupyingSegment($sectionOwn, $ownShelf, 95)]);

    $product = makeOverflowProduct($catA, 10.0);
    $rejected = collect([[
        'product' => $product,
        'reason' => PlacementFailureReason::NoHorizontalSpace,
        'slot_id' => 'slot-own',
    ]]);

    // leftoverShelf NÃO está no mapa → prateleira sem slot designado.
    [$resultPlaced, $resultRejected] = callPlaceOverflow(
        $engine,
        $placed,
        $rejected,
        collect([$sectionOwn, $sectionLeftover]),
        [$ownShelf => [$catA => true]],
    );

    expect($resultRejected)->toBeEmpty()
        ->and($resultPlaced->last()->shelfId)->toBe($leftoverShelf);
});

test('overflow não mistura categorias numa prateleira sobrando reivindicada', function (): void {
    $catA = (string) Str::ulid();
    $catB = (string) Str::ulid();
    $engine = makeOverflowEngine([$catA => [$catA], $catB => [$catB]]);

    $leftoverShelf = (string) Str::ulid();
    $section = makeOverflowSection(100.0, $leftoverShelf); // única prateleira, vazia e sem slot

    $pa = makeOverflowProduct($catA, 30.0);
    $pb = makeOverflowProduct($catB, 30.0);
    $rejected = collect([
        ['product' => $pa, 'reason' => PlacementFailureReason::NoHorizontalSpace, 'slot_id' => 'slot-a'],
        ['product' => $pb, 'reason' => PlacementFailureReason::NoHorizontalSpace, 'slot_id' => 'slot-b'],
    ]);

    // Sem slots designados em nenhuma prateleira.
    [$resultPlaced, $resultRejected] = callPlaceOverflow(
        $engine,
        collect(),
        $rejected,
        collect([$section]),
        [],
    );

    // A primeira categoria reivindica a prateleira; a outra NÃO entra na mesma (sem mistura).
    expect($resultPlaced)->toHaveCount(1)
        ->and($resultPlaced->first()->shelfId)->toBe($leftoverShelf)
        ->and($resultRejected->pluck('product.id'))->toContain($pb->id);
});

test('overflow preenche a mesma prateleira sobrando com vários produtos da MESMA categoria', function (): void {
    $catA = (string) Str::ulid();
    $engine = makeOverflowEngine([$catA => [$catA]]);

    $leftoverShelf = (string) Str::ulid();
    $section = makeOverflowSection(100.0, $leftoverShelf);

    $p1 = makeOverflowProduct($catA, 30.0);
    $p2 = makeOverflowProduct($catA, 30.0);
    $rejected = collect([
        ['product' => $p1, 'reason' => PlacementFailureReason::NoHorizontalSpace, 'slot_id' => 'slot-a'],
        ['product' => $p2, 'reason' => PlacementFailureReason::NoHorizontalSpace, 'slot_id' => 'slot-a'],
    ]);

    [$resultPlaced, $resultRejected] = callPlaceOverflow(
        $engine,
        collect(),
        $rejected,
        collect([$section]),
        [],
    );

    expect($resultRejected)->toBeEmpty()
        ->and($resultPlaced)->toHaveCount(2)
        ->and($resultPlaced->every(fn ($seg) => $seg->shelfId === $leftoverShelf))->toBeTrue();
});

test('overflow expande frentes até cobrir o estoque alvo quando há espaço', function (): void {
    $catA = (string) Str::ulid();
    // depth null → 1 unidade por frente → teto = alvo (4 frentes)
    $product = makeOverflowProduct($catA, 10.0);
    $engine = makeOverflowEngine([$catA => [$catA]], [$product->id => 4.0]);

    $shelf = (string) Str::ulid();
    $section = makeOverflowSection(100.0, $shelf);

    $rejected = collect([[
        'product' => $product,
        'reason' => PlacementFailureReason::NoHorizontalSpace,
        'slot_id' => 'slot-a',
    ]]);

    [$resultPlaced, $resultRejected] = callPlaceOverflow(
        $engine,
        collect(),
        $rejected,
        collect([$section]),
        [$shelf => [$catA => true]],
    );

    expect($resultPlaced)->toHaveCount(1)
        ->and($resultRejected)->toBeEmpty()
        ->and($resultPlaced->first()->layers->first()->quantity)->toBe(4)
        ->and($resultPlaced->first()->width)->toBe(40);
});

test('overflow limita a expansão de estoque alvo ao espaço livre da prateleira', function (): void {
    $catA = (string) Str::ulid();
    $product = makeOverflowProduct($catA, 10.0);
    // Alvo alto (20 frentes), mas só sobram 30cm = 3 frentes de 10cm
    $engine = makeOverflowEngine([$catA => [$catA]], [$product->id => 20.0]);

    $shelf = (string) Str::ulid();
    $section = makeOverflowSection(100.0, $shelf);
    $placed = collect([makeOccupyingSegment($section, $shelf, 70)]);

    $rejected = collect([[
        'product' => $product,
        'reason' => PlacementFailureReason::NoHorizontalSpace,
        'slot_id' => 'slot-a',
    ]]);

    [$resultPlaced, $resultRejected] = callPlaceOverflow(
        $engine,
        $placed,
        $rejected,
        collect([$section]),
        [$shelf => [$catA => true]],
    );

    expect($resultRejected)->toBeEmpty()
        ->and($resultPlaced->last()->layers->first()->quantity)->toBe(3)
        ->and($resultPlaced->last()->width)->toBe(30);
});

test('overflow converte estoque alvo em frentes pela profundidade (caso Nordeste: alvo 12 → 4 frentes)', function (): void {
    $catA = (string) Str::ulid();
    // profundidade 10 / prateleira 40 = 4, limitado a max_facing_depth=3 → 3 un/frente
    // alvo 12 / 3 = 4 frentes
    $product = makeOverflowProduct($catA, 10.0, depth: 10.0);
    $engine = makeOverflowEngine([$catA => [$catA]], [$product->id => 12.0]);

    $shelf = (string) Str::ulid();
    $section = makeOverflowSection(100.0, $shelf, shelfDepth: 40.0);

    $rejected = collect([[
        'product' => $product,
        'reason' => PlacementFailureReason::NoHorizontalSpace,
        'slot_id' => 'slot-a',
    ]]);

    [$resultPlaced, $resultRejected] = callPlaceOverflow(
        $engine,
        collect(),
        $rejected,
        collect([$section]),
        [$shelf => [$catA => true]],
    );

    expect($resultPlaced)->toHaveCount(1)
        ->and($resultRejected)->toBeEmpty()
        ->and($resultPlaced->first()->layers->first()->quantity)->toBe(4);
});

test('overflow prioriza variedade: coloca todos os SKUs antes de expandir por estoque alvo', function (): void {
    // 4 produtos da mesma categoria, cada um com alvo que pediria 3 frentes, numa prateleira
    // que só comporta 5 frentes no total. Sem a regra de variedade, o 1º produto expandiria
    // para 3 frentes e sufocaria os demais. Com ela, todos os 4 entram (frente mínima) e a
    // expansão usa só o que sobrar.
    $catA = (string) Str::ulid();
    $shelf = (string) Str::ulid();
    $section = makeOverflowSection(100.0, $shelf); // 100cm → 5 frentes de 20cm

    $products = [];
    $targetMap = [];
    for ($i = 0; $i < 4; $i++) {
        $p = makeOverflowProduct($catA, 20.0); // depth null → 1 un/frente → cap = alvo
        $products[] = $p;
        $targetMap[$p->id] = 3.0; // cap = 3 frentes
    }

    $engine = makeOverflowEngine([$catA => [$catA]], $targetMap);

    $rejected = collect(array_map(fn ($p) => [
        'product' => $p,
        'reason' => PlacementFailureReason::NoHorizontalSpace,
        'slot_id' => 'slot-a',
    ], $products));

    [$resultPlaced, $resultRejected] = callPlaceOverflow(
        $engine,
        collect(),
        $rejected,
        collect([$section]),
        [$shelf => [$catA => true]],
    );

    // Todos os 4 SKUs posicionados (variedade preservada), nenhum rejeitado.
    $placedProductIds = $resultPlaced->flatMap(fn ($seg) => $seg->layers->map(fn ($l) => $l->productId))->unique();
    expect($resultRejected)->toBeEmpty()
        ->and($placedProductIds)->toHaveCount(4)
        // Espaço sobrante (100 − 4×20 = 20) vira no máximo 1 frente extra: nenhum produto monopoliza.
        ->and($resultPlaced->max(fn ($seg) => $seg->layers->first()->quantity))->toBe(2);
});

test('overflow mantém a frente mínima quando o produto não tem estoque alvo', function (): void {
    $catA = (string) Str::ulid();
    $product = makeOverflowProduct($catA, 10.0);
    // Sem targetStockMap → sem expansão
    $engine = makeOverflowEngine([$catA => [$catA]]);

    $shelf = (string) Str::ulid();
    $section = makeOverflowSection(100.0, $shelf);

    $rejected = collect([[
        'product' => $product,
        'reason' => PlacementFailureReason::NoHorizontalSpace,
        'slot_id' => 'slot-a',
    ]]);

    [$resultPlaced, $resultRejected] = callPlaceOverflow(
        $engine,
        collect(),
        $rejected,
        collect([$section]),
        [$shelf => [$catA => true]],
    );

    expect($resultPlaced)->toHaveCount(1)
        ->and($resultRejected)->toBeEmpty()
        ->and($resultPlaced->first()->layers->first()->quantity)->toBe(1)
        ->and($resultPlaced->first()->width)->toBe(10);
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
