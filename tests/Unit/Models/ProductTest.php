<?php

use App\Models\Product;

test('grouping_normalized é derivado automaticamente ao setar grouping', function (): void {
    $product = new Product;
    $product->grouping = 'CEREAIS | FARINÁCEOS | FAROFA DE MANDIOCA';

    expect($product->grouping)->toBe('CEREAIS | FARINÁCEOS | FAROFA DE MANDIOCA')
        ->and($product->grouping_normalized)->toBe('cereais | farináceos | farofa de mandioca');
});

test('grouping null resulta em grouping_normalized null', function (): void {
    $product = new Product;
    $product->grouping = null;

    expect($product->grouping_normalized)->toBeNull();
});

test('grouping string vazia resulta em grouping_normalized null', function (): void {
    $product = new Product;
    $product->grouping = '';

    expect($product->grouping_normalized)->toBeNull();
});

test('espaços extras são colapsados na normalização', function (): void {
    $product = new Product;
    $product->grouping = 'CEREAIS  |  FARINÁCEOS';

    expect($product->grouping_normalized)->toBe('cereais | farináceos');
});

test('trim é aplicado na normalização', function (): void {
    $product = new Product;
    $product->grouping = '  CEREAIS  ';

    expect($product->grouping_normalized)->toBe('cereais');
});
