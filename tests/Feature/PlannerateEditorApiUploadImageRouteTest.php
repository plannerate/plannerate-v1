<?php

use App\Http\Middleware\SetPermissionTeamContext;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController;
use Illuminate\Support\Facades\Route;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;

test('rota de upload de imagem do editor fica no domínio do tenant', function (): void {
    $route = Route::getRoutes()->getByName('api.products.upload-image');

    expect($route)->not->toBeNull();

    expect($route->getDomain())->toBe(sprintf('{subdomain}.%s', config('app.landlord_domain')));

    expect($route->uri())->toBe('api/products/{product}/upload-image');

    expect($route->getAction('controller'))->toBe(ProductImageController::class.'@uploadImage');

    $middleware = collect($route->gatherMiddleware())->values()->all();

    expect($middleware)->toContain('web');
    expect($middleware)->toContain('auth');
    expect($middleware)->toContain(NeedsTenant::class);
    expect($middleware)->toContain(SetPermissionTeamContext::class);

    expect($route->allowsTrashedBindings())->toBeTrue();
});

test('rota delete-image do editor permite binding com produto soft-deleted', function (): void {
    $route = Route::getRoutes()->getByName('api.products.delete-image');

    expect($route)->not->toBeNull();
    expect($route->allowsTrashedBindings())->toBeTrue();
});

test('rota save-changes do editor gera URL quando subdomain é informado', function (): void {
    $url = route('api.editor.gondolas.save-changes', [
        'subdomain' => 'acme-tenant',
        'gondola' => '01fakegondolaidxxxxxxxx',
    ], false);

    expect($url)->toBe('/api/editor/gondolas/01fakegondolaidxxxxxxxx/save-changes');
});
