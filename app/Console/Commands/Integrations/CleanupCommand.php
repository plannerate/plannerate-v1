<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Cleanup\CleanupOldSalesJob;
use App\Jobs\Cleanup\CleanupOrphanSalesJob;
use App\Jobs\Cleanup\DeactivateInactiveProductsJob;
use App\Jobs\Cleanup\NotifyCleanupCompletedJob;
use App\Jobs\Cleanup\RestoreSoldProductsJob;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\ImportQueueMonitor;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CleanupCommand extends Command
{
    private const INACTIVE_DAYS = 120;

    /**
     * Janela de frescor: sem NENHUMA venda nos últimos N dias, o import diário
     * provavelmente está quebrado — desativar/limpar com base nesse snapshot
     * desatualizado apagaria dados indevidamente.
     */
    private const SALES_FRESHNESS_DAYS = 3;

    protected $signature = 'sync:cleanup
        {--tenant= : ID do tenant específico}
        {--force : Ignora as travas de segurança (backlog das filas de import e frescor das vendas)}';

    protected $description = 'Executa limpeza automática por TenantIntegration ativa';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->importQueuesAreIdle()) {
            return self::FAILURE;
        }

        $integrations = $this->getActiveIntegrations();

        if ($integrations->isEmpty()) {
            $this->warn('Nenhuma TenantIntegration ativa encontrada para cleanup.');

            return self::SUCCESS;
        }

        foreach ($integrations as $integration) {
            $this->processIntegration($integration);
        }

        $this->newLine();
        $this->info('Verificação concluída. Jobs de limpeza despachados; a notificação sai ao fim de cada corrente.');

        return self::SUCCESS;
    }

    /**
     * Trava de segurança: com jobs de importação ainda pendentes, o cleanup
     * agiria sobre um snapshot parcial (ex.: desativar produtos cujas vendas
     * ainda estão na fila imports-process).
     */
    protected function importQueuesAreIdle(): bool
    {
        $pendingByQueue = ImportQueueMonitor::pendingJobsByQueue();
        $totalPending = array_sum($pendingByQueue);

        if ($totalPending === 0) {
            return true;
        }

        $this->error(sprintf(
            'Filas de importação com %d job(s) pendente(s); cleanup abortado para não apagar dados com base em import parcial (use --force para ignorar).',
            $totalPending,
        ));

        Log::warning('sync:cleanup abortado: backlog nas filas de importação', [
            'pending' => $pendingByQueue,
        ]);

        return false;
    }

    /**
     * @return Collection<int, TenantIntegration>
     */
    protected function getActiveIntegrations(): Collection
    {
        $tenantId = $this->option('tenant');

        return TenantIntegration::query()
            ->with(['api', 'tenant'])
            ->where('is_active', true)
            ->whereHas('api', fn ($query) => $query->where('is_active', true))
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->when(is_string($tenantId) && $tenantId !== '', fn ($query) => $query->where('tenant_id', $tenantId))
            ->get();
    }

    protected function processIntegration(TenantIntegration $integration): void
    {
        $tenant = $integration->tenant;

        if (! $tenant instanceof Tenant) {
            $this->error(sprintf('Integração %s sem tenant relacionado; cleanup ignorado.', $integration->id));

            return;
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info("🏢 {$tenant->name} | Integração {$integration->id}");
        $this->info('═══════════════════════════════════════════════════════');

        $configuredTenantConnection = config('multitenancy.tenant_database_connection_name');
        $connection = (string) ($configuredTenantConnection ?: config('database.default'));
        $shouldSwitchTenantContext = is_string($configuredTenantConnection) && $configuredTenantConnection !== '';

        $tenantDatabase = is_string($tenant->getAttribute('database')) ? trim((string) $tenant->getAttribute('database')) : '';
        if ($shouldSwitchTenantContext && $tenantDatabase === '') {
            $this->error(sprintf('Tenant %s sem database configurado; cleanup ignorado.', $tenant->id));

            return;
        }

        $process = fn () => $this->processIntegrationOnConnection(
            tenant: $tenant,
            integration: $integration,
            connection: $connection,
            shouldSwitchTenantContext: $shouldSwitchTenantContext,
        );

        if ($shouldSwitchTenantContext) {
            $tenant->execute($process);

            return;
        }

        $process();
    }

    /**
     * Os counts abaixo são só para o relatório do console: os jobs recebem o
     * CRITÉRIO (cutoff/condição) e re-derivam as linhas em chunks na execução —
     * o payload da fila não carrega mais arrays de IDs potencialmente enormes.
     */
    protected function processIntegrationOnConnection(
        Tenant $tenant,
        TenantIntegration $integration,
        string $connection,
        bool $shouldSwitchTenantContext,
    ): void {
        $tenantId = (string) $tenant->id;
        $jobs = collect();

        if ($this->countOrphanSales($tenantId, $connection) > 0) {
            $jobs->push(new CleanupOrphanSalesJob($tenantId, $connection, $shouldSwitchTenantContext));
        }

        $salesAreFresh = (bool) $this->option('force') || $this->salesDataIsFresh($tenantId, $connection);

        if (! $salesAreFresh) {
            $this->warn(sprintf(
                '      Nenhuma venda nos últimos %d dias; limpeza de vendas antigas e desativação de produtos puladas por segurança (use --force para ignorar).',
                self::SALES_FRESHNESS_DAYS,
            ));

            Log::warning('Cleanup destrutivo pulado: vendas sem registro recente', [
                'tenant_id' => $tenantId,
                'freshness_days' => self::SALES_FRESHNESS_DAYS,
            ]);
        }

        if ($salesAreFresh) {
            $retentionsByPath = $this->getOldSalesRetentionsByPath($integration);

            if ($retentionsByPath === []) {
                $this->line('      Nenhum path com initial_days > 0 encontrado para limpeza de vendas antigas');
            } else {
                // União dos cortes por path = corte mais recente (menor retenção).
                $oldSalesCutoff = now()->subDays(min($retentionsByPath))->toDateString();

                if ($this->countOldSales($tenantId, $connection, $oldSalesCutoff, $retentionsByPath) > 0) {
                    $jobs->push(new CleanupOldSalesJob($tenantId, $oldSalesCutoff, $connection, $shouldSwitchTenantContext));
                }
            }

            $inactiveCutoff = now()->subDays(self::INACTIVE_DAYS)->toDateString();

            if ($this->countInactiveProducts($tenantId, $connection, $inactiveCutoff) > 0) {
                $jobs->push(new DeactivateInactiveProductsJob($tenantId, $inactiveCutoff, $connection, $shouldSwitchTenantContext));
            }
        }

        $restoreSold = $this->checkDeletedProductsWithSales($tenantId, $connection, self::INACTIVE_DAYS);

        if ($restoreSold['ids'] !== []) {
            $jobs->push(new RestoreSoldProductsJob($tenantId, $restoreSold['ids'], $connection, $shouldSwitchTenantContext));
        }

        if ($jobs->isNotEmpty()) {
            $jobCount = $jobs->count();

            // Notificação só depois que a corrente inteira rodou (não no despacho);
            // catch() alerta quando um elo falha e os seguintes deixam de rodar.
            $jobs->push(new NotifyCleanupCompletedJob($tenantId, (string) $tenant->name, $jobCount));

            Bus::chain($jobs->all())
                ->catch(function (Throwable $e) use ($tenantId): void {
                    Log::error('Corrente de cleanup falhou; jobs seguintes da corrente não rodaram', [
                        'tenant_id' => $tenantId,
                        'error' => $e->getMessage(),
                    ]);
                })
                ->dispatch();

            $this->info("   {$jobCount} jobs de limpeza despachados");
        }
    }

    /**
     * Dataset de vendas é considerado "fresco" quando existe venda registrada
     * nos últimos SALES_FRESHNESS_DAYS — evidência de que o import diário roda.
     */
    protected function salesDataIsFresh(string $tenantId, string $connection): bool
    {
        $maxSaleDate = DB::connection($connection)
            ->table('sales')
            ->where('tenant_id', $tenantId)
            ->max('sale_date');

        if ($maxSaleDate === null) {
            return false;
        }

        return Carbon::parse((string) $maxSaleDate)
            ->greaterThanOrEqualTo(now()->subDays(self::SALES_FRESHNESS_DAYS)->startOfDay());
    }

    /**
     * @return array<string, int>
     */
    protected function getOldSalesRetentionsByPath(TenantIntegration $integration): array
    {
        $paths = data_get($integration->api?->requests ?? [], 'paths', []);

        if (! is_array($paths)) {
            return [];
        }

        $retentionsByPath = [];

        foreach ($paths as $pathKey => $pathConfig) {
            if (! is_string($pathKey) || ! is_array($pathConfig)) {
                continue;
            }

            $initialDays = (int) data_get($pathConfig, 'initial_days', 0);

            if ($initialDays > 0) {
                $retentionsByPath[$pathKey] = $initialDays;
            }
        }

        return $retentionsByPath;
    }

    /**
     * @param  array<string, int>  $retentionsByPath
     */
    protected function countOldSales(string $tenantId, string $connection, string $cutoffDate, array $retentionsByPath): int
    {
        $count = DB::connection($connection)
            ->table('sales')
            ->where('tenant_id', $tenantId)
            ->where('sale_date', '<', $cutoffDate)
            ->count();

        if ($count === 0) {
            $this->line('      Nenhuma venda anterior ao período de retenção');

            return 0;
        }

        $this->warn("      {$count} venda(s) anteriores ao período (corte {$cutoffDate})");

        Log::info('Vendas antigas identificadas', [
            'tenant_id' => $tenantId,
            'count' => $count,
            'cutoff_date' => $cutoffDate,
            'retentions_by_path' => $retentionsByPath,
        ]);

        return $count;
    }

    protected function countOrphanSales(string $tenantId, string $connection): int
    {
        $totalProducts = DB::connection($connection)
            ->table('products')
            ->where('tenant_id', $tenantId)
            ->count();

        if ($totalProducts === 0) {
            $this->warn('      Nenhum produto encontrado; deleção de vendas ignorada por segurança.');

            return 0;
        }

        $count = DB::connection($connection)
            ->table('sales')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('product_id')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('products')
                    ->whereColumn('products.id', 'sales.product_id')
                    ->whereColumn('products.tenant_id', 'sales.tenant_id');
            })
            ->count();

        if ($count === 0) {
            $this->line('      Nenhuma venda órfã encontrada');

            return 0;
        }

        $this->warn("      {$count} vendas sem produto correspondente");

        Log::info('Vendas órfãs identificadas', [
            'tenant_id' => $tenantId,
            'count' => $count,
        ]);

        return $count;
    }

    protected function countInactiveProducts(string $tenantId, string $connection, string $cutoffDate): int
    {
        $totalSales = DB::connection($connection)
            ->table('sales')
            ->where('tenant_id', $tenantId)
            ->count();

        if ($totalSales === 0) {
            $this->warn('      Nenhuma venda encontrada; deleção de produtos ignorada por segurança.');

            return 0;
        }

        $count = DB::connection($connection)
            ->table('products')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotExists(function ($query) use ($cutoffDate): void {
                $query->select(DB::raw(1))
                    ->from('sales')
                    ->whereColumn('sales.product_id', 'products.id')
                    ->whereColumn('sales.tenant_id', 'products.tenant_id')
                    ->where('sales.sale_date', '>=', $cutoffDate);
            })
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('layers')
                    ->whereColumn('layers.product_id', 'products.id')
                    ->whereColumn('layers.tenant_id', 'products.tenant_id')
                    ->whereNull('layers.deleted_at');
            })
            ->count();

        if ($count === 0) {
            $this->line('      Todos os produtos têm vendas no período');

            return 0;
        }

        $this->warn("      {$count} produtos sem vendas no período");

        Log::info('Produtos inativos identificados', [
            'tenant_id' => $tenantId,
            'count' => $count,
            'cutoff_date' => $cutoffDate,
        ]);

        return $count;
    }

    /**
     * @return array{count: int, ids: array<int, string>}
     */
    protected function checkDeletedProductsWithSales(string $tenantId, string $connection, int $days): array
    {
        $cutoffDate = now()->subDays($days)->toDateString();

        $deletedWithSales = DB::connection($connection)
            ->table('products')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('deleted_at')
            ->where(function ($query) use ($cutoffDate): void {
                $query->whereExists(function ($salesQuery) use ($cutoffDate): void {
                    $salesQuery->select(DB::raw(1))
                        ->from('sales')
                        ->whereColumn('sales.product_id', 'products.id')
                        ->whereColumn('sales.tenant_id', 'products.tenant_id')
                        ->where('sales.sale_date', '>=', $cutoffDate);
                })->orWhereExists(function ($layersQuery): void {
                    $layersQuery->select(DB::raw(1))
                        ->from('layers')
                        ->whereColumn('layers.product_id', 'products.id')
                        ->whereColumn('layers.tenant_id', 'products.tenant_id')
                        ->whereNull('layers.deleted_at');
                });
            })
            ->select('id', 'name', 'ean', 'deleted_at')
            ->get();

        $count = $deletedWithSales->count();

        if ($count === 0) {
            $this->line('      Nenhum produto deletado com vendas recentes');

            return ['count' => 0, 'ids' => []];
        }

        $this->info("      {$count} produtos deletados com vendas recentes ou vinculados em layers");

        Log::info('Produtos deletados com vendas/layers identificados', [
            'tenant_id' => $tenantId,
            'count' => $count,
            'days' => $days,
        ]);

        return [
            'count' => $count,
            'ids' => $deletedWithSales->pluck('id')->map(fn (mixed $id): string => (string) $id)->all(),
        ];
    }
}
