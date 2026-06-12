<?php

// ── TENANT (rotas que exigem tenant ativo) ────────────────────

use App\Http\Controllers\Settings;
use App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\Products\DimensionApprovalController;
use App\Http\Middleware\SetPermissionTeamContext;
use App\Support\Modules\ModuleSlug;
use Illuminate\Support\Facades\Route;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;

// ── EDITOR & AUTO-PLANOGRAM (sem redirect de client) ─────────
// Rotas do editor visual e API interna do auto-planograma.
// Não passam pelo middleware tenant.client.redirect.
Route::middleware(['web', 'auth', NeedsTenant::class, SetPermissionTeamContext::class])
    ->name('tenant.')
    ->group(function (): void {

        // ── Editor de planograma ──────────────────────────────
        Route::get('editor/planograms', [Tenant\Editor\ClientPlanogramController::class, 'index'])
            ->name('editor.planograms.index');
        Route::get('editor/planograms/{planogram}/gondolas', [Tenant\Editor\ClientPlanogramController::class, 'gondolas'])
            ->name('editor.planograms.gondolas');
        Route::get('editor/planograms/{record}/gondolas/editor', [Tenant\Editor\EditorPlanogramController::class, 'edit'])
            ->name('planograms.gondolas.editor');

    });

// ── TENANT PRINCIPAL ──────────────────────────────────────────
// Todas as rotas abaixo passam pelo middleware tenant.client.redirect,
// que bloqueia usuários do tipo "client" de acessar áreas restritas.
Route::middleware(['web', 'auth', NeedsTenant::class, SetPermissionTeamContext::class, 'tenant.client.redirect'])
    ->name('tenant.')
    ->group(function (): void {

        // ── Dashboard ─────────────────────────────────────────
        Route::get('/', [Tenant\DashboardController::class, 'index'])->name('dashboard');

        // ── Categories ───────────────────────────────────────
        Route::get('categories/cascade/children', [Tenant\CategoryController::class, 'cascadeChildren'])
            ->name('categories.cascade.children');
        Route::get('categories/cascade/path', [Tenant\CategoryController::class, 'cascadePath'])
            ->name('categories.cascade.path');
        Route::post('categories/import', [Tenant\CategoryController::class, 'import'])
            ->name('categories.import');
        Route::get('categories/export/template', [Tenant\CategoryController::class, 'exportTemplate'])
            ->name('categories.export.template');
        Route::get('categories/export/data', [Tenant\CategoryController::class, 'exportData'])
            ->name('categories.export.data');
        Route::resource('categories', Tenant\CategoryController::class)
            ->except(['show'])
            ->names('categories');

        // ── Products ─────────────────────────────────────────
        Route::resource('products', Tenant\ProductController::class)
            ->except(['show'])
            ->names('products');
        Route::get('products/{product}/sales', [Tenant\ProductController::class, 'sales'])
            ->name('products.sales');
        Route::get('products/sortiment-attributes', [Tenant\ProductController::class, 'sortimentAttributes'])
            ->name('products.sortiment-attributes');
        Route::post('products/sync-single', [Tenant\ProductController::class, 'syncSingle'])
            ->name('products.sync-single');
        Route::post('products/update-images', [Tenant\ProductController::class, 'updateImages'])
            ->name('products.update-images');

        // ── Product Images ────────────────────────────────────
        Route::post('products/image/upload', [Tenant\ProductImageController::class, 'upload'])
            ->name('products.image.upload');
        Route::delete('products/image/{product}', [Tenant\ProductImageController::class, 'destroy'])
            ->name('products.image.destroy');
        Route::post('products/image/ai/process', [Tenant\ProductImageController::class, 'process'])
            ->name('products.image.ai.process');
        Route::get('products/image/ai/operations/{operation}', [Tenant\ProductImageController::class, 'status'])
            ->name('products.image.ai.status');
        Route::post('products/image/repository/fetch', [Tenant\ProductImageController::class, 'fetchFromRepository'])
            ->name('products.image.repository.fetch');

        // ── AI Dimension Approval Pipeline ───────────────────
        Route::prefix('products/dimensions')->name('products.dimensions.')->group(function (): void {
            Route::get('/', [DimensionApprovalController::class, 'index'])->name('index');
            Route::post('{product}/approve', [DimensionApprovalController::class, 'approve'])->name('approve');
            Route::post('{product}/reject', [DimensionApprovalController::class, 'reject'])->name('reject');
            Route::post('{product}/research', [DimensionApprovalController::class, 'research'])->name('research');
            Route::post('approve-all', [DimensionApprovalController::class, 'approveAll'])->name('approve-all');
        });

        // ── Product Dimensions (legado — edição manual) ───────
        Route::get('dimensions', [Tenant\ProductDimensionController::class, 'index'])
            ->name('dimensions.index');
        Route::post('dimensions/sync-from-reference-page', [Tenant\ProductDimensionController::class, 'syncPageFromReference'])
            ->name('dimensions.sync-from-reference-page');
        Route::post('dimensions/{product}/sync-from-reference', [Tenant\ProductDimensionController::class, 'syncFromReference'])
            ->name('dimensions.sync-from-reference');
        Route::patch('dimensions/{product}', [Tenant\ProductDimensionController::class, 'update'])
            ->name('dimensions.update');

        // ── Similar Groups ────────────────────────────────────
        Route::get('similar-groups/products/search', [Tenant\SimilarGroupController::class, 'productSearch'])
            ->name('similar-groups.products.search');
        Route::resource('similar-groups', Tenant\SimilarGroupController::class)
            ->except(['show'])
            ->names('similar-groups');

        // ── Stores ────────────────────────────────────────────
        Route::resource('stores', Tenant\StoreController::class)
            ->except(['show'])
            ->names('stores');

        // ── Sales ─────────────────────────────────────────────
        Route::resource('sales', Tenant\SaleController::class)
            ->except(['show'])
            ->names('sales');

        // ── Clusters ──────────────────────────────────────────
        Route::resource('clusters', Tenant\ClusterController::class)
            ->except(['show'])
            ->names('clusters');

        // ── Providers ─────────────────────────────────────────
        Route::resource('providers', Tenant\ProviderController::class)
            ->except(['show'])
            ->names('providers');

        // ── Planograms ────────────────────────────────────────
        Route::resource('planograms', Tenant\PlanogramController::class)
            ->except(['show'])
            ->names('planograms');
        Route::get('planograms/maps', [Tenant\PlanogramController::class, 'maps'])
            ->name('planograms.maps');
        Route::get('planograms/orphan-layers', [Tenant\PlanogramController::class, 'orphanLayers'])
            ->name('planograms.orphan-layers');
        Route::middleware('tenant.module.active:'.ModuleSlug::KANBAN)
            ->get('planograms/kanban', [Tenant\PlanogramController::class, 'kanban'])
            ->name('planograms.kanban');

        // ── Gondolas ──────────────────────────────────────────
        Route::resource('planograms/{planogram}/gondolas', Tenant\GondolaController::class)
            ->except(['show'])
            ->names('gondolas');

        // ── Users ─────────────────────────────────────────────
        Route::resource('users', Tenant\UserController::class)
            ->except(['show'])
            ->names('users');

        // ── Notifications ─────────────────────────────────────
        Route::post('notifications/read-all', [Tenant\NotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');
        Route::delete('notifications', [Tenant\NotificationController::class, 'destroyAll'])
            ->name('notifications.destroy-all');
        Route::patch('notifications/{id}/read', [Tenant\NotificationController::class, 'markRead'])
            ->name('notifications.read');
        Route::get('notifications/{id}/download', [Tenant\NotificationController::class, 'download'])
            ->name('notifications.download');
        Route::delete('notifications/{id}', [Tenant\NotificationController::class, 'destroy'])
            ->name('notifications.destroy');

        // ── System Logs ───────────────────────────────────────
        Route::get('system-logs', [Tenant\SystemLogController::class, 'index'])
            ->name('system-logs.index');
        Route::get('system-logs/download', [Tenant\SystemLogController::class, 'download'])
            ->name('system-logs.download');
        Route::delete('system-logs', [Tenant\SystemLogController::class, 'clear'])
            ->name('system-logs.clear');

        // ── Settings ──────────────────────────────────────────
        Route::get('settings/scoring-weights', [Settings\ScoringWeightsController::class, 'edit'])
            ->name('scoring-weights.edit');
        Route::put('settings/scoring-weights', [Settings\ScoringWeightsController::class, 'update'])
            ->name('scoring-weights.update');

        Route::get('settings/adjacency-matrix', [Settings\AdjacencyMatrixController::class, 'edit'])
            ->name('adjacency-matrix.edit');
        Route::post('settings/adjacency-matrix', [Settings\AdjacencyMatrixController::class, 'store'])
            ->name('adjacency-matrix.store');
        Route::put('settings/adjacency-matrix/{adjacencyRule}', [Settings\AdjacencyMatrixController::class, 'update'])
            ->name('adjacency-matrix.update');
        Route::delete('settings/adjacency-matrix/{adjacencyRule}', [Settings\AdjacencyMatrixController::class, 'destroy'])
            ->name('adjacency-matrix.destroy');

        Route::get('settings/planogram', [Settings\PlanogramSettingsController::class, 'edit'])
            ->name('planogram-settings.edit');
        Route::put('settings/planogram', [Settings\PlanogramSettingsController::class, 'update'])
            ->name('planogram-settings.update');

        Route::get('settings/shelf-level-preferences', [Settings\ShelfLevelPreferencesController::class, 'edit'])
            ->name('shelf-level-preferences.edit');
        Route::post('settings/shelf-level-preferences', [Settings\ShelfLevelPreferencesController::class, 'store'])
            ->name('shelf-level-preferences.store');
        Route::put('settings/shelf-level-preferences/{preference}', [Settings\ShelfLevelPreferencesController::class, 'update'])
            ->name('shelf-level-preferences.update');
        Route::delete('settings/shelf-level-preferences/{preference}', [Settings\ShelfLevelPreferencesController::class, 'destroy'])
            ->name('shelf-level-preferences.destroy');

        // ── Kanban (módulo opcional) ───────────────────────────
        Route::middleware('tenant.module.active:'.ModuleSlug::KANBAN)->group(function (): void {
            Route::get('kanban', [Tenant\WorkflowKanbanController::class, 'index'])->name('kanban.index');

            Route::post('kanban/{planogram}/executions', [Tenant\WorkflowExecutionController::class, 'store'])
                ->name('kanban.executions.store');
            Route::get('kanban/executions/{execution}/details', [Tenant\WorkflowExecutionController::class, 'details'])
                ->name('kanban.executions.details');
            Route::patch('kanban/executions/{execution}/start', [Tenant\WorkflowExecutionController::class, 'start'])
                ->name('kanban.executions.start');
            Route::patch('kanban/executions/{execution}/move', [Tenant\WorkflowExecutionController::class, 'move'])
                ->name('kanban.executions.move');
            Route::patch('kanban/executions/{execution}/pause', [Tenant\WorkflowExecutionController::class, 'pause'])
                ->name('kanban.executions.pause');
            Route::patch('kanban/executions/{execution}/resume', [Tenant\WorkflowExecutionController::class, 'resume'])
                ->name('kanban.executions.resume');
            Route::patch('kanban/executions/{execution}/complete', [Tenant\WorkflowExecutionController::class, 'complete'])
                ->name('kanban.executions.complete');
            Route::patch('kanban/executions/{execution}/abandon', [Tenant\WorkflowExecutionController::class, 'abandon'])
                ->name('kanban.executions.abandon');
            Route::post('kanban/executions/{execution}/request-abandonment', [Tenant\WorkflowExecutionController::class, 'requestAbandonment'])
                ->name('kanban.executions.request-abandonment');
            Route::patch('kanban/executions/{execution}/assign', [Tenant\WorkflowExecutionController::class, 'assign'])
                ->name('kanban.executions.assign');
            Route::get('kanban/executions/{execution}/history', [Tenant\WorkflowExecutionController::class, 'history'])
                ->name('kanban.executions.history');
            Route::post('kanban/histories/{history}/restore', [Tenant\WorkflowExecutionController::class, 'restore'])
                ->name('kanban.histories.restore');

            Route::get('planograms/{planogram}/workflow-settings', [Tenant\WorkflowPlanogramStepController::class, 'index'])
                ->name('planograms.workflow-settings.index');
            Route::put('planograms/{planogram}/workflow-settings', [Tenant\WorkflowPlanogramStepController::class, 'update'])
                ->name('planograms.workflow-settings.update');
            Route::post('planograms/{planogram}/workflow-settings/load-defaults', [Tenant\WorkflowPlanogramStepController::class, 'loadDefaults'])
                ->name('planograms.workflow-settings.load-defaults');
        });

        // ── Reverb Test (desenvolvimento) ─────────────────────
        Route::get('reverb-test', [Tenant\ReverbTestController::class, 'index'])->name('reverb-test.index');
        Route::post('reverb-test/notify', [Tenant\ReverbTestController::class, 'notify'])->name('reverb-test.notify');
    });
