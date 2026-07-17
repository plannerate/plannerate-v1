<?php

use App\Http\Controllers\Auth\TenantSocialiteController;
use App\Http\Controllers\Public\DimensionCorrectionController;
use App\Http\Controllers\Tenant\ImpersonationController;
use App\Http\Controllers\Tenant\PasswordSetupController;
use App\Http\Middleware\SetPermissionTeamContext;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;

// Route::inertia('/',  'Welcome')->name('home');

// Redireciona para "/" do host atual, e não para route('dashboard'): a rota
// dashboard landlord tem Route::domain() fixo, então em um subdomínio de tenant
// ela gera URL cross-origin (CORS/403). "/" resolve para o dashboard do próprio
// host — tenant.dashboard no subdomínio, dashboard no landlord.
Route::get('/hetail', function () {
    return redirect('/');
})->name('home');

include __DIR__.'/landlord.php';

// ── SOCIALITE OAuth — subdomain, sem auth, com NeedsTenant ───
Route::middleware(['web', NeedsTenant::class])
    ->group(function (): void {
        Route::get('/auth/{provider}/redirect', [TenantSocialiteController::class, 'redirect'])
            ->name('tenant.auth.socialite.redirect');
        Route::get('/auth/{provider}/callback', [TenantSocialiteController::class, 'callback'])
            ->name('tenant.auth.socialite.callback');
    });

// ── IMPERSONATION consume — subdomain, sem auth, com NeedsTenant ───
Route::middleware(['web', NeedsTenant::class])
    ->group(function (): void {
        Route::get('/impersonation/consume/{code}', [ImpersonationController::class, 'consume'])
            ->middleware('throttle:10,1')
            ->name('tenant.impersonation.consume');
    });

// ── PASSWORD SETUP consume — subdomain, sem auth, com NeedsTenant ───
Route::middleware(['web', NeedsTenant::class])
    ->group(function (): void {
        Route::get('/password/setup/{code}', [PasswordSetupController::class, 'edit'])
            ->middleware('throttle:10,1')
            ->name('tenant.password-setup.edit');
        Route::post('/password/setup/{code}', [PasswordSetupController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('tenant.password-setup.update');
    });

// ── CORREÇÃO PÚBLICA DE DIMENSÕES — subdomain, sem auth, com NeedsTenant ───
Route::middleware(['web', NeedsTenant::class, 'dimension.share'])
    ->group(function (): void {
        Route::get('/dimensoes/{code}', [DimensionCorrectionController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('public.dimensions.show');
        Route::patch('/dimensoes/{code}/produtos/{product}', [DimensionCorrectionController::class, 'update'])
            ->middleware('throttle:120,1')
            ->name('public.dimensions.update');
    });
// Route::prefix('admin')
// ->group(function (): void {

include __DIR__.'/tenant.php';
// });

// Broadcasting auth precisa rodar no contexto do tenant para autenticar canais privados
Route::middleware(['web', 'auth', NeedsTenant::class, SetPermissionTeamContext::class])
    ->group(function (): void {
        Broadcast::routes();
    });
require __DIR__.'/settings.php';
