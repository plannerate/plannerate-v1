<?php

namespace App\Providers;

use App\Listeners\EnforceSingleSession;
use App\Http\Controllers\Auth\LoginAsController as SecureLoginAsController;
use Callcocam\LaravelRaptor\Http\Controllers\LoginAsController as PackageLoginAsController;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Horizon\Horizon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PackageLoginAsController::class, SecureLoginAsController::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Login único: derrubar sessões anteriores ao fazer novo login (configurável por tenant)
        Event::listen(Login::class, EnforceSingleSession::class);

        if (app()->environment('production') || app()->environment('staging')) {
            URL::forceScheme('https');
        }
        Inertia::share('settings', function () {
            return [
                'user' => auth()->user(),
                'tenant_id' => config('app.current_tenant_id'),
                'client_id' => config('app.current_domainable_id'),
                'store_id' => config('app.current_store_id'),
            ];
        });
        // Horizon Authorization
        $this->configureHorizon();
    }

    /**
     * Configure Laravel Horizon.
     */
    protected function configureHorizon(): void
    {
        Horizon::auth(function ($request) {
            // Em local e staging: sempre permitir (desenvolvimento)
            // Em produção: apenas usuários autenticados
            return app()->environment(['local', 'staging'])
                || ($request->user() !== null && app()->environment('production'));
        });
    }
}
