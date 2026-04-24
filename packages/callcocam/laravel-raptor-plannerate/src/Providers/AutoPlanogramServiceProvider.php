<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Providers;

use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate\AutoPlanogramController;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate\AutoPlanogramService;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate\LayoutOptimizationService;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate\MerchandisingRulesService;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate\ProductSelectionService;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\SectionGenerate\SectionAIAllocator;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\SectionGenerate\SectionContextBuilder;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\SectionGenerate\SectionPersistenceService;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\SectionGenerate\SectionPlanogramService;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\SectionGenerate\SectionRulesAllocator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider para Geração Automática de Planogramas
 *
 * Este provider é responsável por:
 * - Registrar services no container (apenas se feature estiver ativa)
 * - Registrar rotas da API (apenas se feature estiver ativa)
 * - Controlar feature flag via configuração
 *
 * Para habilitar: PLANNERATE_AUTO_GENERATE_ENABLED=true no .env
 */
class AutoPlanogramServiceProvider extends ServiceProvider
{
    /**
     * Registrar services no container
     *
     * Services são registrados como Singleton para performance
     * Só executa se feature estiver habilitada
     */
    public function register(): void
    {
        // Verifica se feature está habilitada
        if (! $this->isEnabled()) {
            return;
        }

        // Registra services como Singleton
        $this->app->singleton(AutoPlanogramService::class);
        $this->app->singleton(ProductSelectionService::class);
        $this->app->singleton(MerchandisingRulesService::class);
        $this->app->singleton(LayoutOptimizationService::class);
        $this->app->singleton(SectionContextBuilder::class);
        $this->app->singleton(SectionRulesAllocator::class);
        $this->app->singleton(SectionAIAllocator::class);
        $this->app->singleton(SectionPersistenceService::class);
        $this->app->singleton(SectionPlanogramService::class);
    }

    /**
     * Bootstrap services
     *
     * Registra rotas da API
     * Só executa se feature estiver habilitada
     */
    public function boot(): void
    {
        // Verifica se feature está habilitada
        if (! $this->isEnabled()) {
            return;
        }

        // Registra rotas da API
        $this->registerRoutes();
    }

    /**
     * Registrar rotas da API de geração automática
     */
    protected function registerRoutes(): void
    {
        Route::middleware(['web', 'auth', 'tenant'])
            ->prefix('api/tenant/plannerate')
            ->name('api.tenant.plannerate.')
            ->group(function () {
                // Geração automática (algoritmo tradicional)
                Route::post(
                    'gondolas/{gondola}/auto-generate',
                    [AutoPlanogramController::class, 'generate']
                )->name('gondolas.auto-generate');

                // Geração com IA (Prism PHP)
                Route::post(
                    'gondolas/{gondola}/ia-generate',
                    [AutoPlanogramController::class, 'iaGenerate']
                )->name('gondolas.ia-generate');

                // Geração por section (módulo) com regras
                Route::post(
                    'gondolas/{gondola}/generate-by-sections',
                    [AutoPlanogramController::class, 'generateBySections']
                )->name('gondolas.generate-by-sections');
            });
    }

    /**
     * Verifica se a feature de geração automática está habilitada
     */
    protected function isEnabled(): bool
    {
        return (bool) config('plannerate.features.auto_generate', false);
    }
}
