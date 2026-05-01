<?php

namespace Callcocam\LaravelRaptorPlannerate;

use App\Http\Middleware\SetPermissionTeamContext;
use Callcocam\LaravelRaptorPlannerate\Commands\Plannerate\TestAutoGenerateCommand;
use Callcocam\LaravelRaptorPlannerate\Commands\SyncPlannerateMigrationsCommand;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Policies\GondolaPolicy;
use Callcocam\LaravelRaptorPlannerate\Policies\PlanogramPolicy;
use Callcocam\LaravelRaptorPlannerate\Providers\AutoPlanogramServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;

class LaravelRaptorPlannerateServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-raptor-plannerate')
            ->hasConfigFile('plannerate')
            ->hasCommand(SyncPlannerateMigrationsCommand::class)
            ->hasCommand(TestAutoGenerateCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->register(AutoPlanogramServiceProvider::class);
    }

    public function packageBooted(): void
    {
        $this->registerPolicyBindings();
        $this->registerPlannerateRoutes();
        $this->registerExportRoutes();
        $this->registerEditorApiRoutes();
        $this->registerEditorPageRoutes();
    }

    protected function registerPolicyBindings(): void
    {
        Gate::policy(Planogram::class, PlanogramPolicy::class);
        Gate::policy(Gondola::class, GondolaPolicy::class);
    }

    protected function registerPlannerateRoutes(): void
    {
        $plannerateRouteFile = __DIR__.'/../routes/plannerate.php';

        if (! file_exists($plannerateRouteFile)) {
            return;
        }

        Route::middleware('web')->group($plannerateRouteFile);
    }

    protected function registerEditorApiRoutes(): void
    {
        $editorRouteFile = __DIR__.'/../routes/editor.php';

        if (! file_exists($editorRouteFile)) {
            return;
        }

        /*
         * Estas rotas são consumidas no subdomínio do tenant (ex.: franciosi.app.test).
         * Registrá-las apenas no domínio "central" faz o host do tenant retornar 404.
         */
        Route::domain(sprintf('{subdomain}.%s', config('app.landlord_domain')))
            ->middleware(['web', 'auth', NeedsTenant::class, SetPermissionTeamContext::class])
            ->group($editorRouteFile);
    }

    protected function registerExportRoutes(): void
    {
        $exportRouteFile = __DIR__.'/../routes/export.php';

        if (! file_exists($exportRouteFile)) {
            return;
        }

        Route::middleware('web')->group($exportRouteFile);
    }

    protected function registerEditorPageRoutes(): void {}
}
