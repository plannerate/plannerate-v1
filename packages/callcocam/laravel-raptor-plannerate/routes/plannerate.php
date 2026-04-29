<?php

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::prefix('tenant/gondola')->name('tenant.gondola.')->group(function () {
        Route::get('{gondola}', [GondolaTenantController::class, 'show'])->name('show');
        Route::get('{gondola}/section/{section}', [GondolaTenantController::class, 'showSection'])->name('section.show');
    });

    Route::get('/gondolas/{gondola}/analysis/abc', [GondolaAnalysisController::class, 'calculateAbc'])
        ->name('tenant.gondolas.analysis.abc');

    Route::get('/gondolas/{gondola}/analysis/target-stock', [GondolaAnalysisController::class, 'calculateTargetStock'])
        ->name('tenant.gondolas.analysis.target-stock');
});
