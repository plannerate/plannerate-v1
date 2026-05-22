<?php

use App\Http\Controllers\Auth\TenantSocialiteController; 
use App\Http\Middleware\SetPermissionTeamContext; 
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant; 

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
})->name('home');

include __DIR__ . '/landlord.php';

// ── SOCIALITE OAuth — subdomain, sem auth, com NeedsTenant ───
Route::middleware(['web', NeedsTenant::class])
    ->group(function (): void {
        Route::get('/auth/{provider}/redirect', [TenantSocialiteController::class, 'redirect'])
            ->name('tenant.auth.socialite.redirect');
        Route::get('/auth/{provider}/callback', [TenantSocialiteController::class, 'callback'])
            ->name('tenant.auth.socialite.callback');
    });

include __DIR__ . '/tenant.php';

// Broadcasting auth precisa rodar no contexto do tenant para autenticar canais privados
Route::middleware(['web', 'auth', NeedsTenant::class, SetPermissionTeamContext::class])
    ->group(function (): void {
        Broadcast::routes();
    });
require __DIR__ . '/settings.php';
