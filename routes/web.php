<?php

use App\Http\Controllers\Landlord\DashboardController as LandlordDashboardController;
use App\Http\Controllers\Landlord\PermissionController;
use App\Http\Controllers\Landlord\PlanController;
use App\Http\Controllers\Landlord\RoleController;
use App\Http\Controllers\Landlord\TenantController as LandlordTenantController;
use App\Http\Controllers\Landlord\TenantUserAccessController;
use App\Http\Controllers\Landlord\UserController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\DashboardController as TenantDashboardController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\ProductImageController;
use App\Http\Middleware\SetPermissionTeamContext;
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

    Route::resource('users', UserController::class)
        ->except(['show'])
        ->names('landlord.users');

    Route::resource('permissions', PermissionController::class)
        ->except(['show'])
        ->names('landlord.permissions');

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

        Route::post('products/image/upload', [ProductImageController::class, 'upload'])
            ->name('products.image.upload');
        Route::post('products/image/ai/process', [ProductImageController::class, 'process'])
            ->name('products.image.ai.process');
        Route::get('products/image/ai/operations/{operation}', [ProductImageController::class, 'status'])
            ->name('products.image.ai.status');
    });

require __DIR__.'/settings.php';
