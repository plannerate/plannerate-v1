<?php

use App\Models\Product;

test('grouping não é mais sincronizado de sortiment_attribute', function (): void {
    $product = new Product;
    $product->sortiment_attribute = 'CEREAIS | FARINÁCEOS | FAROFA DE MANDIOCA';

    expect($product->grouping)->toBeNull()
        ->and($product->grouping_normalized)->toBeNull();
});

test('sortiment_attribute é atribuível no product', function (): void {
    $product = new Product;
    $product->sortiment_attribute = 'HIGIENE PESSOAL';

    expect($product->sortiment_attribute)->toBe('HIGIENE PESSOAL');
});

test('grouping e grouping_normalized não estão no fillable do product', function (): void {
    $fillable = (new Product)->getFillable();

    expect($fillable)->not->toContain('grouping')
        ->and($fillable)->not->toContain('grouping_normalized');
});

test('category_id está no fillable do product', function (): void {
    $fillable = (new Product)->getFillable();

    expect($fillable)->toContain('category_id');
});
