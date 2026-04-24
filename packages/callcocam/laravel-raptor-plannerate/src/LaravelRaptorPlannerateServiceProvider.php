<?php

namespace Callcocam\LaravelRaptorPlannerate;

use Callcocam\LaravelRaptorFlow\Models\FlowExecution;
use Callcocam\LaravelRaptorPlannerate\Commands\Plannerate\TestAutoGenerateCommand;
use Callcocam\LaravelRaptorPlannerate\Commands\SyncPlannerateMigrationsCommand;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\GondolaController;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\PlanogramController;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Observers\GondolaObserver;
use Callcocam\LaravelRaptorPlannerate\Policies\FlowExecutionPolicy;
use Callcocam\LaravelRaptorPlannerate\Policies\GondolaPolicy;
use Callcocam\LaravelRaptorPlannerate\Policies\PlanogramPolicy;
use Callcocam\LaravelRaptorPlannerate\Providers\AutoPlanogramServiceProvider;
use Callcocam\LaravelRaptorPlannerate\Support\WorkflowMorphMap;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
        $this->registerMorphCompatibilityMap();
        $this->registerPolicyBindings();
        $this->registerObservers();
        $this->registerPlannerateRoutes();
        $this->registerExportRoutes();
        $this->registerEditorApiRoutes();
        $this->registerEditorPageRoutes();
    }

    protected function registerPolicyBindings(): void
    {
        Gate::policy(FlowExecution::class, FlowExecutionPolicy::class);
        Gate::policy(Planogram::class, PlanogramPolicy::class);
        Gate::policy(Gondola::class, GondolaPolicy::class);
    }

    protected function registerObservers(): void
    {
        Gondola::observe(GondolaObserver::class);
    }

    protected function registerMorphCompatibilityMap(): void
    {
        Relation::morphMap(WorkflowMorphMap::morphAliases());
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

        Route::middleware(['web', 'auth', 'tenant'])
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

    protected function registerEditorPageRoutes(): void
    {
        Route::middleware(['web', 'auth', 'tenant'])
            ->prefix('{subdomain}')
            ->name('tenant.')
            ->group(function () {
                Route::resource('gondolas', GondolaController::class)
                    ->names('gondolas');

                Route::resource('planograms', PlanogramController::class)
                    ->names('planograms');
            });
    }
}
