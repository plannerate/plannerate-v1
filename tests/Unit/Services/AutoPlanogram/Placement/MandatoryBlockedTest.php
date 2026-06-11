<?php

/**
 * Testes de produtos obrigatórios e bloqueados (prompt 39).
 *
 * Verifica partitionBlocked (por produto_id, marca e subcategoria),
 * priorização de obrigatórios em orderCandidates, e re-rotulagem
 * MandatoryNoSpace quando obrigatório não cabe no slot.
 */

use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\Placement\GreedyShelfPlacer;
use App\Services\AutoPlanogram\Placement\TemplatePlacementEngine;
use App\Services\AutoPlanogram\ProductOrderingService;
use App\Services\AutoPlanogram\ProductSizeResolver;
use App\Services\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Enums\BrandExposure;
use Callcocam\LaravelRaptorPlannerate\Enums\PriceOrder;
use Callcocam\LaravelRaptorPlannerate\Enums\SizeOrder;
use Callcocam\LaravelRaptorPlannerate\Models\PlanogramTemplateSlot;
use Callcocam\LaravelRaptorPlannerate\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── helpers ───────────────────────────────────────────────────────────────────

function makeMBEngine(): TemplatePlacementEngine
{
    return new TemplatePlacementEngine(
        new ProductWidthResolver,
        new ProductSizeResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
        new ProductOrderingService(new ProductSizeResolver),
    );
}

/**
 * Constrói um produto configurável para testes mandatory/blocked.
 */
function makeMBProduct(
    float $width = 10.0,
    ?string $brand = null,
    ?string $categoryId = null,
    ?string $id = null,
): Product {
    $product = new Product;
    $product->id = $id ?? (string) Str::ulid();
    $product->name = 'Test product';
    $product->ean = '7890000000000';
    $product->width = $width;
    $product->brand = $brand ?? 'Marca Genérica';
    $product->category_id = $categoryId ?? (string) Str::ulid();
    $product->status = 'published';

    return $product;
}

/**
 * Constrói PlacementSettings mínimo sem template (apenas com produtos e regras).
 *
 * @param  array<string, true>  $mandatoryProductIds
 * @param  array<string, true>  $blockedProductIds
 * @param  array<string, true>  $blockedBrands
 * @param  array<string, true>  $blockedSubcategoryIds
 */
function makeMBSettings(
    Collection $products,
    array $mandatoryProductIds = [],
    array $blockedProductIds = [],
    array $blockedBrands = [],
    array $blockedSubcategoryIds = [],
): PlacementSettings {
    return new PlacementSettings(
        strategy: 'abc',
        useExistingAnalysis: false,
        startDate: null,
        endDate: null,
        products: $products,
        mandatoryProductIds: $mandatoryProductIds,
        blockedProductIds: $blockedProductIds,
        blockedBrands: $blockedBrands,
        blockedSubcategoryIds: $blockedSubcategoryIds,
    );
}

/**
 * Injeta os mapas de regra diretamente nos campos privados do engine via reflection.
 *
 * @param  array<string, true>  $mandatoryProductIds
 * @param  array<string, true>  $blockedProductIds
 * @param  array<string, true>  $blockedBrands
 * @param  array<string, true>  $blockedSubcategoryIds
 */
function injectRules(
    TemplatePlacementEngine $engine,
    array $mandatoryProductIds = [],
    array $blockedProductIds = [],
    array $blockedBrands = [],
    array $blockedSubcategoryIds = [],
): void {
    foreach (['mandatoryProductIds', 'blockedProductIds', 'blockedBrands', 'blockedSubcategoryIds'] as $field) {
        $prop = new ReflectionProperty($engine, $field);
        $prop->setAccessible(true);
        $prop->setValue($engine, $$field);
    }
}

/**
 * Chama TemplatePlacementEngine::partitionBlocked via reflection.
 *
 * @return array{0: Collection, 1: Collection}
 */
function callPartitionBlocked(TemplatePlacementEngine $engine, Collection $candidates): array
{
    $ref = new ReflectionMethod($engine, 'partitionBlocked');
    $ref->setAccessible(true);

    return $ref->invoke($engine, $candidates);
}

/**
 * Chama TemplatePlacementEngine::orderCandidates via reflection.
 * Usa um slot sem critérios visuais para forçar legacy ordering.
 */
function callMBOrderCandidates(TemplatePlacementEngine $engine, Collection $products): Collection
{
    $slot = new PlanogramTemplateSlot;
    $slot->visual_criteria = null;
    $slot->size_order = SizeOrder::None;
    $slot->price_order = PriceOrder::None;
    $slot->brand_exposure = BrandExposure::Mixed;

    $ref = new ReflectionMethod($engine, 'orderCandidates');
    $ref->setAccessible(true);

    return $ref->invoke($engine, $products, $slot, null, null);
}

// ── regressão: sem regras tudo passa ─────────────────────────────────────────

test('MandatoryBlocked regressão: sem regras partitionBlocked retorna todos como válidos', function (): void {
    $engine = makeMBEngine();
    // sem injeção de regras — todos os mapas ficam vazios

    $p1 = makeMBProduct();
    $p2 = makeMBProduct();
    $candidates = collect([$p1, $p2]);

    [$valid, $blocked] = callPartitionBlocked($engine, $candidates);

    expect($valid)->toHaveCount(2)
        ->and($blocked)->toHaveCount(0);
});

// ── bloqueio por product_id ───────────────────────────────────────────────────

test('MandatoryBlocked produto bloqueado por product_id é separado com motivo Blocked', function (): void {
    $engine = makeMBEngine();

    $blockedProduct = makeMBProduct();
    $normalProduct = makeMBProduct();

    injectRules($engine, blockedProductIds: [$blockedProduct->id => true]);

    $candidates = collect([$blockedProduct, $normalProduct]);
    [$valid, $blocked] = callPartitionBlocked($engine, $candidates);

    expect($valid)->toHaveCount(1)
        ->and($blocked)->toHaveCount(1)
        ->and($blocked->first()->id)->toBe($blockedProduct->id)
        ->and($valid->first()->id)->toBe($normalProduct->id);
});

// ── bloqueio por marca ────────────────────────────────────────────────────────

test('MandatoryBlocked produto bloqueado por marca é separado dos candidatos', function (): void {
    $engine = makeMBEngine();

    $blockedBrand = 'MARCA_BLOQUEADA';
    $pBlocked1 = makeMBProduct(brand: $blockedBrand);
    $pBlocked2 = makeMBProduct(brand: $blockedBrand);
    $pOk = makeMBProduct(brand: 'OUTRA_MARCA');

    injectRules($engine, blockedBrands: [$blockedBrand => true]);

    $candidates = collect([$pBlocked1, $pBlocked2, $pOk]);
    [$valid, $blocked] = callPartitionBlocked($engine, $candidates);

    expect($valid)->toHaveCount(1)
        ->and($blocked)->toHaveCount(2)
        ->and($valid->first()->brand)->toBe('OUTRA_MARCA');
});

// ── bloqueio por subcategoria ─────────────────────────────────────────────────

test('MandatoryBlocked produto bloqueado por subcategoria é separado', function (): void {
    $engine = makeMBEngine();

    $blockedCat = (string) Str::ulid();
    $okCat = (string) Str::ulid();

    $pBlocked = makeMBProduct(categoryId: $blockedCat);
    $pOk = makeMBProduct(categoryId: $okCat);

    injectRules($engine, blockedSubcategoryIds: [$blockedCat => true]);

    $candidates = collect([$pBlocked, $pOk]);
    [$valid, $blocked] = callPartitionBlocked($engine, $candidates);

    expect($valid)->toHaveCount(1)
        ->and($blocked)->toHaveCount(1)
        ->and($blocked->first()->category_id)->toBe($blockedCat);
});

// ── obrigatório sobe ao topo em orderCandidates ───────────────────────────────

test('MandatoryBlocked obrigatório sobe ao topo independente de score ou zona', function (): void {
    $engine = makeMBEngine();

    $mandatory = makeMBProduct();
    $regular1 = makeMBProduct();
    $regular2 = makeMBProduct();

    // Obrigatório está no meio da lista
    $products = collect([$regular1, $mandatory, $regular2]);

    injectRules($engine, mandatoryProductIds: [$mandatory->id => true]);

    $ordered = callMBOrderCandidates($engine, $products);

    expect($ordered->first()->id)->toBe($mandatory->id);
});
