<?php

use App\Http\Controllers\Landlord;
use App\Http\Controllers\MetricsController;
use App\Http\Middleware\SetPermissionTeamContext;
use App\Http\Middleware\VerifyMetricsToken;
use App\Support\Modules\ModuleSlug;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// ── Métricas Prometheus (bearer token, sem sessão/CSRF) ───────
Route::domain(config('app.landlord_domain'))
    ->middleware(VerifyMetricsToken::class)
    ->get('metrics', MetricsController::class)
    ->name('landlord.metrics');

// ── LANDLORD (rota raiz, sem tenant) ──────────────────────────
Route::domain(config('app.landlord_domain'))->middleware(['web', 'auth', SetPermissionTeamContext::class])->group(function (): void {
    Broadcast::routes();

    Route::get('/', [Landlord\DashboardController::class, 'index'])->name('dashboard');

    Route::resource('plans', Landlord\PlanController::class)
        ->except(['show'])
        ->names('landlord.plans');

    Route::get('proposal-generator', [Landlord\ProposalGeneratorController::class, 'index'])
        ->name('landlord.proposal-generator.index');

    Route::resource('tenants', Landlord\TenantController::class)
        ->except(['show'])
        ->names('landlord.tenants');
    Route::get('tenants/export', [Landlord\TenantController::class, 'exportConfigurations'])
        ->name('landlord.tenants.export');
    Route::post('tenants/import', [Landlord\TenantController::class, 'importConfigurations'])
        ->name('landlord.tenants.import');

    Route::resource('roles', Landlord\RoleController::class)
        ->except(['show'])
        ->withTrashed(['destroy'])
        ->names('landlord.roles');
    Route::post('roles/{role}/restore', [Landlord\RoleController::class, 'restore'])
        ->withTrashed()
        ->name('landlord.roles.restore');

    Route::resource('modules', Landlord\ModuleController::class)
        ->except(['show'])
        ->names('landlord.modules');

    Route::resource('integration-apis', Landlord\IntegrationApiController::class)
        ->except(['show'])
        ->withTrashed(['destroy'])
        ->names('landlord.integration-apis');
    Route::post('integration-apis/{integration_api}/restore', [Landlord\IntegrationApiController::class, 'restore'])
        ->withTrashed()
        ->name('landlord.integration-apis.restore');
    Route::get('integration-apis/export', [Landlord\IntegrationApiController::class, 'exportConfigurations'])
        ->name('landlord.integration-apis.export');
    Route::post('integration-apis/import', [Landlord\IntegrationApiController::class, 'importConfigurations'])
        ->name('landlord.integration-apis.import');

    Route::resource('users', Landlord\UserController::class)
        ->except(['show'])
        ->withTrashed(['destroy'])
        ->names('landlord.users');
    Route::post('users/{user}/restore', [Landlord\UserController::class, 'restore'])
        ->withTrashed()
        ->name('landlord.users.restore');

    Route::post('permissions/sync', [Landlord\PermissionController::class, 'sync'])
        ->name('landlord.permissions.sync');

    Route::resource('permissions', Landlord\PermissionController::class)
        ->except(['show'])
        ->names('landlord.permissions');

    Route::resource('ean-references', Landlord\EanReferenceController::class)
        ->except(['show'])
        ->withTrashed(['destroy'])
        ->names('landlord.ean-references');
    Route::post('ean-references/{ean_reference}/restore', [Landlord\EanReferenceController::class, 'restore'])
        ->withTrashed()
        ->name('landlord.ean-references.restore');

    Route::resource('useful-links', Landlord\UsefulLinkController::class)
        ->except(['show'])
        ->withTrashed(['destroy'])
        ->names('landlord.useful-links');
    Route::post('useful-links/{useful_link}/restore', [Landlord\UsefulLinkController::class, 'restore'])
        ->withTrashed()
        ->name('landlord.useful-links.restore');

    Route::post('notifications/read-all', [Landlord\NotificationController::class, 'markAllRead'])
        ->name('landlord.notifications.read-all');
    Route::delete('notifications', [Landlord\NotificationController::class, 'destroyAll'])
        ->name('landlord.notifications.destroy-all');
    Route::patch('notifications/{id}/read', [Landlord\NotificationController::class, 'markRead'])
        ->name('landlord.notifications.read');
    Route::get('notifications/{id}/download', [Landlord\NotificationController::class, 'download'])
        ->name('landlord.notifications.download');
    Route::delete('notifications/{id}', [Landlord\NotificationController::class, 'destroy'])
        ->name('landlord.notifications.destroy');

    Route::post('ean-references/image/upload', [Landlord\EanReferenceController::class, 'uploadImage'])
        ->name('landlord.ean-references.image.upload');
    Route::post('ean-references/{ean_reference}/fetch-image', [Landlord\EanReferenceController::class, 'fetchImage'])
        ->name('landlord.ean-references.fetch-image');

    Route::get('tenants/{tenant}/setup', [Landlord\TenantController::class, 'setup'])
        ->name('landlord.tenants.setup');
    Route::post('tenants/{tenant}/provision', [Landlord\TenantController::class, 'provision'])
        ->name('landlord.tenants.provision');
    Route::post('tenants/{tenant}/cloudflare', [Landlord\TenantCloudflareController::class, 'store'])
        ->name('landlord.tenants.cloudflare.store');
    Route::delete('tenants/{tenant}/cloudflare', [Landlord\TenantCloudflareController::class, 'destroy'])
        ->name('landlord.tenants.cloudflare.destroy');

    Route::get('tenants/{tenant}/access', [Landlord\TenantUserAccessController::class, 'edit'])
        ->name('landlord.tenants.access.edit');
    Route::post('tenants/{tenant}/access/users', [Landlord\TenantUserAccessController::class, 'store'])
        ->name('landlord.tenants.access.users.store');
    Route::put('tenants/{tenant}/access/users/{userId}', [Landlord\TenantUserAccessController::class, 'update'])
        ->name('landlord.tenants.access.users.update');
    Route::patch('tenants/{tenant}/access/users/{userId}/toggle-active', [Landlord\TenantUserAccessController::class, 'toggleActive'])
        ->name('landlord.tenants.access.users.toggle-active');
    Route::patch('tenants/{tenant}/access/users/{userId}/sync-roles', [Landlord\TenantUserAccessController::class, 'syncRoles'])
        ->name('landlord.tenants.access.users.sync-roles');
    Route::delete('tenants/{tenant}/access/users/{userId}', [Landlord\TenantUserAccessController::class, 'destroy'])
        ->name('landlord.tenants.access.users.destroy');
    Route::patch('tenants/{tenant}/access/users/{userId}/restore', [Landlord\TenantUserAccessController::class, 'restore'])
        ->name('landlord.tenants.access.users.restore');
    Route::delete('tenants/{tenant}/access/users/{userId}/force', [Landlord\TenantUserAccessController::class, 'forceDelete'])
        ->name('landlord.tenants.access.users.force-delete');
    Route::post('tenants/{tenant}/access/users/{userId}/impersonate', [Landlord\TenantUserAccessController::class, 'impersonate'])
        ->middleware('throttle:6,1')
        ->name('landlord.tenants.access.users.impersonate');
    Route::post('tenants/{tenant}/access/users/{userId}/password-setup/resend', [Landlord\TenantUserAccessController::class, 'resendPasswordSetup'])
        ->middleware('throttle:6,1')
        ->name('landlord.tenants.access.users.password-setup.resend');

    // ── Mercadológico (árvore de categorias do tenant) ──
    Route::get('tenants/{tenant}/mercadologico', [Landlord\CategoryTreeController::class, 'index'])
        ->name('landlord.tenants.mercadologico.index');
    Route::get('tenants/{tenant}/mercadologico/children', [Landlord\CategoryTreeController::class, 'children'])
        ->name('landlord.tenants.mercadologico.children');
    Route::get('tenants/{tenant}/mercadologico/{category}/products', [Landlord\CategoryTreeController::class, 'products'])
        ->name('landlord.tenants.mercadologico.products');
    Route::post('tenants/{tenant}/mercadologico/{category}/move', [Landlord\CategoryTreeController::class, 'move'])
        ->name('landlord.tenants.mercadologico.move');
    Route::post('tenants/{tenant}/mercadologico/move-products', [Landlord\CategoryTreeController::class, 'moveProducts'])
        ->name('landlord.tenants.mercadologico.move-products');
    // CRUD de categorias (JSON, via useHttp)
    Route::post('tenants/{tenant}/mercadologico/categories', [Landlord\CategoryTreeController::class, 'store'])
        ->name('landlord.tenants.mercadologico.categories.store');
    Route::put('tenants/{tenant}/mercadologico/categories/{category}', [Landlord\CategoryTreeController::class, 'update'])
        ->name('landlord.tenants.mercadologico.categories.update');
    Route::delete('tenants/{tenant}/mercadologico/categories/{category}', [Landlord\CategoryTreeController::class, 'destroy'])
        ->name('landlord.tenants.mercadologico.categories.destroy');
    Route::post('tenants/{tenant}/mercadologico/categories/{category}/restore', [Landlord\CategoryTreeController::class, 'restore'])
        ->name('landlord.tenants.mercadologico.categories.restore');

    Route::get('tenants/{tenant}/gondola-defaults', [Landlord\TenantGondolaDefaultsController::class, 'edit'])
        ->name('landlord.tenants.gondola-defaults.edit');
    Route::put('tenants/{tenant}/gondola-defaults', [Landlord\TenantGondolaDefaultsController::class, 'update'])
        ->name('landlord.tenants.gondola-defaults.update');

    Route::put('tenants/{tenant}/socialite-provider', [Landlord\TenantSocialiteProviderController::class, 'update'])
        ->name('landlord.tenants.socialite-provider.update');
    Route::delete('tenants/{tenant}/socialite-provider', [Landlord\TenantSocialiteProviderController::class, 'destroy'])
        ->name('landlord.tenants.socialite-provider.destroy');

    Route::get('tenants/{tenant}/integration', [Landlord\TenantIntegrationController::class, 'edit'])
        ->name('landlord.tenants.integration.edit');
    Route::put('tenants/{tenant}/integration', [Landlord\TenantIntegrationController::class, 'update'])
        ->name('landlord.tenants.integration.update');
    Route::post('tenants/{tenant}/integration/test-connection', [Landlord\TenantIntegrationController::class, 'testConnection'])
        ->name('landlord.tenants.integration.test-connection');
    Route::patch('tenants/{tenant}/integration/toggle-status', [Landlord\TenantIntegrationController::class, 'toggleStatus'])
        ->name('landlord.tenants.integration.toggle-status');
    Route::post('tenants/{tenant}/integration/run-import', [Landlord\TenantIntegrationController::class, 'runImport'])
        ->name('landlord.tenants.integration.run-import');
    Route::post('tenants/{tenant}/integration/run-post-import', [Landlord\TenantIntegrationController::class, 'runPostImport'])
        ->name('landlord.tenants.integration.run-post-import');
    Route::delete('tenants/{tenant}/integration', [Landlord\TenantIntegrationController::class, 'destroy'])
        ->name('landlord.tenants.integration.destroy');

    Route::middleware('tenant.module.active:'.ModuleSlug::KANBAN)->group(function (): void {
        Route::get('tenants/{tenant}/kanban/templates', [Landlord\WorkflowTemplateController::class, 'index'])
            ->name('landlord.tenants.kanban.templates.index');
        Route::get('tenants/{tenant}/kanban/templates/create', [Landlord\WorkflowTemplateController::class, 'create'])
            ->name('landlord.tenants.kanban.templates.create');
        Route::post('tenants/{tenant}/kanban/templates', [Landlord\WorkflowTemplateController::class, 'store'])
            ->name('landlord.tenants.kanban.templates.store');
        Route::post('tenants/{tenant}/kanban/templates/seed-defaults', [Landlord\WorkflowTemplateController::class, 'seedDefaultTemplates'])
            ->name('landlord.tenants.kanban.templates.seed-defaults');
        Route::get('tenants/{tenant}/kanban/templates/{template}/edit', [Landlord\WorkflowTemplateController::class, 'edit'])
            ->name('landlord.tenants.kanban.templates.edit');
        Route::put('tenants/{tenant}/kanban/templates/{template}', [Landlord\WorkflowTemplateController::class, 'update'])
            ->name('landlord.tenants.kanban.templates.update');
        Route::patch('tenants/{tenant}/kanban/templates/{template}/sync-users', [Landlord\WorkflowTemplateController::class, 'syncUsers'])
            ->name('landlord.tenants.kanban.templates.sync-users');
        Route::delete('tenants/{tenant}/kanban/templates/{template}', [Landlord\WorkflowTemplateController::class, 'destroy'])
            ->withTrashed()
            ->name('landlord.tenants.kanban.templates.destroy');
        Route::post('tenants/{tenant}/kanban/templates/{template}/restore', [Landlord\WorkflowTemplateController::class, 'restore'])
            ->withTrashed()
            ->name('landlord.tenants.kanban.templates.restore');
    });
});
