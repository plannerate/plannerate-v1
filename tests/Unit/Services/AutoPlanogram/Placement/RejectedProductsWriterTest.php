<?php

use App\Enums\PlacementFailureReason;
use App\Services\AutoPlanogram\DTO\PlacedLayer;
use App\Services\AutoPlanogram\DTO\PlacedSegment;
use App\Services\AutoPlanogram\Placement\RejectedProductsWriter;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;

function writerTestProduct(string $id, float $width = 20.0, float $height = 33.0): Product
{
    $p = new Product;
    $p->id = $id;
    $p->name = "Product {$id}";
    $p->ean = str_pad($id, 13, '0', STR_PAD_LEFT);
    $p->width = $width;
    $p->height = $height;

    return $p;
}

it('deduplicates rejected records when same product appears in multiple slots', function () {
    $product = writerTestProduct('prod-abc');

    $rejected = collect([
        ['product' => $product, 'reason' => PlacementFailureReason::NoHorizontalSpace, 'slot_id' => 'slot-1'],
        ['product' => $product, 'reason' => PlacementFailureReason::NoHorizontalSpace, 'slot_id' => 'slot-2'],
    ]);

    $unique = $rejected
        ->filter(fn ($r) => $r['product'] !== null)
        ->unique(fn ($r) => $r['product']->id)
        ->values();

    expect($unique)->toHaveCount(1)
        ->and($unique->first()['slot_id'])->toBe('slot-1');
});

it('keeps products with different product_ids as separate rejected records', function () {
    $p1 = writerTestProduct('prod-1');
    $p2 = writerTestProduct('prod-2');

    $rejected = collect([
        ['product' => $p1, 'reason' => PlacementFailureReason::NoHorizontalSpace, 'slot_id' => 'slot-1'],
        ['product' => $p2, 'reason' => PlacementFailureReason::NoHorizontalSpace, 'slot_id' => 'slot-1'],
    ]);

    $unique = $rejected
        ->filter(fn ($r) => $r['product'] !== null)
        ->unique(fn ($r) => $r['product']->id)
        ->values();

    expect($unique)->toHaveCount(2);
});

it('excludes products from rejected list when they were placed in another shelf', function () {
    $placed = writerTestProduct('prod-placed');
    $trueRejected = writerTestProduct('prod-rejected');

    $layer = new PlacedLayer(productId: 'prod-placed', ean: '0000000000001', quantity: 2, height: 1);
    $segment = new PlacedSegment(
        sectionId: 'sec-1',
        shelfId: 'shelf-2',
        ordering: 0,
        position: 0,
        width: 40,
        distributedWidth: 40,
        layers: collect([$layer]),
    );

    $placedSegments = collect([$segment]);
    $rejectedProducts = collect([
        ['product' => $placed, 'reason' => PlacementFailureReason::NoHorizontalSpace, 'slot_id' => 'slot-1'],
        ['product' => $trueRejected, 'reason' => PlacementFailureReason::NoHorizontalSpace, 'slot_id' => 'slot-1'],
    ]);

    $placedProductIds = $placedSegments
        ->flatMap(fn ($seg) => $seg->layers->map(fn ($l) => $l->productId))
        ->flip()
        ->all();

    $filtered = $rejectedProducts
        ->filter(fn ($r) => $r['product'] !== null && ! isset($placedProductIds[$r['product']->id]))
        ->groupBy(fn ($r) => $r['product']->id);

    expect($filtered)->toHaveCount(1)
        ->and($filtered->keys()->first())->toBe('prod-rejected');
});

it('buildSlotAnalysisIndex indexes by slot_id', function () {
    $writer = new RejectedProductsWriter;
    $reflection = new ReflectionClass($writer);
    $method = $reflection->getMethod('buildSlotAnalysisIndex');
    $method->setAccessible(true);

    $slotAnalysis = [
        ['slot_id' => 'slot-1', 'category_name' => 'Farinha', 'module_number' => 1, 'shelf_order' => 4],
        ['slot_id' => 'slot-2', 'category_name' => 'Açúcar', 'module_number' => 1, 'shelf_order' => 3],
    ];

    $index = $method->invoke($writer, $slotAnalysis);

    expect($index)->toHaveKey('slot-1')
        ->and($index['slot-1']['category_name'])->toBe('Farinha')
        ->and($index)->toHaveKey('slot-2');
});
