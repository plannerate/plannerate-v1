<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Cleanup\CleanupOldSalesJob;
use App\Jobs\Cleanup\CleanupOrphanSalesJob;
use App\Jobs\Cleanup\DeactivateInactiveProductsJob;
use App\Jobs\Cleanup\RestoreSoldProductsJob;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupCommand extends Command
{
    private const INACTIVE_DAYS = 120;

    protected $signature = 'sync:cleanup {--tenant= : ID do tenant específico}';

    protected $description = 'Executa limpeza automática por TenantIntegration ativa';

    public function handle(): int
    {
        $integrations = $this->getActiveIntegrations();

        if ($integrations->isEmpty()) {
            $this->warn('Nenhuma TenantIntegration ativa encontrada para cleanup.');

            return self::SUCCESS;
        }

        $results = [];

        foreach ($integrations as $integration) {
            $summary = $this->processIntegration($integration);

            if ($summary !== null) {
                $results[] = $summary;
            }
        }

        $this->newLine();
        $this->info('Verificação concluída.');

        if ($results !== []) {
            $this->sendCleanupCompletedNotification($results, $integrations->count());
        }

        return self::SUCCESS;
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

    /**
     * @param  array<int, array{tenant_name: string, orphan_sales: int, old_sales: int, inactive_products: int, restore_sold: int, jobs_dispatched: int}>  $results
     */
    protected function sendCleanupCompletedNotification(array $results, int $totalIntegrations): void
    {
        try {
            $users = User::all();

            if ($users->isEmpty()) {
                return;
            }

            $totalJobs = array_sum(array_column($results, 'jobs_dispatched'));
            $notification = new AppNotification(
                title: 'Limpeza concluída',
                message: sprintf('%d integração(ões) verificada(s), %d job(s) despachado(s).', $totalIntegrations, $totalJobs),
                type: 'success',
            );

            foreach ($users as $user) {
                $user->notify($notification);
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar notificação de conclusão do cleanup', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{tenant_name: string, orphan_sales: int, old_sales: int, inactive_products: int, restore_sold: int, jobs_dispatched: int}|null
     */
    protected function processIntegration(TenantIntegration $integration): ?array
    {
        $tenant = $integration->tenant;

        if (! $tenant instanceof Tenant) {
            $this->error(sprintf('Integração %s sem tenant relacionado; cleanup ignorado.', $integration->id));

            return null;
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

            return null;
        }

        $process = fn (): array => $this->processIntegrationOnConnection(
            tenant: $tenant,
            integration: $integration,
            connection: $connection,
            shouldSwitchTenantContext: $shouldSwitchTenantContext,
        );

        if ($shouldSwitchTenantContext) {
            return $tenant->execute($process);
        }

        return $process();
    }

    /**
     * @return array{tenant_name: string, orphan_sales: int, old_sales: int, inactive_products: int, restore_sold: int, jobs_dispatched: int}
     */
    protected function processIntegrationOnConnection(
        Tenant $tenant,
        TenantIntegration $integration,
        string $connection,
        bool $shouldSwitchTenantContext,
    ): array {
        $tenantId = (string) $tenant->id;

        $summary = [
            'tenant_name' => $tenant->name,
            'orphan_sales' => 0,
            'old_sales' => 0,
            'inactive_products' => 0,
            'restore_sold' => 0,
            'jobs_dispatched' => 0,
        ];

        $jobs = collect();

        $orphanSales = $this->checkOrphanSales($tenantId, $connection);
        $summary['orphan_sales'] = $orphanSales['count'];

        if ($orphanSales['ids'] !== []) {
            $jobs->push(new CleanupOrphanSalesJob($tenantId, $orphanSales['ids'], $connection, $shouldSwitchTenantContext));
        }

        $retentionsByPath = $this->getOldSalesRetentionsByPath($integration);
        $oldSalesIds = [];

        if ($retentionsByPath === []) {
            $this->line('      Nenhum path com initial_days > 0 encontrado para limpeza de vendas antigas');
        }

        foreach ($retentionsByPath as $pathKey => $initialDays) {
            $result = $this->checkOldSales($tenantId, $connection, $initialDays, $pathKey);
            $oldSalesIds = array_values(array_unique([...$oldSalesIds, ...$result['ids']]));
        }

        $summary['old_sales'] = count($oldSalesIds);

        if ($oldSalesIds !== []) {
            $jobs->push(new CleanupOldSalesJob($tenantId, $oldSalesIds, $connection, $shouldSwitchTenantContext));
        }

        $inactiveProducts = $this->checkInactiveProducts($tenantId, $connection, self::INACTIVE_DAYS);
        $summary['inactive_products'] = $inactiveProducts['count'];

        if ($inactiveProducts['ids'] !== []) {
            $jobs->push(new DeactivateInactiveProductsJob($tenantId, $inactiveProducts['ids'], $connection, $shouldSwitchTenantContext));
        }

        $restoreSold = $this->checkDeletedProductsWithSales($tenantId, $connection, self::INACTIVE_DAYS);
        $summary['restore_sold'] = $restoreSold['count'];

        if ($restoreSold['ids'] !== []) {
            $jobs->push(new RestoreSoldProductsJob($tenantId, $restoreSold['ids'], $connection, $shouldSwitchTenantContext));
        }

        if ($jobs->isNotEmpty()) {
            $summary['jobs_dispatched'] = $jobs->count();
            Bus::chain($jobs->all())->dispatch();
            $this->info("   {$jobs->count()} jobs despachados");
        }

        return $summary;
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
     * @return array{count: int, ids: array<int, string>}
     */
    protected function checkOldSales(string $tenantId, string $connection, int $periodDays, string $pathKey): array
    {
        $cutoffDate = now()->subDays($periodDays)->toDateString();

        $oldSales = DB::connection($connection)
            ->table('sales')
            ->where('tenant_id', $tenantId)
            ->where('sale_date', '<', $cutoffDate)
            ->select('id', 'ean', 'sale_date', 'total_sale_value')
            ->get();

        $count = $oldSales->count();

        if ($count === 0) {
            $this->line("      Path {$pathKey} (initial_days={$periodDays}): nenhuma venda antiga encontrada");

            return ['count' => 0, 'ids' => []];
        }

        $this->warn("      Path {$pathKey} (initial_days={$periodDays}): {$count} venda(s) anteriores ao período");

        Log::info('Vendas antigas identificadas', [
            'tenant_id' => $tenantId,
            'count' => $count,
            'cutoff_date' => $cutoffDate,
            'period_days' => $periodDays,
            'path_key' => $pathKey,
        ]);

        return [
            'count' => $count,
            'ids' => $oldSales->pluck('id')->map(fn (mixed $id): string => (string) $id)->all(),
        ];
    }

    /**
     * @return array{count: int, ids: array<int, string>}
     */
    protected function checkOrphanSales(string $tenantId, string $connection): array
    {
        $totalProducts = DB::connection($connection)
            ->table('products')
            ->where('tenant_id', $tenantId)
            ->count();

        if ($totalProducts === 0) {
            $this->warn('      Nenhum produto encontrado; deleção de vendas ignorada por segurança.');

            return ['count' => 0, 'ids' => []];
        }

        $orphanSales = DB::connection($connection)
            ->table('sales')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('product_id')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('products')
                    ->whereColumn('products.id', 'sales.product_id')
                    ->whereColumn('products.tenant_id', 'sales.tenant_id');
            })
            ->select('id', 'product_id', 'ean', 'sale_date')
            ->get();

        $count = $orphanSales->count();

        if ($count === 0) {
            $this->line('      Nenhuma venda órfã encontrada');

            return ['count' => 0, 'ids' => []];
        }

        $this->warn("      {$count} vendas sem produto correspondente");

        Log::info('Vendas órfãs identificadas', [
            'tenant_id' => $tenantId,
            'count' => $count,
        ]);

        return [
            'count' => $count,
            'ids' => $orphanSales->pluck('id')->map(fn (mixed $id): string => (string) $id)->all(),
        ];
    }

    /**
     * @return array{count: int, ids: array<int, string>}
     */
    protected function checkInactiveProducts(string $tenantId, string $connection, int $days): array
    {
        $totalSales = DB::connection($connection)
            ->table('sales')
            ->where('tenant_id', $tenantId)
            ->count();

        if ($totalSales === 0) {
            $this->warn('      Nenhuma venda encontrada; deleção de produtos ignorada por segurança.');

            return ['count' => 0, 'ids' => []];
        }

        $cutoffDate = now()->subDays($days)->toDateString();

        $inactiveProducts = DB::connection($connection)
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
            ->select('id', 'name', 'ean')
            ->get();

        $count = $inactiveProducts->count();

        if ($count === 0) {
            $this->line('      Todos os produtos têm vendas no período');

            return ['count' => 0, 'ids' => []];
        }

        $this->warn("      {$count} produtos sem vendas no período");

        Log::info('Produtos inativos identificados', [
            'tenant_id' => $tenantId,
            'count' => $count,
            'days' => $days,
        ]);

        return [
            'count' => $count,
            'ids' => $inactiveProducts->pluck('id')->map(fn (mixed $id): string => (string) $id)->all(),
        ];
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
