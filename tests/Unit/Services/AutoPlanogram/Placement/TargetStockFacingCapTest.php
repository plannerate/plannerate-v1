<?php

/**
 * Testes do teto de frentes por estoque alvo na expansão (Fase 2 do TemplatePlacementEngine).
 *
 * Quando o slot tem use_target_stock = true, a expansão de frentes deve parar ao atingir as
 * frentes necessárias para cobrir o estoque alvo do produto — mesmo que ainda sobre espaço.
 * Conversão: unidades_por_frente = floor(shelf_depth / product_depth) (mín. 1);
 * teto = ceil(estoque_alvo / unidades_por_frente).
 */

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\GreedyShelfPlacer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\TemplatePlacementEngine;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductOrderingService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductSizeResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Enums\FacingExpansion;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Illuminate\Support\Str;

// ── helpers específicos deste arquivo ─────────────────────────────────────────

function makeCapEngine(): TemplatePlacementEngine
{
    return new TemplatePlacementEngine(
        new ProductWidthResolver,
        new ProductSizeResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
        new ProductOrderingService(new ProductSizeResolver),
    );
}

function makeCapSlot(
    int $maxFacings = 20,
    bool $useTargetStock = true,
    FacingExpansion $facingExpansion = FacingExpansion::Equal,
): PlanogramTemplateSlot {
    $slot = new PlanogramTemplateSlot;
    $slot->max_facings = $maxFacings;
    $slot->min_facings = 1;
    $slot->facing_expansion = $facingExpansion;
    $slot->use_target_stock = $useTargetStock;

    return $slot;
}

function makeCapProduct(float $width = 10.0, float $depth = 10.0): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = 'Test product';
    $product->ean = '7890000000000';
    $product->width = $width;
    $product->depth = $depth;
    $product->status = 'published';

    return $product;
}

function makeCapShelf(int $shelfDepth = 40): Shelf
{
    $shelf = new Shelf;
    $shelf->shelf_depth = $shelfDepth;

    return $shelf;
}

/**
 * Injeta o targetStockMap privado e chama expandFacings via reflection.
 *
 * @param  array<int, array{product: Product, facings: int, singleWidth: float, ordering: int}>  $placedItems
 * @param  array<string, float>  $targetStockMap
 * @return array{0: array<int, array>, 1: float}
 */
function callCapExpandFacings(
    TemplatePlacementEngine $engine,
    array $placedItems,
    PlanogramTemplateSlot $slot,
    float $available,
    float $occupied,
    Shelf $shelf,
    array $targetStockMap,
): array {
    $prop = new ReflectionProperty($engine, 'targetStockMap');
    $prop->setAccessible(true);
    $prop->setValue($engine, $targetStockMap);

    $ref = new ReflectionMethod($engine, 'expandFacings');
    $ref->setAccessible(true);

    return $ref->invoke($engine, $placedItems, $slot, $available, $occupied, $shelf);
}

// ── testes ────────────────────────────────────────────────────────────────────

test('teto por estoque alvo limita frentes mesmo com espaço sobrando', function (): void {
    $engine = makeCapEngine();

    // width=10, depth=20, shelf_depth=40 → unidades/frente = floor(40/20) = 2 (abaixo do teto
    // de profundidade=3, então não é suavizado). target=8 → teto = ceil(8/2) = 4 frentes
    // (em vez de ir até max_facings=10).
    $product = makeCapProduct(width: 10.0, depth: 20.0);

    $placedItems = [
        0 => ['product' => $product, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 0],
    ];

    $slot = makeCapSlot(maxFacings: 10, useTargetStock: true);
    $shelf = makeCapShelf(shelfDepth: 40);

    [$result, $occupied] = callCapExpandFacings(
        $engine, $placedItems, $slot, 200.0, 10.0, $shelf, [$product->id => 8.0]
    );

    expect($result[0]['facings'])->toBe(4)
        ->and($occupied)->toBe(40.0);
});

test('teto de profundidade suaviza a conversão para alvos baixos', function (): void {
    $engine = makeCapEngine();

    // width=12.5, depth=5, shelf_depth=40 → capacidade física = floor(40/5) = 8.
    // Sem teto de profundidade, target=6 → ceil(6/8) = 1 frente (prateleira esvazia).
    // Com max_facing_depth=3 (config padrão): unidades/frente = min(8,3) = 3 → ceil(6/3) = 2.
    config()->set('plannerate.auto_planogram.target_stock.max_facing_depth', 3);

    $product = makeCapProduct(width: 12.5, depth: 5.0);

    $placedItems = [
        0 => ['product' => $product, 'facings' => 1, 'singleWidth' => 12.5, 'ordering' => 0],
    ];

    $slot = makeCapSlot(maxFacings: 10, useTargetStock: true);
    $shelf = makeCapShelf(shelfDepth: 40);

    [$result] = callCapExpandFacings(
        $engine, $placedItems, $slot, 200.0, 12.5, $shelf, [$product->id => 6.0]
    );

    expect($result[0]['facings'])->toBe(2);
});

test('use_target_stock = false ignora o teto e expande até max_facings', function (): void {
    $engine = makeCapEngine();

    $product = makeCapProduct(width: 10.0, depth: 10.0);

    $placedItems = [
        0 => ['product' => $product, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 0],
    ];

    // Mesmo target baixo, mas flag desligado → comportamento original (max_facings=5).
    $slot = makeCapSlot(maxFacings: 5, useTargetStock: false);
    $shelf = makeCapShelf(shelfDepth: 40);

    [$result, $occupied] = callCapExpandFacings(
        $engine, $placedItems, $slot, 200.0, 10.0, $shelf, [$product->id => 8.0]
    );

    expect($result[0]['facings'])->toBe(5)
        ->and($occupied)->toBe(50.0);
});

test('produto sem profundidade válida cai para 1 unidade por frente', function (): void {
    $engine = makeCapEngine();

    // depth=0 → unidades/frente = 1; target=3 → teto = ceil(3/1) = 3.
    $product = makeCapProduct(width: 10.0, depth: 0.0);

    $placedItems = [
        0 => ['product' => $product, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 0],
    ];

    $slot = makeCapSlot(maxFacings: 10, useTargetStock: true);
    $shelf = makeCapShelf(shelfDepth: 40);

    [$result] = callCapExpandFacings(
        $engine, $placedItems, $slot, 200.0, 10.0, $shelf, [$product->id => 3.0]
    );

    expect($result[0]['facings'])->toBe(3);
});

test('produto sem estoque alvo no mapa não recebe teto', function (): void {
    $engine = makeCapEngine();

    $product = makeCapProduct(width: 10.0, depth: 10.0);

    $placedItems = [
        0 => ['product' => $product, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 0],
    ];

    $slot = makeCapSlot(maxFacings: 6, useTargetStock: true);
    $shelf = makeCapShelf(shelfDepth: 40);

    // targetStockMap vazio → sem teto → expande até max_facings=6.
    [$result] = callCapExpandFacings(
        $engine, $placedItems, $slot, 200.0, 10.0, $shelf, []
    );

    expect($result[0]['facings'])->toBe(6);
});
