<?php

/**
 * Rotas de API do tenant (Editor, Produtos).
 * Carregado em routes/web.php dentro do group com middleware web, auth e contexto.
 * Todas as rotas aqui exigem autenticação e contexto tenant.
 */

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Api\ProductImageController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->group(function () {
    Route::get('products/details/{ean}', [ProductDetailsController::class, 'show'])->name('products.details');
    // ========== Product Image API (rota fixa antes das com {product} para não confundir com segmento) ==========
    Route::post('products/update-image', [ProductImageController::class, 'update'])
        ->name('products.update-image');

    Route::post('products/{product}/upload-image', [ProductImageController::class, 'uploadImage'])
        ->name('products.upload-image');

    Route::delete('products/{product}/delete-image', [ProductImageController::class, 'deleteImage'])
        ->name('products.delete-image');

    // Editor API Routes - Gondolas
    Route::post('editor/planograms/{planogram}/gondolas', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\GondolaController::class, 'store'])
        ->name('editor.gondolas.store');
    Route::put('editor/gondolas/{gondola}', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\GondolaController::class, 'update'])
        ->name('editor.gondolas.update');
    Route::delete('editor/gondolas/{gondola}', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\GondolaController::class, 'destroy'])
        ->name('editor.gondolas.destroy');
    Route::get('editor/gondolas/{gondola}/sections', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\GondolaController::class, 'sections'])
        ->name('editor.gondolas.sections');
    Route::get('plannograma/{planogram}/editor/gondolas/{gondola}/products', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\GondolaController::class, 'products'])
        ->name('editor.gondolas.products');
    Route::post('editor/gondolas/{gondola}/update-images', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\GondolaController::class, 'updateImages'])
        ->name('editor.gondolas.update-images');

    Route::get('editor/categories', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\CategoryController::class, 'index'])
        ->name('editor.categories.index');
    Route::get('editor/{categoryId}/categories', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\CategoryController::class, 'index'])
        ->name('editor.categories.show');

    // Editor API Routes - Sections
    Route::get('editor/sections/{section}', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\SectionController::class, 'show'])
        ->name('editor.sections.show');
    Route::post('editor/gondolas/{gondola}/sections', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\SectionController::class, 'store'])
        ->name('editor.sections.store');
    Route::put('editor/sections/{id}', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\SectionController::class, 'update'])
        ->name('editor.sections.update');
    Route::delete('editor/sections/{section}', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\SectionController::class, 'destroy'])
        ->name('editor.sections.destroy');
    Route::post('editor/sections/{section}/transfer', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\SectionController::class, 'transfer'])
        ->name('editor.sections.transfer');

    // Editor API Routes - Planograms & Gondolas
    Route::get('editor/planograms', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\PlanogramApiController::class, 'index'])
        ->name('editor.planograms.index');
    Route::get('editor/planograms/{planogram}/gondolas', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\PlanogramApiController::class, 'gondolas'])
        ->name('editor.planograms.gondolas');

    // Editor API Routes - Shelves
    Route::post('editor/sections/{section}/shelves', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\ShelfController::class, 'store'])
        ->name('editor.shelves.store');
    Route::put('editor/shelves/{id}', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\ShelfController::class, 'update'])
        ->name('editor.shelves.update');
    Route::delete('editor/shelves/{shelf}', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\ShelfController::class, 'destroy'])
        ->name('editor.shelves.destroy');

    // Editor API Routes - Segments
    Route::put('editor/segments/{id}', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\SegmentController::class, 'update'])
        ->name('editor.segments.update');

    // Editor API Routes - Layers
    Route::put('editor/layers/{id}', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\LayerController::class, 'update'])
        ->name('editor.layers.update');
    Route::delete('editor/layers/{layer}', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\LayerController::class, 'destroy'])
        ->name('editor.layers.destroy');

    // Gondola Save Changes (Delta/Diff)
    Route::post('editor/gondolas/{gondola}/save-changes', \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\SaveChangesController::class)
        ->name('editor.gondolas.save-changes');

    // Product Dimensions API
    Route::post('plannograma/{planogram}/products/{product}/dimensions', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\ProductDimensionController::class, 'update'])
        ->name('editor.products.dimensions.update');

    // Product Sales Summary API
    Route::get('plannerate/products/{product}/sales/summary', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\Editor\ProductSalesController::class, 'summary'])
        ->name('plannerate.products.sales.summary');

    // Performance Analysis API - ABC e Estoque Alvo
    Route::post('editor/gondolas/{gondola}/analysis/abc', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\GondolaAnalysisController::class, 'calculateAbcApi'])
        ->name('editor.gondolas.analysis.abc');
    Route::post('editor/gondolas/{gondola}/analysis/target-stock', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\GondolaAnalysisController::class, 'calculateTargetStockApi'])
        ->name('editor.gondolas.analysis.target-stock');
    Route::delete('editor/gondolas/{gondola}/analysis', [\Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\GondolaAnalysisController::class, 'clearAnalysisApi'])
        ->name('editor.gondolas.analysis.clear');
});
