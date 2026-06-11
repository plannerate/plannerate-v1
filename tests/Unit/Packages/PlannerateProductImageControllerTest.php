<?php

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController;
use League\Flysystem\UnableToWriteFile;

test('uploadImage resolve produto por string id e não usa binding implícito do modelo', function (): void {
    $method = new ReflectionMethod(ProductImageController::class, 'uploadImage');
    $parameters = $method->getParameters();

    // Assinatura atual: (request, product) — sem parâmetro de subdomain
    // (as rotas do editor são registradas sem domínio para funcionar no host do tenant)
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

test('mensagem de erro de upload diferencia falha de escrita no disco', function (): void {
    $controller = new ProductImageController;
    $method = new ReflectionMethod(ProductImageController::class, 'resolveUploadExceptionMessage');
    $method->setAccessible(true);

    $message = $method->invoke($controller, UnableToWriteFile::atLocation('products/example'));

    expect($message)->toContain('Nao foi possivel salvar a imagem no armazenamento');
});

test('mensagem de erro de upload expõe a mensagem da exceção quando existir', function (): void {
    $controller = new ProductImageController;
    $method = new ReflectionMethod(ProductImageController::class, 'resolveUploadExceptionMessage');
    $method->setAccessible(true);

    $message = $method->invoke($controller, new RuntimeException('boom'));

    expect($message)->toBe('boom');
});

test('mensagem de erro de upload usa fallback quando a exceção não tem mensagem', function (): void {
    $controller = new ProductImageController;
    $method = new ReflectionMethod(ProductImageController::class, 'resolveUploadExceptionMessage');
    $method->setAccessible(true);

    $message = $method->invoke($controller, new RuntimeException(''));

    expect($message)->toContain('Erro inesperado ao processar o upload da imagem');
});
