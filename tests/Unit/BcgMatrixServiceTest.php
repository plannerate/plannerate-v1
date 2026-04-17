<?php

use App\Services\Analysis\BcgMatrixService;
use Illuminate\Support\Collection;

it('calculates median correctly for odd number of values', function () {
    $service = new BcgMatrixService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('calculateMedian');
    $method->setAccessible(true);

    $values = collect([1.0, 3.0, 5.0, 7.0, 9.0]);
    $median = $method->invoke($service, $values);

    expect($median)->toBe(5.0);
});

it('calculates median correctly for even number of values', function () {
    $service = new BcgMatrixService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('calculateMedian');
    $method->setAccessible(true);

    $values = collect([1.0, 3.0, 5.0, 7.0]);
    $median = $method->invoke($service, $values);

    expect($median)->toBe(4.0);
});

it('returns 0 for empty values collection', function () {
    $service = new BcgMatrixService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('calculateMedian');
    $method->setAccessible(true);

    $median = $method->invoke($service, collect());

    expect($median)->toBe(0.0);
});

it('classifies star quadrant when high share and positive growth', function () {
    $service = new BcgMatrixService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('classifyQuadrants');
    $method->setAccessible(true);

    // Two products: product 1 dominates (high share), product 2 is small
    // Both have positive growth (current > previous)
    $salesData = collect([
        (object) ['product_id' => 'p1', 'value_current' => 80.0, 'value_previous' => 60.0],
        (object) ['product_id' => 'p2', 'value_current' => 20.0, 'value_previous' => 10.0],
    ]);

    $products = collect([
        'p1' => (object) ['id' => 'p1', 'name' => 'Product 1', 'ean' => '111', 'image_url' => null, 'category_id' => 'c1', 'category' => (object) ['name' => 'Cat 1']],
        'p2' => (object) ['id' => 'p2', 'name' => 'Product 2', 'ean' => '222', 'image_url' => null, 'category_id' => 'c1', 'category' => (object) ['name' => 'Cat 1']],
    ]);

    $result = $method->invoke($service, $salesData, $products);

    // p1 has 80% share (above median ~50%), positive growth → star
    $p1 = $result->firstWhere('product_id', 'p1');
    expect($p1['quadrant'])->toBe('star');
});

it('classifies dog quadrant when low share and negative growth', function () {
    $service = new BcgMatrixService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('classifyQuadrants');
    $method->setAccessible(true);

    // Product 1 dominates, product 2 is small with declining sales
    $salesData = collect([
        (object) ['product_id' => 'p1', 'value_current' => 90.0, 'value_previous' => 80.0],
        (object) ['product_id' => 'p2', 'value_current' => 10.0, 'value_previous' => 20.0],
    ]);

    $products = collect([
        'p1' => (object) ['id' => 'p1', 'name' => 'Product 1', 'ean' => '111', 'image_url' => null, 'category_id' => 'c1', 'category' => (object) ['name' => 'Cat 1']],
        'p2' => (object) ['id' => 'p2', 'name' => 'Product 2', 'ean' => '222', 'image_url' => null, 'category_id' => 'c1', 'category' => (object) ['name' => 'Cat 1']],
    ]);

    $result = $method->invoke($service, $salesData, $products);

    // p2 has 10% share (below median ~50%), negative growth → dog
    $p2 = $result->firstWhere('product_id', 'p2');
    expect($p2['quadrant'])->toBe('dog');
});

it('classifies question_mark when low share and positive growth', function () {
    $service = new BcgMatrixService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('classifyQuadrants');
    $method->setAccessible(true);

    // p2 small but growing fast
    $salesData = collect([
        (object) ['product_id' => 'p1', 'value_current' => 90.0, 'value_previous' => 100.0],
        (object) ['product_id' => 'p2', 'value_current' => 10.0, 'value_previous' => 5.0],
    ]);

    $products = collect([
        'p1' => (object) ['id' => 'p1', 'name' => 'Product 1', 'ean' => '111', 'image_url' => null, 'category_id' => 'c1', 'category' => (object) ['name' => 'Cat 1']],
        'p2' => (object) ['id' => 'p2', 'name' => 'Product 2', 'ean' => '222', 'image_url' => null, 'category_id' => 'c1', 'category' => (object) ['name' => 'Cat 1']],
    ]);

    $result = $method->invoke($service, $salesData, $products);

    $p2 = $result->firstWhere('product_id', 'p2');
    expect($p2['quadrant'])->toBe('question_mark');
});

it('classifies cash_cow when high share and negative growth', function () {
    $service = new BcgMatrixService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('classifyQuadrants');
    $method->setAccessible(true);

    // p1 dominates but declining
    $salesData = collect([
        (object) ['product_id' => 'p1', 'value_current' => 80.0, 'value_previous' => 100.0],
        (object) ['product_id' => 'p2', 'value_current' => 20.0, 'value_previous' => 10.0],
    ]);

    $products = collect([
        'p1' => (object) ['id' => 'p1', 'name' => 'Product 1', 'ean' => '111', 'image_url' => null, 'category_id' => 'c1', 'category' => (object) ['name' => 'Cat 1']],
        'p2' => (object) ['id' => 'p2', 'name' => 'Product 2', 'ean' => '222', 'image_url' => null, 'category_id' => 'c1', 'category' => (object) ['name' => 'Cat 1']],
    ]);

    $result = $method->invoke($service, $salesData, $products);

    $p1 = $result->firstWhere('product_id', 'p1');
    expect($p1['quadrant'])->toBe('cash_cow');
});

it('calculates date range midpoint correctly for monthly_summaries', function () {
    $service = new BcgMatrixService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('calculateDateRange');
    $method->setAccessible(true);

    $filters = [
        'month_from' => '2024-01-01',
        'month_to' => '2024-06-30',
    ];

    $result = $method->invoke($service, $filters, 'monthly_summaries');

    expect($result)->toHaveKeys(['from', 'to', 'midpoint']);
    expect($result['midpoint'])->toBeInstanceOf(\Carbon\Carbon::class);
    expect($result['midpoint']->format('Y-m-d'))->toBe('2024-03-31');
});

it('returns empty collection when analyzeByEans receives empty array', function () {
    $service = new BcgMatrixService;

    $result = $service->analyzeByEans([], 'monthly_summaries', ['client_id' => 'test']);

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result->isEmpty())->toBeTrue();
});
