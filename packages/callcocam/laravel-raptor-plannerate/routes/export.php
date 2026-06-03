<?php

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaExportController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaPdfPreviewController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaShareController;
use Illuminate\Support\Facades\Route;

Route::prefix('export/gondola')
    ->name('export.gondola.')
    ->middleware(['auth', 'tenant.client.redirect'])
    ->group(function () {
        Route::get('{gondola}/view', [GondolaPdfPreviewController::class, 'show'])->name('view');

        Route::get('{gondola}/qr-code', [GondolaExportController::class, 'generateQrCode'])->name('qrcode');
        Route::get('section/{section}/qr-code', [GondolaExportController::class, 'generateSectionQrCode'])->name('section.qrcode');

        Route::get('{gondola}/report', [GondolaExportController::class, 'exportReport'])->name('report');
    });

// Rota pública — sem auth, acessível via link direto para repositores e fornecedores
Route::prefix('gondola')
    ->name('gondola.')
    ->group(function () {
        Route::get('{gondolaId}/share', [GondolaShareController::class, 'show'])->name('share');
    });
