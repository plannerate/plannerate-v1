<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Cleanup\CleanupOldSalesJob;
use App\Jobs\Cleanup\CleanupOrphanSalesJob;
use App\Jobs\Cleanup\DeactivateInactiveProductsJob;
use App\Jobs\Cleanup\RestoreSoldProductsJob;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupCommand extends Command
{
    protected $signature = 'sync:cleanup
                            {--tenant= : ID do tenant específico}
                            {--orphan-sales : Verifica e deleta vendas sem produtos}
                            {--old-sales : Deleta vendas anteriores ao período da integração}
                            {--inactive-products : Verifica produtos sem vendas (soft delete)}
                            {--restore-sold : Restaura produtos deletados que tiveram vendas}
                            {--days=90 : Período em dias para considerar inatividade}
                            {--all : Executa todas as verificações}
                            {--preview : Apenas mostra o que seria feito}';

    protected $description = 'Verifica e limpa vendas/produtos órfãos';

    public function handle(): int
    {
        $tenants = $this->getTenants();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        $runAll = (bool) $this->option('all');
        $preview = (bool) $this->option('preview');
        $days = (int) $this->option('days');

        if (! $runAll && ! $this->hasSelectedCleanup()) {
            $this->warn('Informe ao menos uma verificação ou use --all.');

            return self::SUCCESS;
        }

        $results = [];
        foreach ($tenants as $tenant) {
            $summary = $this->processTenant($tenant, $runAll, $preview, $days);
            if ($summary !== null) {
                $results[] = $summary;
            }
        }

        $this->newLine();
        $this->info('Verificação concluída.');

        if ($results !== []) {
            $this->sendCleanupCompletedNotification($preview, $results, $tenants->count());
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>
     */
    protected function getTenants(): Collection
    {
        $query = Tenant::query()
            ->where('status', 'active')
            ->whereHas('integration', fn ($q) => $q->where('is_active', true));

        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->whereKey($tenantId);
        }

        return $query->get(['id', 'name', 'database']);
    }

    protected function hasSelectedCleanup(): bool
    {
        return (bool) $this->option('orphan-sales')
            || (bool) $this->option('old-sales')
            || (bool) $this->option('inactive-products')
            || (bool) $this->option('restore-sold');
    }

    /**
     * @param  array<int, array{tenant_name: string, orphan_sales: int, old_sales: int, inactive_products: int, restore_sold: int, jobs_dispatched: int}>  $results
     */
    protected function sendCleanupCompletedNotification(bool $preview, array $results, int $totalTenants): void
    {
        try {
            $users = User::all();
            if ($users->isEmpty()) {
                return;
            }

            $totalJobs = array_sum(array_column($results, 'jobs_dispatched'));
            $notification = new AppNotification(
                title: $preview ? 'Preview da limpeza concluído' : 'Limpeza concluída',
                message: sprintf('%d tenant(s) verificado(s), %d job(s) despachado(s).', $totalTenants, $totalJobs),
                type: $preview ? 'info' : 'success',
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
    protected function processTenant(Tenant $tenant, bool $runAll, bool $preview, int $days): ?array
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info("🏢 {$tenant->name}");
        $this->info('═══════════════════════════════════════════════════════');

        $configuredTenantConnection = config('multitenancy.tenant_database_connection_name');
        $connection = (string) ($configuredTenantConnection ?: config('database.default'));
        $shouldSwitchTenantContext = is_string($configuredTenantConnection) && $configuredTenantConnection !== '';

        $tenantDatabase = is_string($tenant->getAttribute('database')) ? trim((string) $tenant->getAttribute('database')) : '';
        if ($shouldSwitchTenantContext && $tenantDatabase === '') {
            $this->error(sprintf('Tenant %s sem database configurado; cleanup ignorado.', $tenant->id));

            return null;
        }

        $process = fn (): array => $this->processTenantOnConnection(
            $tenant,
            $connection,
            $shouldSwitchTenantContext,
            $runAll,
            $preview,
            $days,
        );

        if ($shouldSwitchTenantContext) {
            return $tenant->execute($process);
        }

        return $process();
    }

    /**
     * @return array{tenant_name: string, orphan_sales: int, old_sales: int, inactive_products: int, restore_sold: int, jobs_dispatched: int}
     */
    protected function processTenantOnConnection(
        Tenant $tenant,
        string $connection,
        bool $shouldSwitchTenantContext,
        bool $runAll,
        bool $preview,
        int $days,
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

        if ($runAll || $this->option('orphan-sales')) {
            $result = $this->checkOrphanSales($tenantId, $connection, $preview);
            $summary['orphan_sales'] = $result['count'];
            if (! $preview && $result['ids'] !== []) {
                $jobs->push(new CleanupOrphanSalesJob($tenantId, $result['ids'], $connection, $shouldSwitchTenantContext));
            }
        }

        if ($runAll || $this->option('old-sales')) {
            $result = $this->checkOldSales($tenant, $connection, $preview);
            $summary['old_sales'] = $result['count'];
            if (! $preview && $result['ids'] !== []) {
                $jobs->push(new CleanupOldSalesJob($tenantId, $result['ids'], $connection, $shouldSwitchTenantContext));
            }
        }

        if ($runAll || $this->option('inactive-products')) {
            $result = $this->checkInactiveProducts($tenantId, $connection, $days, $preview);
            $summary['inactive_products'] = $result['count'];
            if (! $preview && $result['ids'] !== []) {
                $jobs->push(new DeactivateInactiveProductsJob($tenantId, $result['ids'], $connection, $shouldSwitchTenantContext));
            }
        }

        if ($runAll || $this->option('restore-sold')) {
            $result = $this->checkDeletedProductsWithSales($tenantId, $connection, $days, $preview);
            $summary['restore_sold'] = $result['count'];
            if (! $preview && $result['ids'] !== []) {
                $jobs->push(new RestoreSoldProductsJob($tenantId, $result['ids'], $connection, $shouldSwitchTenantContext));
            }
        }

        if ($jobs->isNotEmpty()) {
            $summary['jobs_dispatched'] = $jobs->count();
            Bus::chain($jobs->all())->dispatch();
            $this->info("   {$jobs->count()} jobs despachados");
        }

        return $summary;
    }

    protected function getIntegrationPeriod(Tenant $tenant): int
    {
        $integration = $tenant->integration()->where('is_active', true)->first(['id', 'tenant_id', 'config']);

        if (! $integration) {
            return 365;
        }

        $config = $integration->config ?? [];
        $period = data_get($config, 'processing.sales_retention_days')
            ?? data_get($config, 'sales_retention_days')
            ?? data_get($config, 'periodo', 365);

        if (is_string($period) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
            try {
                return (int) Carbon::parse($period)->diffInDays(now());
            } catch (\Throwable) {
                return 365;
            }
        }

        return max(1, (int) $period);
    }

    /**
     * @return array{count: int, ids: array<int, string>}
     */
    protected function checkOldSales(Tenant $tenant, string $connection, bool $preview): array
    {
        $tenantId = (string) $tenant->id;
        $periodDays = $this->getIntegrationPeriod($tenant);
        $cutoffDate = now()->subDays($periodDays)->toDateString();

        $oldSales = DB::connection($connection)
            ->table('sales')
            ->where('tenant_id', $tenantId)
            ->where('sale_date', '<', $cutoffDate)
            ->select('id', 'ean', 'sale_date', 'total_sale_value')
            ->get();

        $count = $oldSales->count();
        if ($count === 0) {
            $this->line('      Nenhuma venda antiga encontrada');

            return ['count' => 0, 'ids' => []];
        }

        $this->warn("      {$count} vendas anteriores ao período");

        if ($preview) {
            $byMonth = $oldSales
                ->groupBy(fn (object $sale): string => Carbon::parse($sale->sale_date)->format('Y-m'))
                ->map->count();

            foreach ($byMonth->take(6) as $month => $quantity) {
                $this->line("         - {$month}: {$quantity} vendas");
            }
        }

        Log::info('Vendas antigas identificadas', [
            'tenant_id' => $tenantId,
            'count' => $count,
            'cutoff_date' => $cutoffDate,
            'period_days' => $periodDays,
        ]);

        return [
            'count' => $count,
            'ids' => $oldSales->pluck('id')->map(fn (mixed $id): string => (string) $id)->all(),
        ];
    }

    /**
     * @return array{count: int, ids: array<int, string>}
     */
    protected function checkOrphanSales(string $tenantId, string $connection, bool $preview): array
    {
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

        if ($preview) {
            foreach ($orphanSales->take(5) as $sale) {
                $this->line("         - Venda {$sale->id}: EAN {$sale->ean} ({$sale->sale_date})");
            }
        }

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
    protected function checkInactiveProducts(string $tenantId, string $connection, int $days, bool $preview): array
    {
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

        if ($preview) {
            foreach ($inactiveProducts->take(5) as $product) {
                $name = mb_substr($product->name ?? 'Sem nome', 0, 40);
                $this->line("         - {$product->ean}: {$name}");
            }
        }

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
    protected function checkDeletedProductsWithSales(string $tenantId, string $connection, int $days, bool $preview): array
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

        if ($preview) {
            foreach ($deletedWithSales->take(5) as $product) {
                $name = mb_substr($product->name ?? 'Sem nome', 0, 40);
                $this->line("         - {$product->ean}: {$name}");
            }
        }

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
