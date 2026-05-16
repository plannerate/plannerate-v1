<?php

use App\Services\AutoPlanogram\DTO\PlacementSettings;
use App\Services\AutoPlanogram\DTO\ScoredProduct;
use App\Services\AutoPlanogram\FacingCalculatorService;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;

function facingProduct(string $id, float $width = 10.0): Product
{
    $product = new Product;
    $product->id = $id;
    $product->name = 'Produto '.$id;
    $product->ean = str_pad($id, 13, '0', STR_PAD_LEFT);
    $product->width = $width;

    return $product;
}

function scoredWithIdeal(string $id, int $facingIdeal, float $width = 10.0): ScoredProduct
{
    $product = facingProduct($id, $width);

    return new ScoredProduct(
        productId: $product->id,
        ean: (string) $product->ean,
        score: 100,
        product: $product,
        metadata: [
            'facing_ideal' => $facingIdeal,
        ],
    );
}

test('scale facings reduces proportionally when demand exceeds available width', function (): void {
    $service = new FacingCalculatorService;
    $products = collect([
        scoredWithIdeal('1', 6, 10),
        scoredWithIdeal('2', 4, 10),
        scoredWithIdeal('3', 2, 10),
    ]);

    // Demand = (6+4+2)*10 = 120. Available = 60 => scale factor = 0.5
    $scaled = $service->scaleFacings($products, availableWidth: 60, minFacings: 1);

    expect($scaled->pluck('metadata.facing_final')->all())->toBe([3, 2, 1]);
});

test('scale facings keeps values when demand is below available width', function (): void {
    $service = new FacingCalculatorService;
    $products = collect([
        scoredWithIdeal('1', 2, 10),
        scoredWithIdeal('2', 1, 10),
    ]);

    // Demand = 30, available = 50 => no scale
    $scaled = $service->scaleFacings($products, availableWidth: 50, minFacings: 1);

    expect($scaled->pluck('metadata.facing_final')->all())->toBe([2, 1]);
});

test('scale facings keeps values when demand equals available width', function (): void {
    $service = new FacingCalculatorService;
    $products = collect([
        scoredWithIdeal('1', 3, 10),
        scoredWithIdeal('2', 2, 10),
    ]);

    // Demand = 50, available = 50 => no scale
    $scaled = $service->scaleFacings($products, availableWidth: 50, minFacings: 1);

    expect($scaled->pluck('metadata.facing_final')->all())->toBe([3, 2]);
});

test('scale facings preserves hierarchy between products', function (): void {
    $service = new FacingCalculatorService;
    $products = collect([
        scoredWithIdeal('A', 6, 10),
        scoredWithIdeal('C', 2, 10),
    ]);

    $scaled = $service->scaleFacings($products, availableWidth: 40, minFacings: 1);

    expect($scaled[0]->metadata['facing_final'])->toBeGreaterThan($scaled[1]->metadata['facing_final']);
});

test('scale facings never returns zero facings when min is one', function (): void {
    $service = new FacingCalculatorService;
    $products = collect([
        scoredWithIdeal('1', 1, 10),
        scoredWithIdeal('2', 1, 10),
        scoredWithIdeal('3', 1, 10),
    ]);

    $scaled = $service->scaleFacings($products, availableWidth: 5, minFacings: 1);

    expect($scaled->pluck('metadata.facing_final')->min())->toBe(1);
});

test('scaled demanded width is less than or equal to available width in proportional scenario', function (): void {
    $service = new FacingCalculatorService;
    $products = collect([
        scoredWithIdeal('1', 6, 10),
        scoredWithIdeal('2', 4, 10),
        scoredWithIdeal('3', 2, 10),
    ]);

    $availableWidth = 60.0;
    $scaled = $service->scaleFacings($products, availableWidth: $availableWidth, minFacings: 1);

    $scaledWidth = $scaled->sum(fn (ScoredProduct $product): float => ((int) $product->metadata['facing_final']) * ((float) ($product->product->width ?? 10)));

    expect($scaledWidth)->toBeLessThanOrEqual($availableWidth);
});

test('calculate ideal facings enriches metadata with facing ideal and final', function (): void {
    $service = new FacingCalculatorService;

    $product = facingProduct('10', 12.0);
    $scored = new ScoredProduct(
        productId: $product->id,
        ean: (string) $product->ean,
        score: 90,
        product: $product,
        metadata: [
            'abc_class' => 'A',
            'sales_total' => 1000,
            'margin' => 100,
            'target_stock' => 25,
        ],
    );

    $settings = new PlacementSettings(
        strategy: 'abc',
        useExistingAnalysis: false,
        startDate: null,
        endDate: null,
        minFacings: 1,
        maxFacings: 10,
    );

    $result = $service->calculateIdealFacings(collect([$scored]), $settings);

    expect($result)->toHaveCount(1)
        ->and($result[0]->metadata)->toHaveKey('facing_ideal')
        ->and($result[0]->metadata)->toHaveKey('facing_final')
        ->and($result[0]->metadata['facing_ideal'])->toBeGreaterThanOrEqual(1)
        ->and($result[0]->metadata['facing_final'])->toBeGreaterThanOrEqual(1);
});
