<?php

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController;
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

// Relatórios de gôndola (Excel/PDF) — consultam dados do tenant, exigem auth e contexto de tenant
Route::controller(GondolaReportController::class)
    ->prefix('export/gondola-report')
    ->name('export.gondola-report.')
    ->middleware(['auth', 'tenant.client.redirect'])
    ->group(function () {
        Route::get('{gondola}/excel', 'generateExcelReport')->name('excel');
        Route::get('{gondola}/pdf', 'generatePdfReport')->name('pdf');
        Route::get('{gondola}/compra', 'generateCompraReport')->name('compra');
        Route::get('{gondola}/dimensao', 'generateDimensaoReport')->name('dimensao');
        Route::get('{gondola}/image', 'generateImageReport')->name('image');
        Route::get('{gondola}/data', 'getReportData')->name('data');
    });
