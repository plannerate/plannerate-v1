<?php

use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;
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

/*
 * Rastreamento de largura chutada — Fase 1 do plano de precisão
 * (docs/gondola-precisao-automatica/). O fallback de 10cm é a causa silenciosa nº1 de
 * gôndola que "não fecha": o motor empacota com um palpite e o resultado não bate com a
 * prateleira real. Antes isso só existia no log; agora vai para o relatório da geração.
 */

test('largura válida não conta como fallback', function (): void {
    $resolver = new ProductWidthResolver;
    $resolver->resolve(productWithWidth(7.5));

    expect($resolver->fallbackProducts())->toBeEmpty();
});

test('produto sem largura cadastrada é rastreado (antes passava totalmente mudo)', function (): void {
    $resolver = new ProductWidthResolver;
    $resolver->resolve(productWithoutWidth());

    $fallbacks = $resolver->fallbackProducts();

    expect($fallbacks)->toHaveCount(1)
        ->and($fallbacks[0]['reason'])->toBe('missing')
        ->and($fallbacks[0]['width_used'])->toBe(10.0)
        ->and($fallbacks[0]['width_raw'])->toBeNull();
});

test('largura zero e largura implausível são rastreadas com o motivo correto', function (): void {
    $resolver = new ProductWidthResolver;

    $zero = productWithWidth(0);
    $zero->id = 'zero';
    $absurd = productWithWidth(293);
    $absurd->id = 'absurdo';

    $resolver->resolve($zero);
    $resolver->resolve($absurd);

    $reasons = collect($resolver->fallbackProducts())->pluck('reason', 'product_id')->all();

    expect($reasons)->toBe(['zero' => 'invalid', 'absurdo' => 'implausible']);
});

test('o mesmo produto suspeito não é contado duas vezes', function (): void {
    $resolver = new ProductWidthResolver;
    $product = productWithWidth(0);

    $resolver->resolve($product);
    $resolver->resolve($product);

    expect($resolver->fallbackProducts())->toHaveCount(1);
});

test('reset zera o rastreamento entre gerações', function (): void {
    // O resolver é singleton e o worker atende várias gerações no mesmo processo: sem
    // reset, os produtos suspeitos de uma gôndola vazariam para o relatório da seguinte.
    $resolver = new ProductWidthResolver;
    $resolver->resolve(productWithWidth(0));

    expect($resolver->fallbackProducts())->toHaveCount(1);

    $resolver->reset();

    expect($resolver->fallbackProducts())->toBeEmpty();
});
