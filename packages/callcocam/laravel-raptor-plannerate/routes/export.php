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
        // Geração enfileirada (mutação) → POST. O job notifica com link ao concluir.
        Route::post('{gondola}/excel', 'generateExcelReport')->name('excel');
        Route::post('{gondola}/pdf', 'generatePdfReport')->name('pdf');
        Route::post('{gondola}/compra', 'generateCompraReport')->name('compra');
        Route::post('{gondola}/dimensao', 'generateDimensaoReport')->name('dimensao');
        Route::post('{gondola}/image', 'generateImageReport')->name('image');
        // Preview de dados (leitura) → permanece GET.
        Route::get('{gondola}/data', 'getReportData')->name('data');
        Route::get('{gondola}/planogram-pdf', 'generatePlanogramRowPdf')->name('planogram');
        Route::get('{gondola}/planogram-modules-pdf', 'generatePlanogramModulesPdf')->name('planogram-modules');
    });
