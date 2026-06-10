<?php

use App\Enums\BrandExposure;
use App\Enums\PriceOrder;
use App\Enums\SizeOrder;
use App\Enums\ZonePriority;
use App\Models\PlanogramTemplateSlot;
use App\Models\Product;
use App\Services\AutoPlanogram\Placement\GreedyShelfPlacer;
use App\Services\AutoPlanogram\Placement\TemplatePlacementEngine;
use App\Services\AutoPlanogram\ProductOrderingService;
use App\Services\AutoPlanogram\ProductSizeResolver;
use App\Services\AutoPlanogram\ProductWidthResolver;
use App\Services\AutoPlanogram\Template\SlotReviewAnalysisService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

function invokeEngineOrderCandidates(TemplatePlacementEngine $engine, Collection $products, PlanogramTemplateSlot $slot): Collection
{
    $method = new ReflectionMethod($engine, 'orderCandidates');
    $method->setAccessible(true);

    return $method->invoke($engine, $products, $slot);
}

function invokeReviewOrderCandidates(SlotReviewAnalysisService $review, Collection $products, PlanogramTemplateSlot $slot): Collection
{
    $method = new ReflectionMethod($review, 'orderCandidates');
    $method->setAccessible(true);

    return $method->invoke($review, $slot, $products, [], [], [], 4, ZonePriority::None, ZonePriority::None);
}

function makeSizeSlot(SizeOrder $sizeOrder): PlanogramTemplateSlot
{
    $slot = new PlanogramTemplateSlot;
    $slot->size_order = $sizeOrder;
    $slot->price_order = PriceOrder::None;
    $slot->brand_exposure = BrandExposure::Mixed;

    return $slot;
}

function makeSizedProduct(string $packagingContent, float $weight): Product
{
    $product = new Product;
    $product->id = (string) Str::ulid();
    $product->packaging_content = $packagingContent;
    $product->weight = $weight;
    $product->brand = 'Marca';
    $product->price = 10;

    return $product;
}

test('engine and slot review produce the same ordering by size', function (): void {
    $sizeResolver = new ProductSizeResolver;
    $engine = new TemplatePlacementEngine(
        new ProductWidthResolver,
        $sizeResolver,
        new GreedyShelfPlacer(new ProductWidthResolver),
        new ProductOrderingService($sizeResolver),
    );
    $review = new SlotReviewAnalysisService(new ProductWidthResolver, $sizeResolver, new ProductOrderingService($sizeResolver));

    $products = collect([
        makeSizedProduct('500ml', 1.0),
        makeSizedProduct('', 0.25),
        makeSizedProduct('2l', 0.1),
        makeSizedProduct('750g', 0.5),
    ]);

    $ascSlot = makeSizeSlot(SizeOrder::Asc);
    $engineAsc = invokeEngineOrderCandidates($engine, $products, $ascSlot)->pluck('id')->values()->all();
    $reviewAsc = invokeReviewOrderCandidates($review, $products, $ascSlot)->pluck('id')->values()->all();

    $descSlot = makeSizeSlot(SizeOrder::Desc);
    $engineDesc = invokeEngineOrderCandidates($engine, $products, $descSlot)->pluck('id')->values()->all();
    $reviewDesc = invokeReviewOrderCandidates($review, $products, $descSlot)->pluck('id')->values()->all();

    expect($engineAsc)->toBe($reviewAsc)
        ->and($engineDesc)->toBe($reviewDesc);
});
