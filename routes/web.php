<?php

use App\Http\Controllers\Auth\TenantSocialiteController;
use App\Http\Controllers\AutoPlanogramController;
use App\Http\Controllers\Landlord\DashboardController as LandlordDashboardController;
use App\Http\Controllers\Landlord\EanReferenceController as LandlordEanReferenceController;
use App\Http\Controllers\Landlord\IntegrationApiController;
use App\Http\Controllers\Landlord\ModuleController;
use App\Http\Controllers\Landlord\NotificationController as LandlordNotificationController;
use App\Http\Controllers\Landlord\PermissionController;
use App\Http\Controllers\Landlord\PlanController;
use App\Http\Controllers\Landlord\RoleController;
use App\Http\Controllers\Landlord\TenantCloudflareController;
use App\Http\Controllers\Landlord\TenantController as LandlordTenantController;
use App\Http\Controllers\Landlord\TenantIntegrationController;
use App\Http\Controllers\Landlord\TenantSocialiteProviderController;
use App\Http\Controllers\Landlord\TenantUserAccessController;
use App\Http\Controllers\Landlord\UsefulLinkController;
use App\Http\Controllers\Landlord\UserController;
use App\Http\Controllers\Landlord\WorkflowTemplateController as LandlordWorkflowTemplateController;
use App\Http\Controllers\Settings\AdjacencyMatrixController;
use App\Http\Controllers\Settings\PlanogramSettingsController;
use App\Http\Controllers\Settings\ScoringWeightsController;
use App\Http\Controllers\Settings\ShelfLevelPreferencesController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\ClusterController;
use App\Http\Controllers\Tenant\DashboardController as TenantDashboardController;
use App\Http\Controllers\Tenant\Editor\ClientPlanogramController;
use App\Http\Controllers\Tenant\Editor\EditorPlanogramController;
use App\Http\Controllers\Tenant\GondolaController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\PlanogramController;
use App\Http\Controllers\Tenant\PlanogramTemplateController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\ProductDimensionController;
use App\Http\Controllers\Tenant\ProductImageController;
use App\Http\Controllers\Tenant\ProviderController;
use App\Http\Controllers\Tenant\ReverbTestController;
use App\Http\Controllers\Tenant\SaleController;
use App\Http\Controllers\Tenant\SimilarGroupController;
use App\Http\Controllers\Tenant\StoreController;
use App\Http\Controllers\Tenant\SystemLogController;
use App\Http\Controllers\Tenant\TemplateSlotController;
use App\Http\Controllers\Tenant\UserController as TenantUserController;
use App\Http\Controllers\Tenant\WorkflowExecutionController;
use App\Http\Controllers\Tenant\WorkflowKanbanController;
use App\Http\Controllers\Tenant\WorkflowPlanogramStepController;
use App\Http\Middleware\SetPermissionTeamContext;
use App\Support\Modules\ModuleSlug;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
})->name('home');

// ── LANDLORD (rota raiz, sem tenant) ──────────────────────────
Route::domain(config('app.landlord_domain'))->middleware(['web', 'auth', SetPermissionTeamContext::class])->group(function (): void {
    Broadcast::routes();

    Route::get('/', [LandlordDashboardController::class, 'index'])->name('dashboard');

    Route::resource('plans', PlanController::class)
        ->except(['show'])
        ->names('landlord.plans');

    Route::resource('tenants', LandlordTenantController::class)
        ->except(['show'])
        ->names('landlord.tenants');
    Route::get('tenants/export', [LandlordTenantController::class, 'exportConfigurations'])
        ->name('landlord.tenants.export');
    Route::post('tenants/import', [LandlordTenantController::class, 'importConfigurations'])
        ->name('landlord.tenants.import');

    Route::resource('roles', RoleController::class)
        ->except(['show'])
        ->names('landlord.roles');

    Route::resource('modules', ModuleController::class)
        ->except(['show'])
        ->names('landlord.modules');

    Route::resource('integration-apis', IntegrationApiController::class)
        ->except(['show'])
        ->names('landlord.integration-apis');
    Route::get('integration-apis/export', [IntegrationApiController::class, 'exportConfigurations'])
        ->name('landlord.integration-apis.export');
    Route::post('integration-apis/import', [IntegrationApiController::class, 'importConfigurations'])
        ->name('landlord.integration-apis.import');

    Route::resource('users', UserController::class)
        ->except(['show'])
        ->names('landlord.users');

    Route::post('permissions/sync', [PermissionController::class, 'sync'])
        ->name('landlord.permissions.sync');

    Route::resource('permissions', PermissionController::class)
        ->except(['show'])
        ->names('landlord.permissions');

    Route::resource('ean-references', LandlordEanReferenceController::class)
        ->except(['show'])
        ->names('landlord.ean-references');

    Route::resource('useful-links', UsefulLinkController::class)
        ->except(['show'])
        ->names('landlord.useful-links');

    Route::post('notifications/read-all', [LandlordNotificationController::class, 'markAllRead'])
        ->name('landlord.notifications.read-all');
    Route::delete('notifications', [LandlordNotificationController::class, 'destroyAll'])
        ->name('landlord.notifications.destroy-all');
    Route::patch('notifications/{id}/read', [LandlordNotificationController::class, 'markRead'])
        ->name('landlord.notifications.read');
    Route::get('notifications/{id}/download', [LandlordNotificationController::class, 'download'])
        ->name('landlord.notifications.download');
    Route::delete('notifications/{id}', [LandlordNotificationController::class, 'destroy'])
        ->name('landlord.notifications.destroy');

    Route::post('ean-references/image/upload', [LandlordEanReferenceController::class, 'uploadImage'])
        ->name('landlord.ean-references.image.upload');
    Route::post('ean-references/{ean_reference}/fetch-image', [LandlordEanReferenceController::class, 'fetchImage'])
        ->name('landlord.ean-references.fetch-image');

    Route::get('tenants/{tenant}/setup', [LandlordTenantController::class, 'setup'])
        ->name('landlord.tenants.setup');
    Route::post('tenants/{tenant}/provision', [LandlordTenantController::class, 'provision'])
        ->name('landlord.tenants.provision');
    Route::post('tenants/{tenant}/cloudflare', [TenantCloudflareController::class, 'store'])
        ->name('landlord.tenants.cloudflare.store');
    Route::delete('tenants/{tenant}/cloudflare', [TenantCloudflareController::class, 'destroy'])
        ->name('landlord.tenants.cloudflare.destroy');

    Route::get('tenants/{tenant}/access', [TenantUserAccessController::class, 'edit'])
        ->name('landlord.tenants.access.edit');
    Route::post('tenants/{tenant}/access/users', [TenantUserAccessController::class, 'store'])
        ->name('landlord.tenants.access.users.store');
    Route::put('tenants/{tenant}/access/users/{userId}', [TenantUserAccessController::class, 'update'])
        ->name('landlord.tenants.access.users.update');
    Route::patch('tenants/{tenant}/access/users/{userId}/toggle-active', [TenantUserAccessController::class, 'toggleActive'])
        ->name('landlord.tenants.access.users.toggle-active');
    Route::patch('tenants/{tenant}/access/users/{userId}/sync-roles', [TenantUserAccessController::class, 'syncRoles'])
        ->name('landlord.tenants.access.users.sync-roles');
    Route::delete('tenants/{tenant}/access/users/{userId}', [TenantUserAccessController::class, 'destroy'])
        ->name('landlord.tenants.access.users.destroy');
    Route::patch('tenants/{tenant}/access/users/{userId}/restore', [TenantUserAccessController::class, 'restore'])
        ->name('landlord.tenants.access.users.restore');

    Route::put('tenants/{tenant}/socialite-provider', [TenantSocialiteProviderController::class, 'update'])
        ->name('landlord.tenants.socialite-provider.update');
    Route::delete('tenants/{tenant}/socialite-provider', [TenantSocialiteProviderController::class, 'destroy'])
        ->name('landlord.tenants.socialite-provider.destroy');

    Route::get('tenants/{tenant}/integration', [TenantIntegrationController::class, 'edit'])
        ->name('landlord.tenants.integration.edit');
    Route::put('tenants/{tenant}/integration', [TenantIntegrationController::class, 'update'])
        ->name('landlord.tenants.integration.update');
    Route::post('tenants/{tenant}/integration/test-connection', [TenantIntegrationController::class, 'testConnection'])
        ->name('landlord.tenants.integration.test-connection');
    Route::patch('tenants/{tenant}/integration/toggle-status', [TenantIntegrationController::class, 'toggleStatus'])
        ->name('landlord.tenants.integration.toggle-status');
    Route::delete('tenants/{tenant}/integration', [TenantIntegrationController::class, 'destroy'])
        ->name('landlord.tenants.integration.destroy');

    Route::middleware('tenant.module.active:'.ModuleSlug::KANBAN)->group(function (): void {
        Route::get('tenants/{tenant}/kanban/templates', [LandlordWorkflowTemplateController::class, 'index'])
            ->name('landlord.tenants.kanban.templates.index');
        Route::get('tenants/{tenant}/kanban/templates/create', [LandlordWorkflowTemplateController::class, 'create'])
            ->name('landlord.tenants.kanban.templates.create');
        Route::post('tenants/{tenant}/kanban/templates', [LandlordWorkflowTemplateController::class, 'store'])
            ->name('landlord.tenants.kanban.templates.store');
        Route::post('tenants/{tenant}/kanban/templates/seed-defaults', [LandlordWorkflowTemplateController::class, 'seedDefaultTemplates'])
            ->name('landlord.tenants.kanban.templates.seed-defaults');
        Route::get('tenants/{tenant}/kanban/templates/{template}/edit', [LandlordWorkflowTemplateController::class, 'edit'])
            ->name('landlord.tenants.kanban.templates.edit');
        Route::put('tenants/{tenant}/kanban/templates/{template}', [LandlordWorkflowTemplateController::class, 'update'])
            ->name('landlord.tenants.kanban.templates.update');
        Route::patch('tenants/{tenant}/kanban/templates/{template}/sync-users', [LandlordWorkflowTemplateController::class, 'syncUsers'])
            ->name('landlord.tenants.kanban.templates.sync-users');
        Route::delete('tenants/{tenant}/kanban/templates/{template}', [LandlordWorkflowTemplateController::class, 'destroy'])
            ->name('landlord.tenants.kanban.templates.destroy');
    });
});

// ── SOCIALITE OAuth — subdomain, sem auth, com NeedsTenant ───
Route::middleware(['web', NeedsTenant::class])
    ->group(function (): void {
        Route::get('/auth/{provider}/redirect', [TenantSocialiteController::class, 'redirect'])
            ->name('tenant.auth.socialite.redirect');
        Route::get('/auth/{provider}/callback', [TenantSocialiteController::class, 'callback'])
            ->name('tenant.auth.socialite.callback');
    });

// ── TENANT (rotas que exigem tenant ativo) ────────────────────
Route::domain(sprintf('{subdomain}.%s', config('app.landlord_domain')))
    ->middleware(['web', 'auth', NeedsTenant::class, SetPermissionTeamContext::class])
    ->name('tenant.')
    ->group(function (): void {

        Route::get('editor/planograms', [ClientPlanogramController::class, 'index'])
            ->name('editor.planograms.index');

        Route::get('editor/planograms/{planogram}/gondolas', [ClientPlanogramController::class, 'gondolas'])
            ->name('editor.planograms.gondolas');

        Route::get('editor/planograms/{record}/gondolas/editor', [EditorPlanogramController::class, 'edit'])
            ->name('planograms.gondolas.editor');

        Route::prefix('api')->name('api.')->group(function (): void {
            Route::post('gondolas/{gondola}/auto-generate', [AutoPlanogramController::class, 'generate'])
                ->name('gondolas.auto-generate');
            Route::get('gondolas/{gondola}/rejected-products', [AutoPlanogramController::class, 'rejectedProducts'])
                ->name('gondolas.rejected-products');
            Route::delete('gondolas/{gondola}/rejected-products/{rejected}', [AutoPlanogramController::class, 'destroyRejectedProduct'])
                ->name('gondolas.rejected-products.destroy');
            Route::post('gondolas/{gondola}/swap-product', [AutoPlanogramController::class, 'swapProduct'])
                ->name('gondolas.swap-product');
        });
    });

Route::domain(sprintf('{subdomain}.%s', config('app.landlord_domain')))
    ->middleware(['web', 'auth', NeedsTenant::class, SetPermissionTeamContext::class, 'tenant.client.redirect'])
    ->name('tenant.')
    ->group(function (): void {
        Route::get('/', [TenantDashboardController::class, 'index'])->name('dashboard');

        Route::get('categories/cascade/children', [CategoryController::class, 'cascadeChildren'])
            ->name('categories.cascade.children');
        Route::get('categories/cascade/path', [CategoryController::class, 'cascadePath'])
            ->name('categories.cascade.path');
        Route::post('categories/import', [CategoryController::class, 'import'])
            ->name('categories.import');
        Route::get('categories/export/template', [CategoryController::class, 'exportTemplate'])
            ->name('categories.export.template');
        Route::get('categories/export/data', [CategoryController::class, 'exportData'])
            ->name('categories.export.data');

        Route::resource('categories', CategoryController::class)
            ->except(['show'])
            ->names('categories');

        Route::resource('products', ProductController::class)
            ->except(['show'])
            ->names('products');
        Route::get('products/sortiment-attributes', [ProductController::class, 'sortimentAttributes'])
            ->name('products.sortiment-attributes');
        Route::post('products/sync-single', [ProductController::class, 'syncSingle'])
            ->name('products.sync-single');
        Route::post('products/update-images', [ProductController::class, 'updateImages'])
            ->name('products.update-images');

        Route::get('dimensions', [ProductDimensionController::class, 'index'])
            ->name('dimensions.index');
        Route::patch('dimensions/{product}', [ProductDimensionController::class, 'update'])
            ->name('dimensions.update');

        Route::get('similar-groups/products/search', [SimilarGroupController::class, 'productSearch'])
            ->name('similar-groups.products.search');
        Route::resource('similar-groups', SimilarGroupController::class)
            ->except(['show'])
            ->names('similar-groups');
        Route::get('system-logs', [SystemLogController::class, 'index'])
            ->name('system-logs.index');
        Route::delete('system-logs', [SystemLogController::class, 'clear'])
            ->name('system-logs.clear');

        Route::resource('stores', StoreController::class)
            ->except(['show'])
            ->names('stores');

        Route::resource('sales', SaleController::class)
            ->except(['show'])
            ->names('sales');

        Route::resource('clusters', ClusterController::class)
            ->except(['show'])
            ->names('clusters');

        Route::resource('providers', ProviderController::class)
            ->except(['show'])
            ->names('providers');

        Route::get('planogram-templates', [PlanogramTemplateController::class, 'index'])
            ->name('planogram-templates.index');
        Route::get('planogram-templates/create', [PlanogramTemplateController::class, 'create'])
            ->name('planogram-templates.create');
        Route::post('planogram-templates', [PlanogramTemplateController::class, 'store'])
            ->name('planogram-templates.store');
        Route::get('planogram-templates/{planogramTemplate}/edit', [PlanogramTemplateController::class, 'edit'])
            ->name('planogram-templates.edit');
        Route::put('planogram-templates/{planogramTemplate}', [PlanogramTemplateController::class, 'update'])
            ->name('planogram-templates.update');
        Route::get('planogram-templates/import', [PlanogramTemplateController::class, 'importPage'])
            ->name('planogram-templates.import-page');
        Route::post('planogram-templates/import', [PlanogramTemplateController::class, 'import'])
            ->name('planogram-templates.import');
        Route::get('planogram-templates/export', [PlanogramTemplateController::class, 'exportAll'])
            ->name('planogram-templates.export-all');
        Route::get('planogram-templates/{planogramTemplate}/export', [PlanogramTemplateController::class, 'export'])
            ->name('planogram-templates.export');
        Route::get('planogram-templates/{planogramTemplate}', [PlanogramTemplateController::class, 'show'])
            ->name('planogram-templates.show');
        Route::delete('planogram-templates/{planogramTemplate}', [PlanogramTemplateController::class, 'destroy'])
            ->name('planogram-templates.destroy');

        // Wizard etapa 2 — Slots (tenant)
        Route::get('planogram-templates/{planogramTemplate}/slots', [TemplateSlotController::class, 'index'])
            ->name('planogram-templates.slots.index');
        Route::get('planogram-templates/{planogramTemplate}/review', [TemplateSlotController::class, 'review'])
            ->name('planogram-templates.slots.review');
        Route::post('planogram-templates/{planogramTemplate}/subtemplates', [TemplateSlotController::class, 'createSubtemplate'])
            ->name('planogram-templates.subtemplates.store');
        Route::post('planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/clone', [TemplateSlotController::class, 'cloneSubtemplate'])
            ->name('planogram-templates.subtemplates.clone');
        Route::post('planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slots', [TemplateSlotController::class, 'storeSlot'])
            ->name('planogram-templates.slots.store');
        Route::post('planogram-templates/{planogramTemplate}/slots/reorder', [TemplateSlotController::class, 'reorder'])
            ->name('planogram-templates.slots.reorder');
        Route::get('planogram-templates/{planogramTemplate}/slots/products', [TemplateSlotController::class, 'slotProducts'])
            ->name('planogram-templates.slots.products');
        Route::post('planogram-templates/{planogramTemplate}/slots/sync-images', [TemplateSlotController::class, 'syncImages'])
            ->name('planogram-templates.slots.sync-images');

        Route::get('planogram-templates/{planogramTemplate}/slots/analysis', [TemplateSlotController::class, 'slotAnalysis'])
            ->name('planogram-templates.slots.analysis');
        Route::put('planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}', [TemplateSlotController::class, 'updateSlot'])
            ->name('planogram-templates.slots.update');
        Route::delete('planogram-templates/{planogramTemplate}/slots/{planogramTemplateSlot}', [TemplateSlotController::class, 'destroySlot'])
            ->name('planogram-templates.slots.destroy');

        Route::resource('users', TenantUserController::class)
            ->except(['show'])
            ->names('users');

        Route::resource('planograms', PlanogramController::class)
            ->except(['show'])
            ->names('planograms');

        Route::middleware('tenant.module.active:'.ModuleSlug::KANBAN)
            ->get('planograms/kanban', [PlanogramController::class, 'kanban'])
            ->name('planograms.kanban');

        Route::get('planograms/maps', [PlanogramController::class, 'maps'])
            ->name('planograms.maps');
        Route::get('planograms/orphan-layers', [PlanogramController::class, 'orphanLayers'])
            ->name('planograms.orphan-layers');

        Route::resource('planograms/{planogram}/gondolas', GondolaController::class)
            ->except(['show'])
            ->names('gondolas');

        Route::post('products/image/upload', [ProductImageController::class, 'upload'])
            ->name('products.image.upload');
        Route::delete('products/image/{product}', [ProductImageController::class, 'destroy'])
            ->name('products.image.destroy');
        Route::post('products/image/ai/process', [ProductImageController::class, 'process'])
            ->name('products.image.ai.process');
        Route::get('products/image/ai/operations/{operation}', [ProductImageController::class, 'status'])
            ->name('products.image.ai.status');
        Route::post('products/image/repository/fetch', [ProductImageController::class, 'fetchFromRepository'])
            ->name('products.image.repository.fetch');

        Route::get('reverb-test', [ReverbTestController::class, 'index'])->name('reverb-test.index');
        Route::post('reverb-test/notify', [ReverbTestController::class, 'notify'])->name('reverb-test.notify');

        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');
        Route::delete('notifications', [NotificationController::class, 'destroyAll'])
            ->name('notifications.destroy-all');
        Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead'])
            ->name('notifications.read');
        Route::get('notifications/{id}/download', [NotificationController::class, 'download'])
            ->name('notifications.download');
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])
            ->name('notifications.destroy');

        Route::get('settings/scoring-weights', [ScoringWeightsController::class, 'edit'])
            ->name('scoring-weights.edit');
        Route::put('settings/scoring-weights', [ScoringWeightsController::class, 'update'])
            ->name('scoring-weights.update');

        Route::get('settings/adjacency-matrix', [AdjacencyMatrixController::class, 'edit'])
            ->name('adjacency-matrix.edit');
        Route::post('settings/adjacency-matrix', [AdjacencyMatrixController::class, 'store'])
            ->name('adjacency-matrix.store');
        Route::put('settings/adjacency-matrix/{adjacencyRule}', [AdjacencyMatrixController::class, 'update'])
            ->name('adjacency-matrix.update');
        Route::delete('settings/adjacency-matrix/{adjacencyRule}', [AdjacencyMatrixController::class, 'destroy'])
            ->name('adjacency-matrix.destroy');

        Route::get('settings/planogram', [PlanogramSettingsController::class, 'edit'])
            ->name('planogram-settings.edit');
        Route::put('settings/planogram', [PlanogramSettingsController::class, 'update'])
            ->name('planogram-settings.update');

        Route::get('settings/shelf-level-preferences', [ShelfLevelPreferencesController::class, 'edit'])
            ->name('shelf-level-preferences.edit');
        Route::post('settings/shelf-level-preferences', [ShelfLevelPreferencesController::class, 'store'])
            ->name('shelf-level-preferences.store');
        Route::put('settings/shelf-level-preferences/{preference}', [ShelfLevelPreferencesController::class, 'update'])
            ->name('shelf-level-preferences.update');
        Route::delete('settings/shelf-level-preferences/{preference}', [ShelfLevelPreferencesController::class, 'destroy'])
            ->name('shelf-level-preferences.destroy');

        Route::middleware('tenant.module.active:'.ModuleSlug::KANBAN)->group(function (): void {
            // ── KANBAN ────────────────────────────────────────────────
            Route::get('kanban', [WorkflowKanbanController::class, 'index'])->name('kanban.index');

            Route::post('kanban/{planogram}/executions', [WorkflowExecutionController::class, 'store'])
                ->name('kanban.executions.store');
            Route::get('kanban/executions/{execution}/details', [WorkflowExecutionController::class, 'details'])
                ->name('kanban.executions.details');
            Route::patch('kanban/executions/{execution}/start', [WorkflowExecutionController::class, 'start'])
                ->name('kanban.executions.start');
            Route::patch('kanban/executions/{execution}/move', [WorkflowExecutionController::class, 'move'])
                ->name('kanban.executions.move');
            Route::patch('kanban/executions/{execution}/pause', [WorkflowExecutionController::class, 'pause'])
                ->name('kanban.executions.pause');
            Route::patch('kanban/executions/{execution}/resume', [WorkflowExecutionController::class, 'resume'])
                ->name('kanban.executions.resume');
            Route::patch('kanban/executions/{execution}/complete', [WorkflowExecutionController::class, 'complete'])
                ->name('kanban.executions.complete');
            Route::patch('kanban/executions/{execution}/abandon', [WorkflowExecutionController::class, 'abandon'])
                ->name('kanban.executions.abandon');
            Route::post('kanban/executions/{execution}/request-abandonment', [WorkflowExecutionController::class, 'requestAbandonment'])
                ->name('kanban.executions.request-abandonment');
            Route::patch('kanban/executions/{execution}/assign', [WorkflowExecutionController::class, 'assign'])
                ->name('kanban.executions.assign');
            Route::get('kanban/executions/{execution}/history', [WorkflowExecutionController::class, 'history'])
                ->name('kanban.executions.history');
            Route::post('kanban/histories/{history}/restore', [WorkflowExecutionController::class, 'restore'])
                ->name('kanban.histories.restore');

            Route::get('planograms/{planogram}/workflow-settings', [WorkflowPlanogramStepController::class, 'index'])
                ->name('planograms.workflow-settings.index');
            Route::put('planograms/{planogram}/workflow-settings', [WorkflowPlanogramStepController::class, 'update'])
                ->name('planograms.workflow-settings.update');
            Route::post('planograms/{planogram}/workflow-settings/load-defaults', [WorkflowPlanogramStepController::class, 'loadDefaults'])
                ->name('planograms.workflow-settings.load-defaults');
        });
    });

// Broadcasting auth precisa rodar no contexto do tenant para autenticar canais privados
Route::domain(sprintf('{subdomain}.%s', config('app.landlord_domain')))
    ->middleware(['web', 'auth', NeedsTenant::class, SetPermissionTeamContext::class])
    ->group(function (): void {
        Broadcast::routes();
    });

require __DIR__.'/settings.php';
