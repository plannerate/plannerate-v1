<?php

use App\Http\Middleware\SetPermissionTeamContext;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController;
use Illuminate\Support\Facades\Route;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;

test('rota de upload de imagem do editor é registrada sem restrição de domínio', function (): void {
    $route = Route::getRoutes()->getByName('api.products.upload-image');

    expect($route)->not->toBeNull();

    // Sem domínio: registrar apenas no host central faria o host do tenant
    // retornar 404 (ver comentário em registerEditorApiRoutes do provider)
    expect($route->getDomain())->toBeNull();

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

test('rota save-changes do editor gera URL pelo path sem exigir subdomain', function (): void {
    $url = route('api.editor.gondolas.save-changes', [
        'gondola' => '01fakegondolaidxxxxxxxx',
    ], false);

    expect($url)->toBe('/api/editor/gondolas/01fakegondolaidxxxxxxxx/save-changes');
});
