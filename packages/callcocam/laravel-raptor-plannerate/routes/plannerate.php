<?php

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaClientController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaAnalysisController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::prefix('client/gondola')->name('client.gondola.')->group(function () {
        Route::get('{gondola}', [GondolaClientController::class, 'show'])->name('show');
        Route::get('{gondola}/section/{section}', [GondolaClientController::class, 'showSection'])->name('section.show');
    });

    Route::get('/gondolas/{gondola}/analysis/abc', [GondolaAnalysisController::class, 'calculateAbc'])
        ->name('tenant.gondolas.analysis.abc');

    Route::get('/gondolas/{gondola}/analysis/target-stock', [GondolaAnalysisController::class, 'calculateTargetStock'])
        ->name('tenant.gondolas.analysis.target-stock');
});
