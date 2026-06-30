<?php

namespace Callcocam\LaravelRaptorPlannerate;

use App\Http\Middleware\SetPermissionTeamContext;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\GreedyShelfPlacer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\PlacementEngineInterface;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\PlanogramWriter;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Placement\PlanogramWriterInterface;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\ProductWidthResolver;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Scoring\CompositeScorer;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Scoring\ProductScorerInterface;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\PlanogramValidator;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\Rules\AdjacencyRule;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\Rules\BlockIntegrityRule;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\Rules\EmptyShelfRule;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\Rules\FacingMinimumRule;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\Rules\SectionCapacityRule;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\Rules\ShelfLevelRule;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Validation\Rules\UnplacedProductsRule;
use Callcocam\LaravelRaptorPlannerate\Commands\SyncPlannerateMigrationsCommand;
use Callcocam\LaravelRaptorPlannerate\Events\LayerRemovedEvent;
use Callcocam\LaravelRaptorPlannerate\Listeners\HandleLayerRemovedForRejectedProducts;
use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Planogram;
use Callcocam\LaravelRaptorPlannerate\Policies\GondolaPolicy;
use Callcocam\LaravelRaptorPlannerate\Policies\PlanogramPolicy;
use Illuminate\Support\Facades\Event;
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
            ->hasViews('plannerate')
            ->hasCommand(SyncPlannerateMigrationsCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->registerAutoPlanogramBindings();
    }

    public function packageBooted(): void
    {
        $this->registerPolicyBindings();
        $this->registerEventListeners();
        $this->registerPlannerateRoutes();
        $this->registerExportRoutes();
        $this->registerEditorApiRoutes();
        $this->registerGenerationRoutes();
        $this->registerTemplateRoutes();
    }

    /**
     * Listeners de eventos do domínio planograma.
     * Antes registrados no EventServiceProvider do app — fundidos aqui na Etapa 6.
     */
    protected function registerEventListeners(): void
    {
        Event::listen(LayerRemovedEvent::class, HandleLayerRemovedForRejectedProducts::class);
    }

    /**
     * Rotas da API interna do Auto-Planograma (sem tenant.client.redirect),
     * espelhando o grupo original de routes/tenant.php do app.
     */
    protected function registerGenerationRoutes(): void
    {
        $generationRouteFile = __DIR__.'/../routes/generation.php';

        if (! file_exists($generationRouteFile)) {
            return;
        }

        Route::middleware(['web', 'auth', NeedsTenant::class, SetPermissionTeamContext::class])
            ->name('tenant.')
            ->group($generationRouteFile);
    }

    /**
     * Rotas de templates de planograma e regras de produto (com tenant.client.redirect),
     * espelhando o grupo "tenant principal" de routes/tenant.php do app.
     */
    protected function registerTemplateRoutes(): void
    {
        $templateRouteFile = __DIR__.'/../routes/templates.php';

        if (! file_exists($templateRouteFile)) {
            return;
        }

        Route::middleware(['web', 'auth', NeedsTenant::class, SetPermissionTeamContext::class, 'tenant.client.redirect'])
            ->name('tenant.')
            ->group($templateRouteFile);
    }

    /**
     * Bindings DI do motor de geração automática (AutoPlanograma).
     *
     * Antes viviam em App\Providers\AutoPlanogramServiceProvider — fundidos aqui
     * quando o AutoPlanograma migrou para dentro do pacote (Etapa 5 da refatoração).
     */
    protected function registerAutoPlanogramBindings(): void
    {
        $this->app->singleton(ProductWidthResolver::class, fn () => new ProductWidthResolver(
            defaultWidth: 10.0,
            maxPlausible: 60.0,
        ));

        $this->app->bind(ProductScorerInterface::class, CompositeScorer::class);
        $this->app->bind(PlacementEngineInterface::class, GreedyShelfPlacer::class);
        $this->app->bind(PlanogramWriterInterface::class, PlanogramWriter::class);

        $this->app->singleton(PlanogramValidator::class, function () {
            return new PlanogramValidator([
                new BlockIntegrityRule,
                new AdjacencyRule,
                new ShelfLevelRule,
                new FacingMinimumRule,
                new SectionCapacityRule,
                new EmptyShelfRule,
                new UnplacedProductsRule,
            ]);
        });
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
        Route::middleware(['web', 'auth', NeedsTenant::class, SetPermissionTeamContext::class])
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
}
