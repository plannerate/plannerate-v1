<?php

/**
 * Testes da blocagem vertical por marca (layout_orientation = vertical).
 *
 * Verifica que marcas formam COLUNAS alinhadas: mesma faixa de X em todas as
 * prateleiras do grupo, larguras proporcionais à demanda, expansão de frentes
 * confinada à coluna, sobras roteadas para o overflow e espelhamento RTL.
 */

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacedSegment;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\PlacementSettings;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\GreedyShelfPlacer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\TemplatePlacementEngine;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductOrderingService;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductSizeResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Enums\BrandExposure;
use Callcocam\LaravelRaptorPlannerate\Enums\FacingExpansion;
use Callcocam\LaravelRaptorPlannerate\Enums\FlowDirection;
use Callcocam\LaravelRaptorPlannerate\Enums\PlacementFailureReason;
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

function vbcEngine(): TemplatePlacementEngine
{
    return new TemplatePlacementEngine(
        new ProductWidthResolver,
        new ProductSizeResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
        new ProductOrderingService(new ProductSizeResolver),
    );
}

function vbcProduct(string $brand, float $width, string $categoryId, ?string $id = null): Product
{
    $product = new Product;
    $product->id = $id ?? (string) Str::ulid();
    $product->name = "Produto {$brand} {$width}";
    $product->ean = '7890000000000';
    $product->width = $width;
    $product->height = 0;
    $product->brand = $brand;
    $product->category_id = $categoryId;
    $product->status = 'published';

    return $product;
}

function vbcSlot(
    string $categoryId,
    int $shelfOrder,
    int $moduleNumber = 1,
    int $minFacings = 1,
    int $maxFacings = 1,
    FacingExpansion $facingExpansion = FacingExpansion::None,
): PlanogramTemplateSlot {
    $slot = new PlanogramTemplateSlot;
    $slot->id = (string) Str::ulid();
    $slot->category_id = $categoryId;
    $slot->module_number = $moduleNumber;
    $slot->shelf_order = $shelfOrder;
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
    $slot->role_override = null;
    // Relação carregada como null: evita lazy-load de categoria no teste (sem banco)
    $slot->setRelation('category', null);

    return $slot;
}

/**
 * Seção com N prateleiras (shelf_position em cm a partir do topo).
 */
function vbcSection(float $width = 100.0, int $numShelves = 2): Section
{
    $shelves = collect();

    for ($i = 0; $i < $numShelves; $i++) {
        $shelf = new Shelf;
        $shelf->id = (string) Str::ulid();
        $shelf->shelf_position = $i * 40;
        $shelves->push($shelf);
    }

    $section = new Section;
    $section->id = (string) Str::ulid();
    $section->width = $width;
    $section->cremalheira_width = 0.0;
    $section->setRelation('shelves', $shelves);

    return $section;
}

/**
 * Pré-carrega o cache de descendentes (evita consulta CTE no banco).
 */
function vbcSeedDescendants(TemplatePlacementEngine $engine, string $categoryId): void
{
    $prop = new ReflectionProperty($engine, 'descendantsCache');
    $prop->setAccessible(true);
    $prop->setValue($engine, [$categoryId => [$categoryId]]);
}

function vbcInjectFlow(TemplatePlacementEngine $engine, FlowDirection $direction): void
{
    $prop = new ReflectionProperty($engine, 'flowDirection');
    $prop->setAccessible(true);
    $prop->setValue($engine, $direction);
}

/**
 * Invoca placeVerticalGroup via reflection.
 *
 * @param  list<PlanogramTemplateSlot>  $group
 * @return array{placed: Collection, rejected: Collection, slot_analysis: array, placed_explanations: array, occupied_per_shelf: array, empty_slot_ids: array}
 */
function vbcPlaceGroup(TemplatePlacementEngine $engine, array $group, Section $section, Collection $products): array
{
    $settings = new PlacementSettings(
        strategy: 'abc',
        useExistingAnalysis: false,
        startDate: null,
        endDate: null,
        products: $products,
    );

    // Clearances zeradas = checagem de altura desativada (dado físico ausente)
    $clearances = [(string) $section->id => []];

    $ref = new ReflectionMethod($engine, 'placeVerticalGroup');
    $ref->setAccessible(true);

    $args = [$group, collect([$section]), $settings, &$clearances];

    return $ref->invokeArgs($engine, $args);
}

/** Agrupa segmentos por shelfId e indexa pelo productId da primeira layer. */
function vbcByProduct(Collection $placed): Collection
{
    return $placed->keyBy(fn (PlacedSegment $seg): string => $seg->layers->first()->productId);
}

// ── alinhamento de colunas entre prateleiras ─────────────────────────────────

test('colunas de marca alinham o X inicial em todas as prateleiras do grupo', function (): void {
    $engine = vbcEngine();
    $cat = (string) Str::ulid();
    vbcSeedDescendants($engine, $cat);

    $section = vbcSection(100.0, 2);
    $group = [vbcSlot($cat, 1), vbcSlot($cat, 2)];

    // Marca A: 3 × 30cm (demanda 90) | Marca B: 2 × 20cm (demanda 40)
    $a1 = vbcProduct('A', 30.0, $cat, 'a1');
    $a2 = vbcProduct('A', 30.0, $cat, 'a2');
    $a3 = vbcProduct('A', 30.0, $cat, 'a3');
    $b1 = vbcProduct('B', 20.0, $cat, 'b1');
    $b2 = vbcProduct('B', 20.0, $cat, 'b2');

    $result = vbcPlaceGroup($engine, $group, $section, collect([$a1, $a2, $a3, $b1, $b2]));

    $placed = $result['placed'];
    expect($placed)->toHaveCount(5);

    $byProduct = vbcByProduct($placed);

    // Coluna A começa em 0 nas duas prateleiras
    expect($byProduct['a1']->position)->toBe(0)
        ->and($byProduct['a3']->position)->toBe(0)
        ->and($byProduct['a3']->shelfId)->not->toBe($byProduct['a1']->shelfId);

    // Coluna B: mesmo X inicial nas duas prateleiras (b1 na linha de cima, b2 desce)
    expect($byProduct['b1']->position)->toBe($byProduct['b2']->position)
        ->and($byProduct['b1']->shelfId)->not->toBe($byProduct['b2']->shelfId);

    // Coluna B começa depois da coluna A (largura proporcional: A > 50%)
    expect($byProduct['b1']->position)->toBeGreaterThan(50);
});

// ── proporcionalidade e confinamento da expansão ─────────────────────────────

test('expansão de frentes nunca invade a coluna vizinha', function (): void {
    $engine = vbcEngine();
    $cat = (string) Str::ulid();
    vbcSeedDescendants($engine, $cat);

    $section = vbcSection(100.0, 2);
    $group = [
        vbcSlot($cat, 1, maxFacings: 20, facingExpansion: FacingExpansion::Score),
        vbcSlot($cat, 2, maxFacings: 20, facingExpansion: FacingExpansion::Score),
    ];

    // Demandas iguais → colunas de 50cm cada
    $a1 = vbcProduct('A', 10.0, $cat, 'a1');
    $b1 = vbcProduct('B', 10.0, $cat, 'b1');

    $result = vbcPlaceGroup($engine, $group, $section, collect([$a1, $b1]));
    $byProduct = vbcByProduct($result['placed']);

    // A expande até o teto da coluna (50cm = 5 frentes), nunca além
    expect($byProduct['a1']->width)->toBeLessThanOrEqual(50)
        ->and($byProduct['a1']->layers->first()->quantity)->toBe(5);

    // B permanece exatamente no início da sua coluna
    expect($byProduct['b1']->position)->toBe(50);
});

// ── marca sem espaço → overflow ───────────────────────────────────────────────

test('marca de menor demanda vai para o overflow quando nem os pisos cabem', function (): void {
    $engine = vbcEngine();
    $cat = (string) Str::ulid();
    vbcSeedDescendants($engine, $cat);

    $section = vbcSection(30.0, 2);
    $group = [vbcSlot($cat, 1), vbcSlot($cat, 2)];

    // Pisos: A=20 + B=25 = 45 > 30 → marca A (menor demanda) sai inteira
    $a1 = vbcProduct('A', 20.0, $cat, 'a1');
    $b1 = vbcProduct('B', 25.0, $cat, 'b1');

    $result = vbcPlaceGroup($engine, $group, $section, collect([$a1, $b1]));

    expect(vbcByProduct($result['placed'])->has('b1'))->toBeTrue();

    $rejectedIds = $result['rejected']
        ->filter(fn (array $r): bool => $r['product'] !== null && $r['reason'] === PlacementFailureReason::NoHorizontalSpace)
        ->map(fn (array $r): string => $r['product']->id)
        ->values()
        ->all();

    expect($rejectedIds)->toBe(['a1']);
});

// ── espelhamento RTL ──────────────────────────────────────────────────────────

test('RightToLeft espelha as colunas inteiras mantendo o alinhamento vertical', function (): void {
    $engine = vbcEngine();
    $cat = (string) Str::ulid();
    vbcSeedDescendants($engine, $cat);
    vbcInjectFlow($engine, FlowDirection::RightToLeft);

    $section = vbcSection(100.0, 2);
    $group = [vbcSlot($cat, 1), vbcSlot($cat, 2)];

    $a1 = vbcProduct('A', 30.0, $cat, 'a1');
    $a2 = vbcProduct('A', 30.0, $cat, 'a2');
    $a3 = vbcProduct('A', 30.0, $cat, 'a3');
    $b1 = vbcProduct('B', 20.0, $cat, 'b1');
    $b2 = vbcProduct('B', 20.0, $cat, 'b2');

    $result = vbcPlaceGroup($engine, $group, $section, collect([$a1, $a2, $a3, $b1, $b2]));
    $byProduct = vbcByProduct($result['placed']);

    // Colunas espelhadas: B (segunda no fluxo) vai para a esquerda, mantendo alinhamento
    expect($byProduct['b1']->position)->toBe($byProduct['b2']->position)
        ->and($byProduct['b1']->position)->toBeLessThan($byProduct['a1']->position);

    // Coluna A alinhada nas duas prateleiras após o espelhamento
    expect($byProduct['a1']->position)->toBe($byProduct['a3']->position);
});

// ── ordering por prateleira ───────────────────────────────────────────────────

test('ordering é sequencial por prateleira após a montagem das colunas', function (): void {
    $engine = vbcEngine();
    $cat = (string) Str::ulid();
    vbcSeedDescendants($engine, $cat);

    $section = vbcSection(100.0, 2);
    $group = [vbcSlot($cat, 1), vbcSlot($cat, 2)];

    $a1 = vbcProduct('A', 30.0, $cat, 'a1');
    $b1 = vbcProduct('B', 20.0, $cat, 'b1');

    $result = vbcPlaceGroup($engine, $group, $section, collect([$a1, $b1]));

    // Os dois produtos cabem na primeira prateleira (linha do topo)
    $byShelf = $result['placed']->groupBy('shelfId');
    $topShelf = $byShelf->first();

    expect($topShelf->sortBy('position')->pluck('ordering')->values()->all())->toBe([0, 1]);
});

// ── elegibilidade dos grupos (buildVerticalGroups) ────────────────────────────

test('buildVerticalGroups exclui o chão com 3+ prateleiras e prateleiras compartilhadas', function (): void {
    $engine = vbcEngine();
    $cat = (string) Str::ulid();
    $otherCat = (string) Str::ulid();

    // Grupo de 3 prateleiras (1=chão, 2, 3) + slot compartilhado na posição (1, 4)
    $floor = vbcSlot($cat, 1);
    $mid = vbcSlot($cat, 2);
    $top = vbcSlot($cat, 3);
    $sharedA = vbcSlot($cat, 4);
    $sharedB = vbcSlot($otherCat, 4);

    $slots = collect([$floor, $mid, $top, $sharedA, $sharedB]);
    $positionCounts = ['1_1' => 1, '1_2' => 1, '1_3' => 1, '1_4' => 2];

    $ref = new ReflectionMethod($engine, 'buildVerticalGroups');
    $ref->setAccessible(true);
    $groups = $ref->invoke($engine, $slots, $positionCounts);

    // Um único grupo elegível: [2, 3] — chão excluído, compartilhados fora
    expect($groups)->toHaveCount(1)
        ->and(array_map(fn (PlanogramTemplateSlot $s): int => $s->shelf_order, $groups[0]))->toBe([2, 3]);
});

test('buildVerticalGroups mantém o chão em grupos de exatamente 2 prateleiras', function (): void {
    $engine = vbcEngine();
    $cat = (string) Str::ulid();

    $slots = collect([vbcSlot($cat, 1), vbcSlot($cat, 2)]);
    $positionCounts = ['1_1' => 1, '1_2' => 1];

    $ref = new ReflectionMethod($engine, 'buildVerticalGroups');
    $ref->setAccessible(true);
    $groups = $ref->invoke($engine, $slots, $positionCounts);

    expect($groups)->toHaveCount(1)
        ->and(array_map(fn (PlanogramTemplateSlot $s): int => $s->shelf_order, $groups[0]))->toBe([1, 2]);
});

test('buildVerticalGroups ignora categorias de uma prateleira e shelf_orders não consecutivos', function (): void {
    $engine = vbcEngine();
    $catSingle = (string) Str::ulid();
    $catGap = (string) Str::ulid();

    $slots = collect([
        vbcSlot($catSingle, 1),
        vbcSlot($catGap, 2),
        vbcSlot($catGap, 4), // gap: 2 → 4
    ]);
    $positionCounts = ['1_1' => 1, '1_2' => 1, '1_4' => 1];

    $ref = new ReflectionMethod($engine, 'buildVerticalGroups');
    $ref->setAccessible(true);
    $groups = $ref->invoke($engine, $slots, $positionCounts);

    expect($groups)->toBe([]);
});
