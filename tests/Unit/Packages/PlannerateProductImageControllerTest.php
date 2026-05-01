<?php

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController;

test('uploadImage resolve produto por string id e não usa binding implícito do modelo', function (): void {
    $method = new ReflectionMethod(ProductImageController::class, 'uploadImage');
    $parameters = $method->getParameters();

    expect($parameters)->toHaveCount(2);
    expect($parameters[0]->getName())->toBe('request');
    expect($parameters[1]->getName())->toBe('product');
    expect($parameters[1]->getType()?->__toString())->toBe('string');
});

test('deleteImage resolve produto por string id e não usa findOrFail implícito', function (): void {
    $method = new ReflectionMethod(ProductImageController::class, 'deleteImage');
    $parameters = $method->getParameters();

    expect($parameters)->toHaveCount(1);
    expect($parameters[0]->getName())->toBe('product');
    expect($parameters[0]->getType()?->__toString())->toBe('string');
});
