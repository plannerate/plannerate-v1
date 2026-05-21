<?php

/**
 * Testes do relatório de explicação por produto (prompt 42).
 *
 * Verifica que cada produto alocado e rejeitado recebe uma justificativa
 * estruturada, e que os alertas de cadastro, espaço e estoque alvo são
 * consolidados corretamente.
 *
 * A lógica de alocação não é alterada — apenas anotada.
 */

use App\Enums\BrandExposure;
use App\Enums\FacingExpansion;
use App\Enums\PlacementFailureReason;
use App\Enums\PriceOrder;
use App\Enums\SizeOrder;
use App\Enums\SpaceFallback;
use App\Models\PlanogramTemplateSlot;
use App\Services\AutoPlanogram\Placement\GreedyShelfPlacer;
use App\Services\AutoPlanogram\Placement\TemplatePlacementEngine;
use App\Services\AutoPlanogram\ProductSizeResolver;
use App\Services\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Shelf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── helpers ───────────────────────────────────────────────────────────────────

function makeExplEngine(): TemplatePlacementEngine
{
    return new TemplatePlacementEngine(
        new ProductWidthResolver,
        new ProductSizeResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
    );
}

function makeExplProduct(float $width = 10.0, ?string $id = null): Product
{
    $product = new Product;
    $product->id = $id ?? (string) Str::ulid();
    $product->name = 'Produto Teste';
    $product->ean = '7890000000000';
    $product->width = $width;
    $product->category_id = (string) Str::ulid();
    $product->status = 'published';

    return $product;
}

function makeExplProductWithoutDimensions(): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = 'Produto Sem Dimensão';
    $product->ean = '7890000000001';
    $product->width = null;
    $product->category_id = (string) Str::ulid();
    $product->status = 'published';

    return $product;
}

function makeExplSlot(
    int $minFacings = 1,
    int $maxFacings = 3,
    FacingExpansion $facingExpansion = FacingExpansion::None,
): PlanogramTemplateSlot {
    $slot = new PlanogramTemplateSlot;
    $slot->id = (string) Str::ulid();
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

function makeExplSection(float $width = 100.0): Section
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
 * Chama distributeInShelf via reflection e retorna o resultado completo.
 *
 * @return array{placed: Collection, rejected: Collection, placed_explanations: list<array>}
 */
function callDistributeWithExplanations(
    TemplatePlacementEngine $engine,
    Collection $products,
    Section $section,
    Shelf $shelf,
    PlanogramTemplateSlot $slot,
    float $available,
): array {
    $ref = new ReflectionMethod($engine, 'distributeInShelf');
    $ref->setAccessible(true);

    return $ref->invoke($engine, $products, $section, $shelf, $slot, $available);
}

/**
 * Chama buildExplanationReport via reflection.
 *
 * @return array{allocated: list<array>, rejected: list<array>, alerts: list<array>}
 */
function callBuildExplanationReport(
    TemplatePlacementEngine $engine,
    array $allPlacedExplanations,
    Collection $rejected,
    array $slotAnalysis,
): array {
    $ref = new ReflectionMethod($engine, 'buildExplanationReport');
    $ref->setAccessible(true);

    return $ref->invoke($engine, $allPlacedExplanations, $rejected, $slotAnalysis);
}

// ── testes: placed_explanations por produto ────────────────────────────────────

test('produto alocado gera entrada de explicação com dados básicos', function (): void {
    $engine = makeExplEngine();
    $p = makeExplProduct(10.0, 'prod-abc');

    $section = makeExplSection(100.0);
    $shelf = $section->shelves->first();
    $slot = makeExplSlot(minFacings: 1, maxFacings: 1);

    $result = callDistributeWithExplanations($engine, collect([$p]), $section, $shelf, $slot, 100.0);

    expect($result['placed_explanations'])->toHaveCount(1);

    $entry = $result['placed_explanations'][0];
    expect($entry['product_id'])->toBe('prod-abc');
    expect($entry['product_name'])->toBe('Produto Teste');
    expect($entry['slot_id'])->toBe($slot->id);
    expect($entry['facings'])->toBe(1);
    expect($entry['facings_expanded'])->toBeFalse();
    expect($entry['is_mandatory'])->toBeFalse();
    expect($entry['has_target_stock'])->toBeFalse();
});

test('produto rejeitado por falta de espaço não gera placed_explanation', function (): void {
    $engine = makeExplEngine();

    // slot de 15cm, dois produtos de 10cm — o segundo não cabe
    $p1 = makeExplProduct(10.0);
    $p2 = makeExplProduct(10.0);

    $section = makeExplSection(15.0);
    $shelf = $section->shelves->first();
    $slot = makeExplSlot(minFacings: 1, maxFacings: 1);

    $result = callDistributeWithExplanations($engine, collect([$p1, $p2]), $section, $shelf, $slot, 15.0);

    expect($result['placed_explanations'])->toHaveCount(1);
    expect($result['placed'])->toHaveCount(1);
    expect($result['rejected'])->toHaveCount(1);
    expect($result['rejected']->first()['reason'])->toBe(PlacementFailureReason::NoHorizontalSpace);
});

test('facings_expanded = true quando expandFacings amplia frentes', function (): void {
    $engine = makeExplEngine();

    // produto de 10cm; slot 50cm com min=1, max=3, expansão ativa
    $p = makeExplProduct(10.0);

    $section = makeExplSection(50.0);
    $shelf = $section->shelves->first();
    $slot = makeExplSlot(minFacings: 1, maxFacings: 3, facingExpansion: FacingExpansion::Equal);

    $result = callDistributeWithExplanations($engine, collect([$p]), $section, $shelf, $slot, 50.0);

    expect($result['placed_explanations'])->toHaveCount(1);

    $entry = $result['placed_explanations'][0];
    expect($entry['facings'])->toBeGreaterThan(1);
    expect($entry['facings_expanded'])->toBeTrue();
});

test('produto sem dimensões gera rejected, não placed_explanation', function (): void {
    $engine = makeExplEngine();

    $semDim = makeExplProductWithoutDimensions();

    $section = makeExplSection(100.0);
    $shelf = $section->shelves->first();
    $slot = makeExplSlot();

    $result = callDistributeWithExplanations($engine, collect([$semDim]), $section, $shelf, $slot, 100.0);

    expect($result['placed_explanations'])->toHaveCount(0);
    expect($result['rejected'])->toHaveCount(1);
    expect($result['rejected']->first()['reason'])->toBe(PlacementFailureReason::MissingDimensions);
});

test('abc_class é refletida na explicação quando abcClassMap injetado', function (): void {
    $engine = makeExplEngine();
    $p = makeExplProduct(10.0, 'prod-a');

    // injeta abcClassMap via reflection
    $prop = new ReflectionProperty($engine, 'abcClassMap');
    $prop->setAccessible(true);
    $prop->setValue($engine, ['prod-a' => 'A']);

    $section = makeExplSection(100.0);
    $shelf = $section->shelves->first();
    $slot = makeExplSlot();

    $result = callDistributeWithExplanations($engine, collect([$p]), $section, $shelf, $slot, 100.0);

    expect($result['placed_explanations'][0]['abc_class'])->toBe('A');
});

// ── testes: buildExplanationReport — alertas ──────────────────────────────────

test('alerta missing_dimensions gerado quando há rejeições por dimensão', function (): void {
    $engine = makeExplEngine();

    $rejected = collect([
        [
            'product' => makeExplProduct(),
            'reason' => PlacementFailureReason::MissingDimensions,
            'slot_id' => 'slot-1',
        ],
    ]);

    $report = callBuildExplanationReport($engine, [], $rejected, []);

    $types = collect($report['alerts'])->pluck('type')->all();
    expect($types)->toContain('missing_dimensions');

    $alert = collect($report['alerts'])->firstWhere('type', 'missing_dimensions');
    expect($alert['count'])->toBe(1);
});

test('alerta mix_excede_gondola gerado quando há rejeições por falta de espaço', function (): void {
    $engine = makeExplEngine();

    $rejected = collect([
        [
            'product' => makeExplProduct(),
            'reason' => PlacementFailureReason::NoHorizontalSpace,
            'slot_id' => 'slot-1',
        ],
        [
            'product' => makeExplProduct(),
            'reason' => PlacementFailureReason::NoHorizontalSpace,
            'slot_id' => 'slot-1',
        ],
    ]);

    $report = callBuildExplanationReport($engine, [], $rejected, []);

    $alert = collect($report['alerts'])->firstWhere('type', 'mix_excede_gondola');
    expect($alert)->not->toBeNull();
    expect($alert['count'])->toBe(2);
});

test('alerta target_stock_not_met gerado para produtos com estoque alvo sem expansão', function (): void {
    $engine = makeExplEngine();

    $allocated = [
        [
            'product_id' => 'prod-1',
            'product_name' => 'P1',
            'slot_id' => 'slot-1',
            'category_name' => 'Cat',
            'abc_class' => 'A',
            'is_mandatory' => false,
            'facings' => 1,
            'facings_expanded' => false,
            'zone' => 'hot',
            'role' => null,
            'has_target_stock' => true,
        ],
    ];

    $report = callBuildExplanationReport($engine, $allocated, collect(), []);

    $alert = collect($report['alerts'])->firstWhere('type', 'target_stock_not_met');
    expect($alert)->not->toBeNull();
    expect($alert['count'])->toBe(1);
});

test('sem alertas quando todos os produtos estão alocados e sem problemas', function (): void {
    $engine = makeExplEngine();

    $allocated = [
        [
            'product_id' => 'prod-1',
            'product_name' => 'P1',
            'slot_id' => 'slot-1',
            'category_name' => 'Cat',
            'abc_class' => 'B',
            'is_mandatory' => false,
            'facings' => 2,
            'facings_expanded' => true,
            'zone' => 'neutral',
            'role' => null,
            'has_target_stock' => false,
        ],
    ];

    $report = callBuildExplanationReport($engine, $allocated, collect(), []);

    expect($report['alerts'])->toBeEmpty();
    expect($report['allocated'])->toHaveCount(1);
    expect($report['rejected'])->toHaveCount(0);
});

test('rejected explanation inclui motivo_label e abc_class quando abcClassMap disponível', function (): void {
    $engine = makeExplEngine();

    $prod = makeExplProduct(10.0, 'prod-blocked');

    $prop = new ReflectionProperty($engine, 'abcClassMap');
    $prop->setAccessible(true);
    $prop->setValue($engine, ['prod-blocked' => 'C']);

    $rejected = collect([
        [
            'product' => $prod,
            'reason' => PlacementFailureReason::Blocked,
            'slot_id' => 'slot-x',
        ],
    ]);

    $report = callBuildExplanationReport($engine, [], $rejected, []);

    expect($report['rejected'])->toHaveCount(1);

    $entry = $report['rejected'][0];
    expect($entry['product_id'])->toBe('prod-blocked');
    expect($entry['abc_class'])->toBe('C');
    expect($entry['motivo'])->toBe('blocked');
    expect($entry['motivo_label'])->toBe('Produto bloqueado por regra');
});
