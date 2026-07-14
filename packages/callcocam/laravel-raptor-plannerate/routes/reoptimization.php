<?php

/**
 * Rotas da reotimização contínua: cadência por gôndola, disparo sob demanda, tela de revisão
 * da proposta e a decisão (aprovar/rejeitar).
 *
 * Mesmo grupo/middlewares das rotas de geração (sem tenant.client.redirect).
 */

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Reoptimization\GondolaReoptimizationController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Reoptimization\ReoptimizationApprovalController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Reoptimization\ReoptimizationInboxController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Reoptimization\ReoptimizationProposalPageController;
use Illuminate\Support\Facades\Route;

// Fila de propostas aguardando decisão (todas as gôndolas).
Route::get('reoptimization', [ReoptimizationInboxController::class, 'index'])
    ->name('planograms.reoptimization.index');

// Tela de revisão do diff. Nome `planograms.reoptimization.show` para casar com o padrão das
// demais páginas do editor.
Route::get('editor/reoptimization/{proposal}', [ReoptimizationProposalPageController::class, 'show'])
    ->name('planograms.reoptimization.show');

Route::prefix('api')->name('api.')->group(function (): void {
    Route::post('reoptimization/{proposal}/approve', [ReoptimizationApprovalController::class, 'approve'])
        ->name('reoptimization.approve');
    Route::post('reoptimization/{proposal}/reject', [ReoptimizationApprovalController::class, 'reject'])
        ->name('reoptimization.reject');

    Route::put('gondolas/{gondola}/reoptimization', [GondolaReoptimizationController::class, 'updateCadence'])
        ->name('gondolas.reoptimization.update');
    Route::post('gondolas/{gondola}/reoptimization/run-now', [GondolaReoptimizationController::class, 'runNow'])
        ->name('gondolas.reoptimization.run-now');
    Route::get('gondolas/{gondola}/reoptimization/pending', [GondolaReoptimizationController::class, 'pending'])
        ->name('gondolas.reoptimization.pending');
});
