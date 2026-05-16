<?php

use App\Services\AutoPlanogram\ProductWidthResolver;
use Illuminate\Support\Facades\Log;

function productWithWidth(mixed $width): object
{
    return new class($width)
    {
        public string $id = 'test-id';

        public string $name = 'Produto Teste';

        public function __construct(public mixed $width) {}
    };
}

function productWithoutWidth(): object
{
    return new class
    {
        public string $id = 'test-id';

        public string $name = 'Produto Teste';
    };
}

test('null width returns default fallback silently', function (): void {
    Log::spy();
    $resolver = new ProductWidthResolver;

    $result = $resolver->resolve(productWithoutWidth());

    expect($result)->toBe(10.0);
    Log::shouldNotHaveReceived('warning');
});

test('width zero returns default fallback with warning', function (): void {
    Log::spy();
    $resolver = new ProductWidthResolver;

    $result = $resolver->resolve(productWithWidth(0));

    expect($result)->toBe(10.0);
    Log::shouldHaveReceived('warning')->once();
});

test('negative width returns default fallback with warning', function (): void {
    Log::spy();
    $resolver = new ProductWidthResolver;

    $result = $resolver->resolve(productWithWidth(-5));

    expect($result)->toBe(10.0);
    Log::shouldHaveReceived('warning')->once();
});

test('valid width is returned as-is', function (): void {
    $resolver = new ProductWidthResolver;

    $result = $resolver->resolve(productWithWidth(12.5));

    expect($result)->toBe(12.5);
});

test('width exactly at threshold is valid', function (): void {
    $resolver = new ProductWidthResolver;

    $result = $resolver->resolve(productWithWidth(60));

    expect($result)->toBe(60.0);
});

test('width above threshold returns default fallback with warning', function (): void {
    Log::spy();
    $resolver = new ProductWidthResolver;

    $result = $resolver->resolve(productWithWidth(61));

    expect($result)->toBe(10.0);
    Log::shouldHaveReceived('warning')->once();
});

test('absurd width 293 returns default fallback with warning', function (): void {
    Log::spy();
    $resolver = new ProductWidthResolver;

    $result = $resolver->resolve(productWithWidth(293));

    expect($result)->toBe(10.0);
    Log::shouldHaveReceived('warning')->once();
});

test('resolveAll returns fallback only for invalid widths', function (): void {
    $resolver = new ProductWidthResolver;

    $p1 = productWithWidth(12.5);
    $p1->id = 'valid-1';
    $p2 = productWithWidth(0);
    $p2->id = 'zero';
    $p3 = productWithWidth(293);
    $p3->id = 'absurd';
    $p4 = productWithWidth(30.0);
    $p4->id = 'valid-2';

    $result = $resolver->resolveAll(collect([$p1, $p2, $p3, $p4]));

    expect($result)->toBe([
        'valid-1' => 12.5,
        'zero' => 10.0,
        'absurd' => 10.0,
        'valid-2' => 30.0,
    ]);
});

test('custom thresholds are respected', function (): void {
    $resolver = new ProductWidthResolver(defaultWidth: 5.0, maxPlausible: 20.0);

    expect($resolver->resolve(productWithWidth(20)))->toBe(20.0)
        ->and($resolver->resolve(productWithWidth(21)))->toBe(5.0)
        ->and($resolver->resolve(productWithWidth(0)))->toBe(5.0);
});
