<?php

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaExportController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\GondolaPdfPreviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('export/gondola')
    ->name('export.gondola.')
    ->middleware(['auth'])
    ->group(function () {
        Route::get('{gondola}/view', [GondolaPdfPreviewController::class, 'show'])->name('view');

        Route::get('{gondola}/qr-code', [GondolaExportController::class, 'generateQrCode'])->name('qrcode');
        Route::get('section/{section}/qr-code', [GondolaExportController::class, 'generateSectionQrCode'])->name('section.qrcode');

        Route::get('{gondola}/report', [GondolaExportController::class, 'exportReport'])->name('report');
    });
