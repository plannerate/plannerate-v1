<?php

use App\Models\Product;
use Illuminate\Support\Str;

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

test('sortiment_attribute sincroniza grouping ao salvar', function (): void {
    $product = new Product;
    $product->name = 'Produto de teste';
    $product->grouping = 'valor manual';
    $product->sortiment_attribute = 'CEREAIS | FARINÁCEOS | FAROFA DE MANDIOCA';
    $product->syncGroupingFromSortimentAttribute();

    expect($product->grouping)->toBe('CEREAIS | FARINÁCEOS | FAROFA DE MANDIOCA')
        ->and($product->grouping_normalized)->toBe(Str::slug('CEREAIS | FARINÁCEOS | FAROFA DE MANDIOCA'));
});

test('sortiment_attribute sincroniza grouping ao atualizar', function (): void {
    $product = new Product;
    $product->name = 'Produto de teste';
    $product->sortiment_attribute = 'BEBIDAS';
    $product->syncGroupingFromSortimentAttribute();

    $product->sortiment_attribute = 'HIGIENE PESSOAL';
    $product->syncGroupingFromSortimentAttribute();

    expect($product->grouping)->toBe('HIGIENE PESSOAL')
        ->and($product->grouping_normalized)->toBe(Str::slug('HIGIENE PESSOAL'));
});
