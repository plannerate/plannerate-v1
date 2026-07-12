<?php

/**
 * Rotas da API interna do Auto-Planograma (geração, rejeitados, reorder/redistribute,
 * overrides por categoria). Movidas de routes/tenant.php do app para o pacote na
 * Etapa 6 da refatoração — URIs, nomes e middlewares preservados.
 *
 * Não passam pelo middleware tenant.client.redirect (mesmo contrato original).
 */

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\AutoPlanogramController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\GondolaSlotOverrideController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->group(function (): void {
    Route::post('gondolas/{gondola}/auto-generate', [AutoPlanogramController::class, 'generate'])
        ->name('gondolas.auto-generate');
    Route::get('gondolas/{gondola}/rejected-products', [AutoPlanogramController::class, 'rejectedProducts'])
        ->name('gondolas.rejected-products');
    Route::get('gondolas/{gondola}/template-groupings', [AutoPlanogramController::class, 'templateGroupings'])
        ->name('gondolas.template-groupings');
    Route::delete('gondolas/{gondola}/rejected-products/{rejected}', [AutoPlanogramController::class, 'destroyRejectedProduct'])
        ->name('gondolas.rejected-products.destroy');
    Route::post('gondolas/{gondola}/swap-product', [AutoPlanogramController::class, 'swapProduct'])
        ->name('gondolas.swap-product');
    Route::post('gondolas/{gondola}/reorder-visual', [AutoPlanogramController::class, 'reorderVisual'])
        ->name('gondolas.reorder-visual');
    Route::post('gondolas/{gondola}/redistribute', [AutoPlanogramController::class, 'redistributeExposure'])
        ->name('gondolas.redistribute');
    Route::post('gondolas/{gondola}/reorder-all', [AutoPlanogramController::class, 'reorderGondola'])
        ->name('gondolas.reorder-all');
    Route::post('gondolas/{gondola}/redistribute-all', [AutoPlanogramController::class, 'redistributeGondola'])
        ->name('gondolas.redistribute-all');
    Route::post('gondolas/{gondola}/regenerate-auto', [AutoPlanogramController::class, 'regenerateAuto'])
        ->name('gondolas.regenerate-auto');

    // ── Histórico de execuções da geração (assíncrona) ───────
    Route::get('gondolas/{gondola}/generation-runs', [PlanogramGenerationRunController::class, 'index'])
        ->name('gondolas.generation-runs.index');
    Route::get('gondolas/{gondola}/generation-runs/latest', [PlanogramGenerationRunController::class, 'latest'])
        ->name('gondolas.generation-runs.latest');
    Route::get('gondolas/{gondola}/generation-runs/pending', [PlanogramGenerationRunController::class, 'pending'])
        ->name('gondolas.generation-runs.pending');
    Route::get('gondolas/{gondola}/generation-runs/{run}', [PlanogramGenerationRunController::class, 'show'])
        ->name('gondolas.generation-runs.show');

    // ── Overrides de geração por categoria ───────────────────
    Route::put('gondolas/{gondola}/generation-overrides', [GondolaSlotOverrideController::class, 'upsert'])
        ->name('gondolas.generation-overrides.upsert');
    Route::delete('gondolas/{gondola}/generation-overrides/{categoryId}', [GondolaSlotOverrideController::class, 'destroy'])
        ->name('gondolas.generation-overrides.destroy');
    Route::post('gondolas/{gondola}/generation-overrides/{categoryId}/apply-to-template', [GondolaSlotOverrideController::class, 'applyToTemplate'])
        ->name('gondolas.generation-overrides.apply-to-template');
});
