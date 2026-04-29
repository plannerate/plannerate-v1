<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Cleanup\CleanupOldSalesJob;
use App\Jobs\Cleanup\CleanupOrphanSalesJob;
use App\Jobs\Cleanup\DeactivateInactiveProductsJob;
use App\Jobs\Cleanup\RestoreSoldProductsJob;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupCommand extends Command
{
    protected $signature = 'integrations:cleanup
                            {--tenant= : ID do tenant específico}
                            {--orphan-sales : Remove vendas sem produto correspondente}
                            {--old-sales : Remove vendas mais antigas que o período}
                            {--inactive-products : Soft delete de produtos sem venda no período}
                            {--restore-sold : Restaura produtos deletados que voltaram a vender}
                            {--days=90 : Período em dias para inatividade}
                            {--all : Executa todas as verificações}
                            {--preview : Apenas mostra o que seria feito}';

    protected $description = 'Verifica e limpa vendas/produtos órfãos por tenant';

    public function handle(): int
    {
        $runAll = (bool) $this->option('all');
        $preview = (bool) $this->option('preview');
        $days = max(1, (int) $this->option('days'));

        $query = Tenant::query()->where('status', 'active');
        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->whereKey($tenantId);
        }

        $tenants = $query->get(['id', 'name', 'database']);

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->info(sprintf('Tenant: %s (%s)', $tenant->name, $tenant->id));

            $tenantDatabase = is_string($tenant->database) ? trim($tenant->database) : '';
            if ($tenantDatabase === '') {
                $this->warn(' - ignorado: tenant sem database configurado');

                continue;
            }

            $summary = $tenant->execute(function () use ($tenant, $runAll, $days): array {
                $connection = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));

                $summary = [
                    'orphan_sales' => ['count' => 0, 'ids' => []],
                    'old_sales' => ['count' => 0, 'ids' => []],
                    'inactive_products' => ['count' => 0, 'ids' => []],
                    'restore_sold' => ['count' => 0, 'ids' => []],
                ];

                if ($runAll || $this->option('orphan-sales')) {
                    $rows = DB::connection($connection)->table('sales')
                        ->where('tenant_id', (string) $tenant->id)
                        ->whereNotNull('product_id')
                        ->whereNotExists(function ($query): void {
                            $query->selectRaw('1')
                                ->from('products')
                                ->whereColumn('products.id', 'sales.product_id');
                        })
                        ->pluck('id')
                        ->all();
                    $summary['orphan_sales'] = ['count' => count($rows), 'ids' => $rows];
                }

                if ($runAll || $this->option('old-sales')) {
                    $cutoffDate = now()->subDays($days)->toDateString();
                    $rows = DB::connection($connection)->table('sales')
                        ->where('tenant_id', (string) $tenant->id)
                        ->where('sale_date', '<', $cutoffDate)
                        ->pluck('id')
                        ->all();
                    $summary['old_sales'] = ['count' => count($rows), 'ids' => $rows];
                }

                if ($runAll || $this->option('inactive-products')) {
                    $cutoffDate = now()->subDays($days)->toDateString();
                    $rows = DB::connection($connection)->table('products')
                        ->where('tenant_id', (string) $tenant->id)
                        ->whereNull('deleted_at')
                        ->whereNotExists(function ($query) use ($cutoffDate): void {
                            $query->selectRaw('1')
                                ->from('sales')
                                ->whereColumn('sales.product_id', 'products.id')
                                ->where('sales.sale_date', '>=', $cutoffDate);
                        })
                        ->whereNotExists(function ($query): void {
                            $query->selectRaw('1')
                                ->from('layers')
                                ->whereColumn('layers.product_id', 'products.id')
                                ->whereNull('layers.deleted_at');
                        })
                        ->pluck('id')
                        ->all();
                    $summary['inactive_products'] = ['count' => count($rows), 'ids' => $rows];
                }

                if ($runAll || $this->option('restore-sold')) {
                    $cutoffDate = now()->subDays($days)->toDateString();
                    $rows = DB::connection($connection)->table('products')
                        ->where('tenant_id', (string) $tenant->id)
                        ->whereNotNull('deleted_at')
                        ->whereExists(function ($query) use ($cutoffDate): void {
                            $query->selectRaw('1')
                                ->from('sales')
                                ->whereColumn('sales.product_id', 'products.id')
                                ->where('sales.sale_date', '>=', $cutoffDate);
                        })
                        ->pluck('id')
                        ->all();
                    $summary['restore_sold'] = ['count' => count($rows), 'ids' => $rows];
                }

                return $summary;
            });

            $this->line(sprintf(' - orphan_sales: %d', $summary['orphan_sales']['count']));
            $this->line(sprintf(' - old_sales: %d', $summary['old_sales']['count']));
            $this->line(sprintf(' - inactive_products: %d', $summary['inactive_products']['count']));
            $this->line(sprintf(' - restore_sold: %d', $summary['restore_sold']['count']));

            if ($preview) {
                continue;
            }

            if ($summary['orphan_sales']['count'] > 0) {
                CleanupOrphanSalesJob::dispatch((string) $tenant->id, $summary['orphan_sales']['ids']);
            }
            if ($summary['old_sales']['count'] > 0) {
                CleanupOldSalesJob::dispatch((string) $tenant->id, $summary['old_sales']['ids']);
            }
            if ($summary['inactive_products']['count'] > 0) {
                DeactivateInactiveProductsJob::dispatch((string) $tenant->id, $summary['inactive_products']['ids']);
            }
            if ($summary['restore_sold']['count'] > 0) {
                RestoreSoldProductsJob::dispatch((string) $tenant->id, $summary['restore_sold']['ids']);
            }
        }

        return self::SUCCESS;
    }
}
