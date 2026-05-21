<?php

use App\Enums\BrandExposure;
use App\Enums\PriceOrder;
use App\Enums\SizeOrder;
use App\Models\PlanogramTemplateSlot;
use App\Models\Product;
use App\Services\AutoPlanogram\Placement\GreedyShelfPlacer;
use App\Services\AutoPlanogram\Placement\TemplatePlacementEngine;
use App\Services\AutoPlanogram\ProductSizeResolver;
use App\Services\AutoPlanogram\ProductWidthResolver;
use App\Services\AutoPlanogram\Template\SlotReviewAnalysisService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

function invokeOrderCandidates(object $service, Collection $products, PlanogramTemplateSlot $slot): Collection
{
    $method = new ReflectionMethod($service, 'orderCandidates');
    $method->setAccessible(true);

    return $method->invoke($service, $products, $slot);
}

function makeSizeSlot(SizeOrder $sizeOrder): PlanogramTemplateSlot
{
    $slot = new PlanogramTemplateSlot;
    $slot->size_order = $sizeOrder;
    $slot->price_order = PriceOrder::None;
    $slot->brand_exposure = BrandExposure::None;

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
    );
    $review = new SlotReviewAnalysisService(new ProductWidthResolver, $sizeResolver);

    $products = collect([
        makeSizedProduct('500ml', 1.0),
        makeSizedProduct('', 0.25),
        makeSizedProduct('2l', 0.1),
        makeSizedProduct('750g', 0.5),
    ]);

    $ascSlot = makeSizeSlot(SizeOrder::Asc);
    $engineAsc = invokeOrderCandidates($engine, $products, $ascSlot)->pluck('id')->values()->all();
    $reviewAsc = invokeOrderCandidates($review, $products, $ascSlot)->pluck('id')->values()->all();

    $descSlot = makeSizeSlot(SizeOrder::Desc);
    $engineDesc = invokeOrderCandidates($engine, $products, $descSlot)->pluck('id')->values()->all();
    $reviewDesc = invokeOrderCandidates($review, $products, $descSlot)->pluck('id')->values()->all();

    expect($engineAsc)->toBe($reviewAsc)
        ->and($engineDesc)->toBe($reviewDesc);
});
