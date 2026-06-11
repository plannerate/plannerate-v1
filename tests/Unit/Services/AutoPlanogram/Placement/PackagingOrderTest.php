<?php

use App\Models\PlanogramTemplateSlot;
use App\Services\AutoPlanogram\Placement\GreedyShelfPlacer;
use App\Services\AutoPlanogram\Placement\TemplatePlacementEngine;
use App\Services\AutoPlanogram\ProductOrderingService;
use App\Services\AutoPlanogram\ProductSizeResolver;
use App\Services\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\Enums\BrandExposure;
use Callcocam\LaravelRaptorPlannerate\Enums\PriceOrder;
use Callcocam\LaravelRaptorPlannerate\Enums\SizeOrder;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── helpers ──────────────────────────────────────────────────────────────────

function packagingEngine(): TemplatePlacementEngine
{
    return new TemplatePlacementEngine(
        new ProductWidthResolver,
        new ProductSizeResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
        new ProductOrderingService(new ProductSizeResolver),
    );
}

/**
 * @param  array{key: string, direction: string, packaging_order?: list<string>}[]|null  $visualCriteria
 */
function makePackagingSlot(?array $visualCriteria = null): PlanogramTemplateSlot
{
    $slot = new PlanogramTemplateSlot;
    $slot->price_order = PriceOrder::None;
    $slot->size_order = SizeOrder::None;
    $slot->brand_exposure = BrandExposure::Horizontal;
    $slot->visual_criteria = $visualCriteria;

    return $slot;
}

function makePackagingProduct(?string $packagingType = null): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->name = 'Produto '.($packagingType ?? 'sem-tipo');
    $product->ean = '7890000000000';
    $product->width = 10.0;
    $product->category_id = 'cat1';
    $product->status = 'published';
    $product->packaging_type = $packagingType;

    return $product;
}

function callPackagingOrder(TemplatePlacementEngine $engine, Collection $products, PlanogramTemplateSlot $slot): Collection
{
    $ref = new ReflectionMethod($engine, 'orderCandidates');
    $ref->setAccessible(true);

    return $ref->invoke($engine, $products, $slot, null, null);
}

// ── testes ────────────────────────────────────────────────────────────────────

test('embalagem: caixa antes de sache conforme ordem configurada', function (): void {
    $engine = packagingEngine();

    $sache = makePackagingProduct('sache');
    $caixa = makePackagingProduct('caixa');
    $pacote = makePackagingProduct('pacote');

    $slot = makePackagingSlot([
        ['key' => 'embalagem', 'direction' => 'none', 'packaging_order' => ['caixa', 'pacote', 'sache']],
    ]);

    $result = callPackagingOrder($engine, collect([$sache, $caixa, $pacote]), $slot);

    expect($result->pluck('packaging_type')->values()->all())->toBe(['caixa', 'pacote', 'sache']);
});

test('embalagem: produto sem tipo vai para o fim', function (): void {
    $engine = packagingEngine();

    $semTipo = makePackagingProduct(null);
    $caixa = makePackagingProduct('caixa');
    $sache = makePackagingProduct('sache');

    $slot = makePackagingSlot([
        ['key' => 'embalagem', 'direction' => 'none', 'packaging_order' => ['caixa', 'sache']],
    ]);

    $result = callPackagingOrder($engine, collect([$semTipo, $caixa, $sache]), $slot);

    expect($result->first()->packaging_type)->toBe('caixa');
    expect($result->last()->packaging_type)->toBeNull();
});

test('embalagem: tipo não listado vai para o fim', function (): void {
    $engine = packagingEngine();

    $pet = makePackagingProduct('pet');
    $caixa = makePackagingProduct('caixa');

    $slot = makePackagingSlot([
        ['key' => 'embalagem', 'direction' => 'none', 'packaging_order' => ['caixa']],
    ]);

    $result = callPackagingOrder($engine, collect([$pet, $caixa]), $slot);

    expect($result->first()->packaging_type)->toBe('caixa');
    expect($result->last()->packaging_type)->toBe('pet');
});

test('embalagem: packaging_order vazia não altera a ordem', function (): void {
    $engine = packagingEngine();

    $caixa = makePackagingProduct('caixa');
    $sache = makePackagingProduct('sache');

    $slot = makePackagingSlot([
        ['key' => 'embalagem', 'direction' => 'none', 'packaging_order' => []],
    ]);

    $result = callPackagingOrder($engine, collect([$caixa, $sache]), $slot);

    // sem ordem definida: mantém ordem original
    expect($result->first()->packaging_type)->toBe('caixa');
});

test('embalagem: ProductOrderingService respeita packaging_order (paridade geração × reordenação)', function (): void {
    $engine = packagingEngine();
    $service = new ProductOrderingService(new ProductSizeResolver);

    $sache = makePackagingProduct('sache');
    $caixa = makePackagingProduct('caixa');
    $pacote = makePackagingProduct('pacote');
    $products = collect([$sache, $caixa, $pacote]);

    $slot = makePackagingSlot([
        ['key' => 'embalagem', 'direction' => 'none', 'packaging_order' => ['caixa', 'pacote', 'sache']],
    ]);

    $engineOrder = callPackagingOrder($engine, $products, $slot)->pluck('id')->values()->all();
    $serviceOrder = $service->orderBySlot($products, $slot)->pluck('id')->values()->all();

    expect($serviceOrder)->toBe($engineOrder)
        ->and($service->orderBySlot($products, $slot)->pluck('packaging_type')->values()->all())
        ->toBe(['caixa', 'pacote', 'sache']);
});

test('embalagem: cascata com preco — embalagem domina como critério primário', function (): void {
    $engine = packagingEngine();

    // Dois sachet com preços 1 e 5; uma caixa com preço 3
    $sache1 = makePackagingProduct('sache');
    $sache1->price = 1.0;
    $sache2 = makePackagingProduct('sache');
    $sache2->price = 5.0;
    $caixa = makePackagingProduct('caixa');
    $caixa->price = 3.0;

    // embalagem primeiro → caixa antes de sache; dentro de sache: preco asc
    $slot = makePackagingSlot([
        ['key' => 'embalagem', 'direction' => 'none', 'packaging_order' => ['caixa', 'sache']],
        ['key' => 'preco', 'direction' => 'asc'],
    ]);

    $result = callPackagingOrder($engine, collect([$sache2, $sache1, $caixa]), $slot);

    expect($result->pluck('packaging_type')->values()->all())->toBe(['caixa', 'sache', 'sache']);
    expect($result->first()->price)->toBe(3.0);
    // sachês ordenados por preço asc (stable sort): sache1(1.0) antes de sache2(5.0)
    expect($result->slice(1)->pluck('price')->values()->all())->toBe([1.0, 5.0]);
});
