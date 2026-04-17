<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return redirect('/dashboard');
})->name('home');

$context = request()->getContext();

Route::middleware(['web', 'auth', $context])
    ->group(function () use ($context) {
        Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
            ->middleware(['auth', 'verified'])
            ->name('dashboard');

        Route::get('dashboard/workflow-report', [\App\Http\Controllers\DashboardController::class, 'workflowReportData'])
            ->middleware(['auth', 'verified'])
            ->name('dashboard.workflow-report');

        // Logs Routes
        Route::get('/logs', [\App\Http\Controllers\LogViewerController::class, 'index'])
            ->name('logs.index');
        Route::get('/logs/download', [\App\Http\Controllers\LogViewerController::class, 'download'])
            ->name('logs.download');
        Route::post('/logs/clear', [\App\Http\Controllers\LogViewerController::class, 'clear'])
            ->name('logs.clear');

        // Include Settings Routes
        require __DIR__.'/settings.php';

        // Análise ABC Standalone
        Route::get('/analysis/abc', [\App\Http\Controllers\Tenant\AbcAnalysisController::class, 'index'])
            ->name('tenant.analysis.abc.index');

        // Estoque Alvo Standalone
        Route::get('/analysis/target-stock', [\App\Http\Controllers\Tenant\TargetStockController::class, 'index'])
            ->name('tenant.analysis.target-stock.index');

        // Matriz BCG Standalone
        Route::get('/analysis/bcg', [\App\Http\Controllers\Tenant\BcgMatrixController::class, 'index'])
            ->name('tenant.analysis.bcg.index');

    });

Route::middleware(['auth'])->group(function () {
    Route::get('/generate-permissions', function () {
        $connection = config('raptor.database.landlord_connection_name', 'landlord');
        $catalog = app(\Callcocam\LaravelRaptor\Services\PermissionCatalogService::class);

        $tenant = $catalog->syncPermissionsForConnection($connection, 'tenant', true);

        $created = (int) ($tenant['created'] ?? 0);
        $updated = (int) ($tenant['updated'] ?? 0);
        $expected = (int) ($tenant['expected'] ?? 0);

        return "Permissões (tenant) sincronizadas com sucesso! Esperadas: {$expected}, criadas: {$created}, atualizadas: {$updated}.";
    })->name('generate-permissions');
});

// Rota de download de exportações
Route::get('download-export/{filename}', function ($filename) {
    $path = Storage::disk(config('raptor.export.disk', 'public'))->path('exports/'.$filename);

    if (! file_exists($path)) {
        abort(404);
    }

    return response()->download($path)->deleteFileAfterSend(true);
})->name('download.export');
