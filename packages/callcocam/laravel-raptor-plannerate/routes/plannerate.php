<?php

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Client\GondolaClientController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Plannerate\KanbanController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Plannerate\MapController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\GondolaAnalysisController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Workflow\GondolaWorkflowController;
use Illuminate\Support\Facades\Route;

/**
 * Plannerate Routes
 * Rotas para visualizações de Planogramas (Lista, Kanban, Maps) e detalhe de workflow.
 * Ações de execução ficam centralizadas no pacote laravel-raptor-flow via flow.execution.*.
 */
Route::middleware(['auth', 'verified'])->group(function () {

    // Kanban View (define o contexto do fluxo: gôndolas; no futuro pode ser campanhas, compras, etc.)
    Route::get('/kanbans/{flow:slug}', [KanbanController::class, 'index'])->name('kanbans.index');

    // Maps View
    Route::get('/maps', [MapController::class, 'index'])->name('maps.index');

    // Detalhe do workflow de uma gôndola (página de timeline)
    Route::get('/workflow/gondola/{gondola}', [GondolaWorkflowController::class, 'show'])
        ->name('gondola.workflow.show')
        ->scopeBindings();
});

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
