<?php

/**
 * Comando para verificação e limpeza de vendas/produtos órfãos.
 *
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Console\Commands\Sync;

use App\Console\Commands\Sync\Concerns\IntegrationConfigTrait;
use App\Jobs\Sync\Cleanup\CleanupNotifyCompletedJob;
use App\Jobs\Sync\Cleanup\CleanupNotifyStartedJob;
use App\Jobs\Sync\Cleanup\CleanupOldSalesJob;
use App\Jobs\Sync\Cleanup\CleanupOrphanSalesJob;
use App\Jobs\Sync\Cleanup\DeactivateInactiveProductsJob;
use App\Jobs\Sync\Cleanup\RestoreSoldProductsJob;
use App\Models\Client;
use App\Models\User;
use App\Notifications\CleanupCompletedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupCommand extends Command
{
    use \App\Concerns\BelongsToConnection;
    use IntegrationConfigTrait;

    protected $signature = 'sync:cleanup 
                            {--client= : ID do cliente específico}
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
        $clients = $this->getClients();

        if ($clients->isEmpty()) {
            $this->warn('⚠️  Nenhum cliente encontrado.');

            return self::SUCCESS;
        }

        $runAll = $this->option('all');
        $preview = $this->option('preview');
        $days = (int) $this->option('days');

        if ($preview) {
            $this->info('👁️  MODO PREVIEW - Nenhuma ação será executada');
            $this->newLine();
        }

        $results = [];
        foreach ($clients as $client) {
            $summary = $this->processClient($client, $runAll, $preview, $days);
            if ($summary !== null) {
                $results[] = $summary;
            }
        }

        $this->newLine();
        $this->info('✅ Verificação concluída.');

        if ($results !== []) {
            $this->sendCleanupCompletedNotification($preview, $results, $clients->count());
        }

        return self::SUCCESS;
    }

    /**
     * Envia notificação (database + broadcast) com os resultados do cleanup.
     */
    protected function sendCleanupCompletedNotification(bool $preview, array $results, int $totalClients): void
    {
        try {
            // Notifica todos os usuários; para restringir (ex.: apenas admins), use User::role('admin')->get()
            $users = User::all();
            if ($users->isEmpty()) {
                return;
            }
            $notification = new CleanupCompletedNotification($preview, $results, $totalClients);
            foreach ($users as $user) {
                $user->notify($notification);
            }
            Log::info('Notificação de conclusão do cleanup enviada', [
                'users_count' => $users->count(),
                'preview' => $preview,
                'results_count' => count($results),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar notificação de conclusão do cleanup', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Processa um cliente específico.
     *
     * @return array<string, mixed>|null Resumo do processamento ou null em caso de falha de conexão
     */
    protected function processClient(Client $client, bool $runAll, bool $preview, int $days): ?array
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info("🏢 {$client->name}");
        $this->info('═══════════════════════════════════════════════════════');

        $this->configureTenantContext($client);

        $connection = $this->getClientConnection();

        if (! $connection) {
            $this->error('   ❌ Falha na conexão');

            return null;
        }

        $summary = [
            'client_name' => $client->name,
            'orphan_sales' => 0,
            'old_sales' => 0,
            'inactive_products' => 0,
            'restore_sold' => 0,
            'jobs_dispatched' => 0,
        ];

        $jobs = collect();

        // 1. Vendas órfãs (sem produto correspondente)
        if ($runAll || $this->option('orphan-sales')) {
            $result = $this->checkOrphanSales($client, $connection, $preview);
            $summary['orphan_sales'] = $result['count'];
            if (! $preview && $result['count'] > 0) {
                $jobs->push(new CleanupOrphanSalesJob($client, $result['ids']));
            }
        }

        // 2. Vendas antigas (anteriores ao período da integração)
        if ($runAll || $this->option('old-sales')) {
            $result = $this->checkOldSales($client, $connection, $preview);
            $summary['old_sales'] = $result['count'];
            if (! $preview && $result['count'] > 0) {
                $jobs->push(new CleanupOldSalesJob($client, $result['ids']));
            }
        }

        // 3. Produtos inativos (sem vendas no período)
        if ($runAll || $this->option('inactive-products')) {
            $result = $this->checkInactiveProducts($client, $connection, $days, $preview);
            $summary['inactive_products'] = $result['count'];
            if (! $preview && $result['count'] > 0) {
                $jobs->push(new DeactivateInactiveProductsJob($client, $result['ids']));
            }
        }

        // 4. Restaurar produtos deletados que tiveram vendas
        if ($runAll || $this->option('restore-sold')) {
            $result = $this->checkDeletedProductsWithSales($client, $connection, $days, $preview);
            $summary['restore_sold'] = $result['count'];
            if (! $preview && $result['count'] > 0) {
                $jobs->push(new RestoreSoldProductsJob($client, $result['ids']));
            }
        }

        // Despachar jobs (notificação no início e no fim da chain)
        if ($jobs->isNotEmpty()) {
            $summary['jobs_dispatched'] = $jobs->count();
            $chain = array_merge(
                [new CleanupNotifyStartedJob($client, $summary, $preview)],
                $jobs->toArray(),
                [new CleanupNotifyCompletedJob($client, $summary, $preview)]
            );
            Bus::chain($chain)->dispatch();
            $this->info("   📋 {$jobs->count()} jobs despachados (notificação no início e no fim)");
        }

        return $summary;
    }

    /**
     * Obtém o período configurado na integração do cliente
     */
    protected function getIntegrationPeriod(Client $client): int
    {
        $integration = $client->client_integration;

        if (! $integration) {
            return 365; // Padrão: 1 ano
        }

        $config = $this->normalizeArray($integration->config ?? []);
        $periodo = data_get($config, 'periodo', 365);

        // Se for uma data (YYYY-MM-DD), calcula dias até hoje
        if (is_string($periodo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodo)) {
            try {
                $dataInicio = \Carbon\Carbon::parse($periodo);

                return (int) $dataInicio->diffInDays(now());
            } catch (\Exception $e) {
                return 365;
            }
        }

        return (int) $periodo;
    }

    /**
     * Verifica vendas antigas (anteriores ao período da integração)
     */
    protected function checkOldSales(Client $client, string $connection, bool $preview): array
    {
        $periodDays = $this->getIntegrationPeriod($client);
        $cutoffDate = now()->subDays($periodDays)->format('Y-m-d');

        $this->newLine();
        $this->info("   🔍 Verificando vendas anteriores a {$cutoffDate} (período: {$periodDays} dias)...");

        // Vendas com data anterior ao período limite
        $oldSales = DB::connection($connection)
            ->table('sales')
            ->where('client_id', $client->id)
            ->where('sale_date', '<', $cutoffDate)
            ->select('id', 'ean', 'sale_date', 'total_sale_value')
            ->get();

        $count = $oldSales->count();

        if ($count === 0) {
            $this->line('      ✓ Nenhuma venda antiga encontrada');

            return ['count' => 0, 'ids' => []];
        }

        // Calcular valor total das vendas antigas
        $totalValue = $oldSales->sum('total_sale_value');
        $formattedValue = number_format($totalValue, 2, ',', '.');

        $this->warn("      ⚠️  {$count} vendas anteriores ao período (R$ {$formattedValue})");

        if ($preview) {
            // Agrupar por mês para melhor visualização
            $byMonth = $oldSales->groupBy(function ($sale) {
                return \Carbon\Carbon::parse($sale->sale_date)->format('Y-m');
            })->map->count();

            $this->line('      📅 Distribuição por mês:');
            foreach ($byMonth->take(6) as $month => $qty) {
                $this->line("         - {$month}: {$qty} vendas");
            }

            if ($byMonth->count() > 6) {
                $remaining = $byMonth->count() - 6;
                $this->line("         ... e mais {$remaining} meses");
            }
        }

        Log::info('Vendas antigas identificadas', [
            'client_id' => $client->id,
            'count' => $count,
            'cutoff_date' => $cutoffDate,
            'period_days' => $periodDays,
            'total_value' => $totalValue,
        ]);

        return [
            'count' => $count,
            'ids' => $oldSales->pluck('id')->toArray(),
        ];
    }

    /**
     * Verifica vendas órfãs (sem produto correspondente)
     */
    protected function checkOrphanSales(Client $client, string $connection, bool $preview): array
    {
        $this->newLine();
        $this->info('   🔍 Verificando vendas órfãs...');

        // Vendas onde product_id não existe na tabela products
        $orphanSales = DB::connection($connection)
            ->table('sales')
            ->whereNotNull('product_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('products')
                    ->whereColumn('products.id', 'sales.product_id');
            })
            ->select('id', 'product_id', 'ean', 'sale_date')
            ->get();

        $count = $orphanSales->count();

        if ($count === 0) {
            $this->line('      ✓ Nenhuma venda órfã encontrada');

            return ['count' => 0, 'ids' => []];
        }

        $this->warn("      ⚠️  {$count} vendas sem produto correspondente");

        if ($preview && $count <= 10) {
            foreach ($orphanSales as $sale) {
                $this->line("         - Venda {$sale->id}: EAN {$sale->ean} ({$sale->sale_date})");
            }
        } elseif ($preview) {
            $sample = $orphanSales->take(5);
            foreach ($sample as $sale) {
                $this->line("         - Venda {$sale->id}: EAN {$sale->ean} ({$sale->sale_date})");
            }
            $remaining = $count - 5;
            $this->line("         ... e mais {$remaining} vendas");
        }

        Log::info('Vendas órfãs identificadas', [
            'client_id' => $client->id,
            'count' => $count,
        ]);

        return [
            'count' => $count,
            'ids' => $orphanSales->pluck('id')->toArray(),
        ];
    }

    /**
     * Verifica produtos inativos (sem vendas no período)
     */
    protected function checkInactiveProducts(Client $client, string $connection, int $days, bool $preview): array
    {
        $this->newLine();
        $this->info("   🔍 Verificando produtos sem vendas (últimos {$days} dias)...");

        $cutoffDate = now()->subDays($days)->format('Y-m-d');

        // Produtos ativos que não têm vendas no período
        $inactiveProducts = DB::connection($connection)
            ->table('products')
            ->whereNull('deleted_at')
            ->where('client_id', $client->id)
            ->whereNotExists(function ($query) use ($cutoffDate) {
                $query->select(DB::raw(1))
                    ->from('sales')
                    ->whereColumn('sales.product_id', 'products.id')
                    ->where('sales.sale_date', '>=', $cutoffDate);
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('layers')
                    ->whereColumn('layers.product_id', 'products.id')
                    ->whereNull('layers.deleted_at');
            })
            ->select('id', 'name', 'ean')
            ->get();

        $count = $inactiveProducts->count();

        if ($count === 0) {
            $this->line('      ✓ Todos os produtos têm vendas no período');

            return ['count' => 0, 'ids' => []];
        }

        $this->warn("      ⚠️  {$count} produtos sem vendas no período");

        if ($preview && $count <= 10) {
            foreach ($inactiveProducts as $product) {
                $name = mb_substr($product->name ?? 'Sem nome', 0, 40);
                $this->line("         - {$product->ean}: {$name}");
            }
        } elseif ($preview) {
            $sample = $inactiveProducts->take(5);
            foreach ($sample as $product) {
                $name = mb_substr($product->name ?? 'Sem nome', 0, 40);
                $this->line("         - {$product->ean}: {$name}");
            }
            $remaining = $count - 5;
            $this->line("         ... e mais {$remaining} produtos");
        }

        Log::info('Produtos inativos identificados', [
            'client_id' => $client->id,
            'count' => $count,
            'days' => $days,
        ]);

        return [
            'count' => $count,
            'ids' => $inactiveProducts->pluck('id')->toArray(),
        ];
    }

    /**
     * Verifica produtos deletados que tiveram vendas recentes
     */
    protected function checkDeletedProductsWithSales(Client $client, string $connection, int $days, bool $preview): array
    {
        $this->newLine();
        $this->info('   🔍 Verificando produtos deletados com vendas recentes...');

        $cutoffDate = now()->subDays($days)->format('Y-m-d');

        // Produtos deletados que têm vendas no período
        $deletedWithSales = DB::connection($connection)
            ->table('products')
            ->whereNotNull('deleted_at')
            ->where('client_id', $client->id)
            ->whereExists(function ($query) use ($cutoffDate) {
                $query->select(DB::raw(1))
                    ->from('sales')
                    ->whereColumn('sales.product_id', 'products.id')
                    ->where('sales.sale_date', '>=', $cutoffDate);
            })
            ->select('id', 'name', 'ean', 'deleted_at')
            ->get();

        $count = $deletedWithSales->count();

        if ($count === 0) {
            $this->line('      ✓ Nenhum produto deletado com vendas recentes');

            return ['count' => 0, 'ids' => []];
        }

        $this->info("      🔄 {$count} produtos deletados com vendas (serão restaurados)");

        if ($preview && $count <= 10) {
            foreach ($deletedWithSales as $product) {
                $name = mb_substr($product->name ?? 'Sem nome', 0, 40);
                $this->line("         - {$product->ean}: {$name}");
            }
        } elseif ($preview) {
            $sample = $deletedWithSales->take(5);
            foreach ($sample as $product) {
                $name = mb_substr($product->name ?? 'Sem nome', 0, 40);
                $this->line("         - {$product->ean}: {$name}");
            }
            $remaining = $count - 5;
            $this->line("         ... e mais {$remaining} produtos");
        }

        Log::info('Produtos deletados com vendas identificados', [
            'client_id' => $client->id,
            'count' => $count,
            'days' => $days,
        ]);

        return [
            'count' => $count,
            'ids' => $deletedWithSales->pluck('id')->toArray(),
        ];
    }
}