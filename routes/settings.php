<?php

use App\Http\Controllers\Settings\ClientSettingsController;
use App\Http\Controllers\Settings\IntegrationSyncDashboardController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\StoreSettingsController;
use App\Http\Controllers\Settings\TenantSettingsController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
$context = request()->getContext();
Route::middleware(['auth', $context])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/integrations', IntegrationSyncDashboardController::class)
        ->name('integrations.dashboard');

    Route::get('settings/tenant', [TenantSettingsController::class, 'edit'])->name('tenant-settings.edit');
    Route::patch('settings/tenant', [TenantSettingsController::class, 'update'])->name('tenant-settings.update');

    Route::get('settings/client', [ClientSettingsController::class, 'edit'])->name('client-settings.edit');
    Route::patch('settings/client', [ClientSettingsController::class, 'update'])->name('client-settings.update');

    Route::get('settings/store', [StoreSettingsController::class, 'edit'])->name('store-settings.edit');
    Route::patch('settings/store', [StoreSettingsController::class, 'update'])->name('store-settings.update');
});

