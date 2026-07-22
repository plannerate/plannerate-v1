<?php

use Callcocam\LaravelIntegrations\Events\IntegrationProcessFinished;
use Callcocam\LaravelIntegrations\Events\IntegrationProcessStarted;
use Callcocam\LaravelIntegrations\Events\ProductSalesSynced;
use Callcocam\LaravelIntegrations\Events\TenantIsolationCheckEvent;
use Callcocam\LaravelIntegrations\Jobs\Cleanup\CleanupOldSalesJob;
use Callcocam\LaravelIntegrations\Jobs\Cleanup\CleanupOrphanSalesJob;
use Callcocam\LaravelIntegrations\Jobs\Cleanup\DeactivateInactiveProductsJob;
use Callcocam\LaravelIntegrations\Jobs\Cleanup\NotifyCleanupCompletedJob;
use Callcocam\LaravelIntegrations\Jobs\Cleanup\RestoreSoldProductsJob;
use Callcocam\LaravelIntegrations\Jobs\DiscoverIntegrationPagesJob;
use Callcocam\LaravelIntegrations\Jobs\FetchIntegrationPageJob;
use Callcocam\LaravelIntegrations\Jobs\Maintenance\FinalizeTenantImportsJob;
use Callcocam\LaravelIntegrations\Jobs\Maintenance\RecalculateTenantMonthlySalesSummariesJob;
use Callcocam\LaravelIntegrations\Jobs\ProcessPageResponseJob;
use Callcocam\LaravelIntegrations\Jobs\RunIntegrationPipelineJob;
use Callcocam\LaravelIntegrations\Jobs\SyncSingleProductJob;
use Callcocam\LaravelIntegrations\Models\IntegrationApi;
use Callcocam\LaravelIntegrations\Models\IntegrationImportRun;
use Callcocam\LaravelIntegrations\Models\TenantIntegration;

/*
 * Shim de UMA release: mapeia os FQCNs antigos do motor de integrações para os do
 * pacote `callcocam/laravel-integrations`.
 *
 * Existe por causa de payload serializado que atravessa o deploy:
 *
 * - job já na fila quando a release subiu (`imports-fetch`, `imports-process`,
 *   `maintenance`) — o payload carrega a classe pelo nome antigo;
 * - linha em `failed_jobs` que alguém vá reprocessar depois;
 * - `SerializesModels` de model movido dentro de um job antigo.
 *
 * Sem os aliases, esses jobs morrem com "Class not found" e o retry nunca funciona.
 *
 * A janela do corte drena as filas antes do deploy, então na prática isto cobre o
 * straggler e o `failed_jobs` histórico — não o caso comum. **Remover na Fase 3**,
 * depois de um ciclo diário verde e de limpar/reprocessar `failed_jobs`.
 *
 * Carregado via `autoload.files` no composer.json, portanto roda em todo request e
 * em todo worker.
 */

/** @var array<string, class-string> mapa FQCN antigo => novo */
$legacyIntegrationClassAliases = [
    // Models — os que aparecem em payload serializado via SerializesModels.
    'App\Models\IntegrationApi' => IntegrationApi::class,
    'App\Models\IntegrationImportRun' => IntegrationImportRun::class,
    'App\Models\TenantIntegration' => TenantIntegration::class,

    // Jobs do pipeline — os que de fato ficam enfileirados.
    'App\Jobs\Integrations\DiscoverIntegrationPagesJob' => DiscoverIntegrationPagesJob::class,
    'App\Jobs\Integrations\FetchIntegrationPageJob' => FetchIntegrationPageJob::class,
    'App\Jobs\Integrations\ProcessPageResponseJob' => ProcessPageResponseJob::class,
    'App\Jobs\Integrations\RunIntegrationPipelineJob' => RunIntegrationPipelineJob::class,
    'App\Jobs\Integrations\SyncSingleProductJob' => SyncSingleProductJob::class,
    'App\Jobs\Integrations\Maintenance\FinalizeTenantImportsJob' => FinalizeTenantImportsJob::class,
    'App\Jobs\Integrations\Maintenance\RecalculateTenantMonthlySalesSummariesJob' => RecalculateTenantMonthlySalesSummariesJob::class,

    // Jobs de cleanup — despachados pelo sync:cleanup, podem estar na fila maintenance.
    'App\Jobs\Cleanup\CleanupOldSalesJob' => CleanupOldSalesJob::class,
    'App\Jobs\Cleanup\CleanupOrphanSalesJob' => CleanupOrphanSalesJob::class,
    'App\Jobs\Cleanup\DeactivateInactiveProductsJob' => DeactivateInactiveProductsJob::class,
    'App\Jobs\Cleanup\NotifyCleanupCompletedJob' => NotifyCleanupCompletedJob::class,
    'App\Jobs\Cleanup\RestoreSoldProductsJob' => RestoreSoldProductsJob::class,

    // Eventos de broadcast — o listener é resolvido pelo nome da classe do evento.
    'App\Events\Tenant\IntegrationProcessStarted' => IntegrationProcessStarted::class,
    'App\Events\Tenant\IntegrationProcessFinished' => IntegrationProcessFinished::class,
    'App\Events\Tenant\ProductSalesSynced' => ProductSalesSynced::class,
    'App\Events\Tenant\TenantIsolationCheckEvent' => TenantIsolationCheckEvent::class,
];

foreach ($legacyIntegrationClassAliases as $legacy => $current) {
    // class_exists($legacy, false) sem autoload: só cria o alias se o nome antigo
    // ainda não estiver definido, para não colidir num rollback parcial.
    if (! class_exists($legacy, false)) {
        class_alias($current, $legacy);
    }
}
