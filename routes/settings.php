<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Middleware\SetPermissionTeamContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', SetPermissionTeamContext::class])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])
        ->middleware('impersonation.block')
        ->name('profile.update');
});

Route::middleware(['auth', SetPermissionTeamContext::class, 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])
        ->middleware('impersonation.block')
        ->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware(['throttle:6,1', 'impersonation.block'])
        ->name('user-password.update');

    Route::delete('settings/other-browser-sessions', [SecurityController::class, 'destroyOtherSessions'])
        ->middleware(['auth.session', 'throttle:6,1', 'impersonation.block'])
        ->name('other-browser-sessions.destroy');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});
