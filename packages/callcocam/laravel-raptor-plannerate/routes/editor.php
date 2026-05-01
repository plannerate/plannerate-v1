<?php

/**
 * Rotas de API do tenant (Editor, Produtos).
 * Carregado em LaravelRaptorPlannerateServiceProvider no domínio {subdomain}.{landlord_domain},
 * com middleware web, auth, NeedsTenant e SetPermissionTeamContext (igual routes/web.php do tenant).
 */

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductDetailsController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Api\ProductImageController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\LayerController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\PlanogramApiController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductDimensionController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductSalesController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SaveChangesController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->group(function () {
    Route::get('products/details/{ean}', [ProductDetailsController::class, 'show'])->name('products.details');
    // ========== Product Image API (rota fixa antes das com {product} para não confundir com segmento) ==========
    Route::post('products/update-image', [ProductImageController::class, 'update'])
        ->name('products.update-image');

    Route::post('products/{product}/upload-image', [ProductImageController::class, 'uploadImage'])
        ->name('products.upload-image')
        ->withTrashed();

    Route::delete('products/{product}/delete-image', [ProductImageController::class, 'deleteImage'])
        ->name('products.delete-image')
        ->withTrashed();

    // Editor API Routes - Gondolas
    Route::post('editor/planograms/{planogram}/gondolas', [GondolaController::class, 'store'])
        ->name('editor.gondolas.store');

    Route::put('editor/gondolas/{gondola}', [GondolaController::class, 'update'])
        ->name('editor.gondolas.update');

    Route::delete('editor/gondolas/{gondola}', [GondolaController::class, 'destroy'])
        ->name('editor.gondolas.destroy');

    Route::get('editor/gondolas/{gondola}/sections', [GondolaController::class, 'sections'])
        ->name('editor.gondolas.sections');

    Route::get('plannograma/{planogram}/editor/gondolas/{gondola}/products', [GondolaController::class, 'products'])
        ->name('editor.gondolas.products');

    Route::post('editor/gondolas/{gondola}/update-images', [GondolaController::class, 'updateImages'])
        ->name('editor.gondolas.update-images');

    Route::get('editor/categories', [CategoryController::class, 'index'])
        ->name('editor.categories.index');

    Route::get('editor/{categoryId}/categories', [CategoryController::class, 'index'])
        ->name('editor.categories.show');

    // Editor API Routes - Sections
    Route::get('editor/sections/{section}', [SectionController::class, 'show'])
        ->name('editor.sections.show');
    Route::post('editor/gondolas/{gondola}/sections', [SectionController::class, 'store'])
        ->name('editor.sections.store');
    Route::put('editor/sections/{id}', [SectionController::class, 'update'])
        ->name('editor.sections.update');
    Route::delete('editor/sections/{section}', [SectionController::class, 'destroy'])
        ->name('editor.sections.destroy');
    Route::post('editor/sections/{section}/transfer', [SectionController::class, 'transfer'])
        ->name('editor.sections.transfer');

    // Editor API Routes - Planograms & Gondolas
    Route::get('editor/planograms', [PlanogramApiController::class, 'index'])
        ->name('editor.planograms.index');
    Route::get('editor/planograms/{planogram}/gondolas', [PlanogramApiController::class, 'gondolas'])
        ->name('editor.planograms.gondolas');

    // Editor API Routes - Shelves
    Route::post('editor/sections/{section}/shelves', [ShelfController::class, 'store'])
        ->name('editor.shelves.store');
    Route::put('editor/shelves/{id}', [ShelfController::class, 'update'])
        ->name('editor.shelves.update');
    Route::delete('editor/shelves/{shelf}', [ShelfController::class, 'destroy'])
        ->name('editor.shelves.destroy');

    // Editor API Routes - Segments
    Route::put('editor/segments/{id}', [SegmentController::class, 'update'])
        ->name('editor.segments.update');

    // Editor API Routes - Layers
    Route::put('editor/layers/{id}', [LayerController::class, 'update'])
        ->name('editor.layers.update');
    Route::delete('editor/layers/{layer}', [LayerController::class, 'destroy'])
        ->name('editor.layers.destroy');

    // Gondola Save Changes (Delta/Diff)
    Route::post('editor/gondolas/{gondola}/save-changes', SaveChangesController::class)
        ->name('editor.gondolas.save-changes');

    // Product Dimensions API
    Route::post('plannograma/{planogram}/products/{product}/dimensions', [ProductDimensionController::class, 'update'])
        ->name('editor.products.dimensions.update');

    // Product Sales Summary API
    Route::get('plannerate/products/{product}/sales/summary', [ProductSalesController::class, 'summary'])
        ->name('plannerate.products.sales.summary');

    // Performance Analysis API - ABC e Estoque Alvo
    Route::post('editor/gondolas/{gondola}/analysis/abc', [GondolaAnalysisController::class, 'calculateAbcApi'])
        ->name('editor.gondolas.analysis.abc');
    Route::post('editor/gondolas/{gondola}/analysis/target-stock', [GondolaAnalysisController::class, 'calculateTargetStockApi'])
        ->name('editor.gondolas.analysis.target-stock');
    Route::delete('editor/gondolas/{gondola}/analysis', [GondolaAnalysisController::class, 'clearAnalysisApi'])
        ->name('editor.gondolas.analysis.clear');
});
