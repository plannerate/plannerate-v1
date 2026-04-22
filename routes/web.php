<?php

use App\Http\Controllers\Landlord\DashboardController as LandlordDashboardController;
use App\Http\Controllers\Landlord\PlanController;
use App\Http\Controllers\Landlord\TenantController as LandlordTenantController;
use App\Http\Controllers\Tenant\DashboardController as TenantDashboardController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

// ── LANDLORD (rota raiz, sem tenant) ──────────────────────────
Route::domain(config('app.landlord_domain'))->middleware(['web', 'auth'])->group(function (): void {
    Route::get('/dashboard', [LandlordDashboardController::class, 'index'])->name('dashboard');

    Route::resource('plans', PlanController::class)
        ->except(['show'])
        ->names('landlord.plans');

    Route::resource('tenants', LandlordTenantController::class)
        ->except(['show'])
        ->names('landlord.tenants');
});

// ── TENANT (rotas que exigem tenant ativo) ────────────────────
Route::domain(sprintf('{subdomain}.%s', config('app.landlord_domain')))
    ->middleware(['web', 'auth', NeedsTenant::class])
    ->name('tenant.')
    ->group(function (): void {
        Route::get('/dashboard', [TenantDashboardController::class, 'index'])->name('dashboard');
    });

require __DIR__.'/settings.php';
