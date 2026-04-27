<?php

use App\Http\Controllers\Landlord\DashboardController as LandlordDashboardController;
use App\Http\Controllers\Landlord\ModuleController;
use App\Http\Controllers\Landlord\PermissionController;
use App\Http\Controllers\Landlord\PlanController;
use App\Http\Controllers\Landlord\RoleController;
use App\Http\Controllers\Landlord\TenantController as LandlordTenantController;
use App\Http\Controllers\Landlord\TenantUserAccessController;
use App\Http\Controllers\Landlord\UserController;
use App\Http\Controllers\Landlord\WorkflowTemplateController as LandlordWorkflowTemplateController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\ClusterController;
use App\Http\Controllers\Tenant\DashboardController as TenantDashboardController;
use App\Http\Controllers\Tenant\Editor\EditorPlanogramController;
use App\Http\Controllers\Tenant\GondolaController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\PlanogramController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\ProductImageController;
use App\Http\Controllers\Tenant\ProviderController;
use App\Http\Controllers\Tenant\ReverbTestController;
use App\Http\Controllers\Tenant\StoreController;
use App\Http\Controllers\Tenant\WorkflowExecutionController;
use App\Http\Controllers\Tenant\WorkflowKanbanController;
use App\Http\Middleware\SetPermissionTeamContext;
use App\Support\Modules\ModuleSlug;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

// ── LANDLORD (rota raiz, sem tenant) ──────────────────────────
Route::domain(config('app.landlord_domain'))->middleware(['web', 'auth', SetPermissionTeamContext::class])->group(function (): void {
    Route::get('/dashboard', [LandlordDashboardController::class, 'index'])->name('dashboard');

    Route::resource('plans', PlanController::class)
        ->except(['show'])
        ->names('landlord.plans');

    Route::resource('tenants', LandlordTenantController::class)
        ->except(['show'])
        ->names('landlord.tenants');

    Route::resource('roles', RoleController::class)
        ->except(['show'])
        ->names('landlord.roles');

    Route::resource('modules', ModuleController::class)
        ->except(['show'])
        ->names('landlord.modules');

    Route::resource('users', UserController::class)
        ->except(['show'])
        ->names('landlord.users');

    Route::resource('permissions', PermissionController::class)
        ->except(['show'])
        ->names('landlord.permissions');

    Route::get('tenants/{tenant}/setup', [LandlordTenantController::class, 'setup'])
        ->name('landlord.tenants.setup');
    Route::post('tenants/{tenant}/provision', [LandlordTenantController::class, 'provision'])
        ->name('landlord.tenants.provision');

    Route::get('tenants/{tenant}/access', [TenantUserAccessController::class, 'edit'])
        ->name('landlord.tenants.access.edit');
    Route::post('tenants/{tenant}/access/users', [TenantUserAccessController::class, 'store'])
        ->name('landlord.tenants.access.users.store');
    Route::put('tenants/{tenant}/access/users/{userId}', [TenantUserAccessController::class, 'update'])
        ->name('landlord.tenants.access.users.update');
    Route::patch('tenants/{tenant}/access/users/{userId}/toggle-active', [TenantUserAccessController::class, 'toggleActive'])
        ->name('landlord.tenants.access.users.toggle-active');
    Route::delete('tenants/{tenant}/access/users/{userId}', [TenantUserAccessController::class, 'destroy'])
        ->name('landlord.tenants.access.users.destroy');
    Route::patch('tenants/{tenant}/access/users/{userId}/restore', [TenantUserAccessController::class, 'restore'])
        ->name('landlord.tenants.access.users.restore');

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
        Route::delete('tenants/{tenant}/kanban/templates/{template}', [LandlordWorkflowTemplateController::class, 'destroy'])
            ->name('landlord.tenants.kanban.templates.destroy');
    });
});

// ── TENANT (rotas que exigem tenant ativo) ────────────────────
Route::domain(sprintf('{subdomain}.%s', config('app.landlord_domain')))
    ->middleware(['web', 'auth', NeedsTenant::class, SetPermissionTeamContext::class])
    ->name('tenant.')
    ->group(function (): void {
        Route::get('/dashboard', [TenantDashboardController::class, 'index'])->name('dashboard');

        Route::get('categories/cascade/children', [CategoryController::class, 'cascadeChildren'])
            ->name('categories.cascade.children');
        Route::get('categories/cascade/path', [CategoryController::class, 'cascadePath'])
            ->name('categories.cascade.path');

        Route::resource('categories', CategoryController::class)
            ->except(['show'])
            ->names('categories');

        Route::resource('products', ProductController::class)
            ->except(['show'])
            ->names('products');

        Route::resource('stores', StoreController::class)
            ->except(['show'])
            ->names('stores');

        Route::resource('clusters', ClusterController::class)
            ->except(['show'])
            ->names('clusters');

        Route::resource('providers', ProviderController::class)
            ->except(['show'])
            ->names('providers');

        Route::resource('planograms', PlanogramController::class)
            ->except(['show'])
            ->names('planograms');

        Route::middleware('tenant.module.active:'.ModuleSlug::KANBAN)
            ->get('planograms/kanban', [PlanogramController::class, 'kanban'])
            ->name('planograms.kanban');

        Route::get('planograms/maps', [PlanogramController::class, 'maps'])
            ->name('planograms.maps');

        Route::resource('planograms/{planogram}/gondolas', GondolaController::class)
            ->except(['show'])
            ->names('gondolas');

        Route::get('editor/planograms/{record}/gondolas', [EditorPlanogramController::class, 'edit'])
            ->name('planograms.gondolas.editor');

        Route::post('products/image/upload', [ProductImageController::class, 'upload'])
            ->name('products.image.upload');
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

        Route::middleware('tenant.module.active:'.ModuleSlug::KANBAN)->group(function (): void {
            // ── KANBAN ────────────────────────────────────────────────
            Route::get('kanban', [WorkflowKanbanController::class, 'index'])->name('kanban.index');
            Route::get('kanban/{planogram}', [WorkflowKanbanController::class, 'show'])->name('kanban.show');

            Route::post('kanban/{planogram}/executions', [WorkflowExecutionController::class, 'store'])
                ->name('kanban.executions.store');
            Route::patch('kanban/executions/{execution}/move', [WorkflowExecutionController::class, 'move'])
                ->name('kanban.executions.move');
            Route::patch('kanban/executions/{execution}/pause', [WorkflowExecutionController::class, 'pause'])
                ->name('kanban.executions.pause');
            Route::patch('kanban/executions/{execution}/resume', [WorkflowExecutionController::class, 'resume'])
                ->name('kanban.executions.resume');
            Route::patch('kanban/executions/{execution}/complete', [WorkflowExecutionController::class, 'complete'])
                ->name('kanban.executions.complete');
            Route::patch('kanban/executions/{execution}/assign', [WorkflowExecutionController::class, 'assign'])
                ->name('kanban.executions.assign');
            Route::get('kanban/executions/{execution}/history', [WorkflowExecutionController::class, 'history'])
                ->name('kanban.executions.history');
            Route::post('kanban/histories/{history}/restore', [WorkflowExecutionController::class, 'restore'])
                ->name('kanban.histories.restore');
        });
    });

// Broadcasting auth precisa rodar no contexto do tenant para autenticar canais privados
Route::domain(sprintf('{subdomain}.%s', config('app.landlord_domain')))
    ->middleware(['web', 'auth', NeedsTenant::class, SetPermissionTeamContext::class])
    ->group(function (): void {
        Broadcast::routes();
    });

require __DIR__.'/settings.php';
