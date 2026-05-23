<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Landlord;
use App\Http\Middleware\SetPermissionTeamContext;
use App\Support\Modules\ModuleSlug;

// ── LANDLORD (rota raiz, sem tenant) ──────────────────────────
Route::domain(config('app.landlord_domain'))->middleware(['web', 'auth', SetPermissionTeamContext::class])->group(function (): void {
    Broadcast::routes();

    Route::get('/dashboard', [Landlord\DashboardController::class, 'index'])->name('dashboard');

    Route::resource('plans', Landlord\PlanController::class)
        ->except(['show'])
        ->names('landlord.plans');

    Route::resource('tenants', Landlord\TenantController::class)
        ->except(['show'])
        ->names('landlord.tenants');
    Route::get('tenants/export', [Landlord\TenantController::class, 'exportConfigurations'])
        ->name('landlord.tenants.export');
    Route::post('tenants/import', [Landlord\TenantController::class, 'importConfigurations'])
        ->name('landlord.tenants.import');

    Route::resource('roles', Landlord\RoleController::class)
        ->except(['show'])
        ->names('landlord.roles');

    Route::resource('modules', Landlord\ModuleController::class)
        ->except(['show'])
        ->names('landlord.modules');

    Route::resource('integration-apis', Landlord\IntegrationApiController::class)
        ->except(['show'])
        ->names('landlord.integration-apis');
    Route::get('integration-apis/export', [Landlord\IntegrationApiController::class, 'exportConfigurations'])
        ->name('landlord.integration-apis.export');
    Route::post('integration-apis/import', [Landlord\IntegrationApiController::class, 'importConfigurations'])
        ->name('landlord.integration-apis.import');

    Route::resource('users', Landlord\UserController::class)
        ->except(['show'])
        ->names('landlord.users');

    Route::post('permissions/sync', [Landlord\PermissionController::class, 'sync'])
        ->name('landlord.permissions.sync');

    Route::resource('permissions', Landlord\PermissionController::class)
        ->except(['show'])
        ->names('landlord.permissions');

    Route::resource('ean-references', Landlord\EanReferenceController::class)
        ->except(['show'])
        ->names('landlord.ean-references');

    Route::resource('useful-links', Landlord\UsefulLinkController::class)
        ->except(['show'])
        ->names('landlord.useful-links');

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
    Route::delete('tenants/{tenant}/integration', [Landlord\TenantIntegrationController::class, 'destroy'])
        ->name('landlord.tenants.integration.destroy');

    Route::middleware('tenant.module.active:' . ModuleSlug::KANBAN)->group(function (): void {
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
            ->name('landlord.tenants.kanban.templates.destroy');
    });
});
