<?php

/**
 * Testes dos limites de participação relativos (prompt 38).
 *
 * Testa max_share_per_sku, max_share_per_brand e max_share_per_subcategory
 * na expansão de frentes do TemplatePlacementEngine.
 */

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\GreedyShelfPlacer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\TemplatePlacementEngine;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductOrderingService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductSizeResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Enums\FacingExpansion;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Illuminate\Support\Str;

// ── helpers específicos deste arquivo ─────────────────────────────────────────

function makeLimitEngine(): TemplatePlacementEngine
{
    return new TemplatePlacementEngine(
        new ProductWidthResolver,
        new ProductSizeResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
        new ProductOrderingService(new ProductSizeResolver),
    );
}

/**
 * Constrói um PlanogramTemplateSlot mínimo para testes de expandFacings.
 */
function makeLimitSlot(
    int $maxFacings = 20,
    FacingExpansion $facingExpansion = FacingExpansion::Equal,
    ?int $maxSharePerSku = null,
    ?int $maxSharePerBrand = null,
    ?int $maxSharePerSubcategory = null,
): PlanogramTemplateSlot {
    $slot = new PlanogramTemplateSlot;
    $slot->max_facings = $maxFacings;
    $slot->facing_expansion = $facingExpansion;
    $slot->max_share_per_sku = $maxSharePerSku;
    $slot->max_share_per_brand = $maxSharePerBrand;
    $slot->max_share_per_subcategory = $maxSharePerSubcategory;

    return $slot;
}

/**
 * Constrói um produto com width, brand e category_id configuráveis.
 */
function makeLimitProduct(float $width = 10.0, ?string $brand = null, ?string $categoryId = null): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = 'Test product';
    $product->ean = '7890000000000';
    $product->width = $width;
    $product->brand = $brand ?? 'Marca Genérica';
    $product->category_id = $categoryId ?? (string) Str::ulid();
    $product->status = 'published';

    return $product;
}

/**
 * Chama TemplatePlacementEngine::expandFacings via reflection.
 *
 * @param  array<int, array{product: Product, facings: int, singleWidth: float, ordering: int}>  $placedItems
 * @return array{0: array<int, array{product: Product, facings: int, singleWidth: float, ordering: int}>, 1: float}
 */
function callExpandFacings(
    TemplatePlacementEngine $engine,
    array $placedItems,
    PlanogramTemplateSlot $slot,
    float $available,
    float $occupied,
): array {
    $ref = new ReflectionMethod($engine, 'expandFacings');
    $ref->setAccessible(true);

    return $ref->invoke($engine, $placedItems, $slot, $available, $occupied);
}

// ── regressão: todos os limites null → comportamento igual ao atual ────────────

test('ParticipationLimit regressão: limites null não alteram expansão', function (): void {
    $engine = makeLimitEngine();

    // 2 produtos, width=10cm cada, 1 facing inicial
    $p1 = makeLimitProduct(10.0);
    $p2 = makeLimitProduct(10.0);

    $placedItems = [
        0 => ['product' => $p1, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 0],
        1 => ['product' => $p2, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 1],
    ];

    // Slot sem nenhum limite relativo, max_facings=5
    $slot = makeLimitSlot(maxFacings: 5, facingExpansion: FacingExpansion::Equal);

    // Available=100, inicial=20 → sobra=80 para expandir
    [$result, $occupied] = callExpandFacings($engine, $placedItems, $slot, 100.0, 20.0);

    // Sem limites relativos: cada produto chega ao max_facings=5 → total=100cm
    expect($result[0]['facings'])->toBe(5)
        ->and($result[1]['facings'])->toBe(5)
        ->and($occupied)->toBe(100.0);
});

// ── max_share_per_sku ─────────────────────────────────────────────────────────

test('ParticipationLimit max_share_per_sku bloqueia SKU ao atingir % do slot', function (): void {
    $engine = makeLimitEngine();

    // 1 produto, width=10cm, available=100cm, limite de 30% → max 3 facings (30cm)
    $product = makeLimitProduct(10.0);

    $placedItems = [
        0 => ['product' => $product, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 0],
    ];

    $slot = makeLimitSlot(maxFacings: 20, facingExpansion: FacingExpansion::Equal, maxSharePerSku: 30);

    [$result, $occupied] = callExpandFacings($engine, $placedItems, $slot, 100.0, 10.0);

    // 30% de 100cm = 30cm → 3 facings de 10cm = 30% ≤ 30% → permitido
    // 4 facings = 40cm = 40% > 30% → bloqueado
    expect($result[0]['facings'])->toBe(3)
        ->and($occupied)->toBe(30.0);
});

test('ParticipationLimit max_share_per_sku: dois SKUs expandem independentemente', function (): void {
    $engine = makeLimitEngine();

    $p1 = makeLimitProduct(10.0);
    $p2 = makeLimitProduct(10.0);

    $placedItems = [
        0 => ['product' => $p1, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 0],
        1 => ['product' => $p2, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 1],
    ];

    // Limite de 30% por SKU. Cada produto pode chegar a 3 facings (30cm = 30%).
    $slot = makeLimitSlot(maxFacings: 20, facingExpansion: FacingExpansion::Equal, maxSharePerSku: 30);

    [$result, $occupied] = callExpandFacings($engine, $placedItems, $slot, 100.0, 20.0);

    expect($result[0]['facings'])->toBe(3)
        ->and($result[1]['facings'])->toBe(3)
        ->and($occupied)->toBe(60.0); // 60cm ocupados, 40cm sobram inutilizados
});

// ── max_share_per_brand ───────────────────────────────────────────────────────

test('ParticipationLimit max_share_per_brand bloqueia marca dominante ao atingir %', function (): void {
    $engine = makeLimitEngine();

    // Produto A e B da mesma marca COCA (cada width=10cm)
    // Produto C da marca PEPSI (width=10cm)
    $pA = makeLimitProduct(10.0, 'COCA');
    $pB = makeLimitProduct(10.0, 'COCA');
    $pC = makeLimitProduct(10.0, 'PEPSI');

    $placedItems = [
        0 => ['product' => $pA, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 0],
        1 => ['product' => $pB, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 1],
        2 => ['product' => $pC, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 2],
    ];

    // Limite de 30% por marca. COCA pode ocupar no máximo 30cm de 100cm.
    // PEPSI idem.
    $slot = makeLimitSlot(maxFacings: 20, facingExpansion: FacingExpansion::Equal, maxSharePerBrand: 30);

    [$result, $occupied] = callExpandFacings($engine, $placedItems, $slot, 100.0, 30.0);

    $cocaWidth = $result[0]['facings'] * 10 + $result[1]['facings'] * 10;
    $pepsiWidth = $result[2]['facings'] * 10;

    // COCA total ≤ 30cm (30%), PEPSI total ≤ 30cm (30%)
    expect($cocaWidth)->toBeLessThanOrEqual(30)
        ->and($pepsiWidth)->toBeLessThanOrEqual(30);
});

test('ParticipationLimit max_share_per_brand: sobra de espaço vai para outra marca', function (): void {
    $engine = makeLimitEngine();

    // Marca COCA dominante (2 produtos) vs. PEPSI (1 produto)
    $pCoca1 = makeLimitProduct(10.0, 'COCA');
    $pCoca2 = makeLimitProduct(10.0, 'COCA');
    $pPepsi = makeLimitProduct(10.0, 'PEPSI');

    $placedItems = [
        0 => ['product' => $pCoca1, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 0],
        1 => ['product' => $pCoca2, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 1],
        2 => ['product' => $pPepsi, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 2],
    ];

    // Limite de 30% por marca. Com max_facings=20 (sem teto absoluto relevante).
    // Sem o limite, COCA poderia dominar; com o limite, PEPSI ocupa o espaço que sobrar.
    $slot = makeLimitSlot(maxFacings: 20, facingExpansion: FacingExpansion::Equal, maxSharePerBrand: 30);

    [$result, $occupied] = callExpandFacings($engine, $placedItems, $slot, 100.0, 30.0);

    $pepsiWidth = $result[2]['facings'] * 10;
    $cocaWidth = ($result[0]['facings'] + $result[1]['facings']) * 10;

    // PEPSI deve receber pelo menos tanto espaço quanto COCA (sobra vai para quem não está bloqueado)
    expect($pepsiWidth)->toBeGreaterThanOrEqual($cocaWidth)
        ->and($cocaWidth)->toBeLessThanOrEqual(30);
});

// ── max_share_per_subcategory ─────────────────────────────────────────────────

test('ParticipationLimit max_share_per_subcategory bloqueia subcategoria ao atingir %', function (): void {
    $engine = makeLimitEngine();

    $catX = (string) Str::ulid();
    $catY = (string) Str::ulid();

    // 2 produtos de CATX, 1 produto de CATY
    $pX1 = makeLimitProduct(10.0, 'A', $catX);
    $pX2 = makeLimitProduct(10.0, 'B', $catX);
    $pY1 = makeLimitProduct(10.0, 'C', $catY);

    $placedItems = [
        0 => ['product' => $pX1, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 0],
        1 => ['product' => $pX2, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 1],
        2 => ['product' => $pY1, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 2],
    ];

    // Limite de 40% por subcategoria → CATX pode ocupar no máximo 40cm de 100cm
    $slot = makeLimitSlot(maxFacings: 20, facingExpansion: FacingExpansion::Equal, maxSharePerSubcategory: 40);

    [$result, $occupied] = callExpandFacings($engine, $placedItems, $slot, 100.0, 30.0);

    $catXWidth = ($result[0]['facings'] + $result[1]['facings']) * 10;

    expect($catXWidth)->toBeLessThanOrEqual(40);
});

test('ParticipationLimit max_share_per_subcategory: CATY expande alem de CATX bloqueada', function (): void {
    $engine = makeLimitEngine();

    $catX = (string) Str::ulid();
    $catY = (string) Str::ulid();

    $pX1 = makeLimitProduct(10.0, 'A', $catX);
    $pX2 = makeLimitProduct(10.0, 'B', $catX);
    $pY1 = makeLimitProduct(10.0, 'C', $catY);

    $placedItems = [
        0 => ['product' => $pX1, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 0],
        1 => ['product' => $pX2, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 1],
        2 => ['product' => $pY1, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 2],
    ];

    // Limite de 30% por subcategoria. CATX pode ocupar 30cm, CATY pode ocupar 30cm.
    $slot = makeLimitSlot(maxFacings: 20, facingExpansion: FacingExpansion::Equal, maxSharePerSubcategory: 30);

    [$result, $occupied] = callExpandFacings($engine, $placedItems, $slot, 100.0, 30.0);

    $catXWidth = ($result[0]['facings'] + $result[1]['facings']) * 10;

    // CATX (2 produtos dividindo 30%) fica no teto
    // O produto solo de CATY acumula mais facings individuais do que cada produto de CATX
    $maxCatXProductFacings = max($result[0]['facings'], $result[1]['facings']);

    expect($catXWidth)->toBeLessThanOrEqual(30)
        ->and($result[2]['facings'])->toBeGreaterThan($maxCatXProductFacings); // produto solo ganha mais facings
});

// ── max_facings como teto absoluto que vence ──────────────────────────────────

test('ParticipationLimit max_facings absoluto prevalece quando menor que o limite relativo', function (): void {
    $engine = makeLimitEngine();

    $product = makeLimitProduct(10.0);

    $placedItems = [
        0 => ['product' => $product, 'facings' => 1, 'singleWidth' => 10.0, 'ordering' => 0],
    ];

    // max_facings=2 e max_share_per_sku=50% → max_facings vence (2 < 5)
    $slot = makeLimitSlot(maxFacings: 2, facingExpansion: FacingExpansion::Equal, maxSharePerSku: 50);

    [$result, $occupied] = callExpandFacings($engine, $placedItems, $slot, 100.0, 10.0);

    // O teto absoluto é 2 facings
    expect($result[0]['facings'])->toBe(2);
});
