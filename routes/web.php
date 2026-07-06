<?php

use App\Http\Controllers\Auth\TenantSocialiteController;
use App\Http\Controllers\Tenant\ImpersonationController;
use App\Http\Controllers\Tenant\PasswordSetupController;
use App\Http\Middleware\SetPermissionTeamContext;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;

// Route::inertia('/',  'Welcome')->name('home');

Route::get('/hetail', function () {
    return redirect()->route('dashboard');
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
