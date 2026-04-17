<?php

/**
 * Comando para vincular vendas aos produtos usando codigo_erp.
 *
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Console\Commands\Sync;

use App\Console\Commands\Sync\Concerns\IntegrationConfigTrait;
use App\Models\Client;
use App\Models\User;
use App\Notifications\LinkSalesCompletedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LinkSalesProductsCommand extends Command
{
    use \App\Concerns\BelongsToConnection;
    use IntegrationConfigTrait;

    protected $signature = 'sync:link-sales 
                            {--client= : ID do cliente específico}
                            {--preview : Apenas mostra o que seria feito}';

    protected $description = 'Vincula vendas aos produtos usando codigo_erp';

    public function handle(): int
    {
        $clients = $this->getClients();

        if ($clients->isEmpty()) {
            $this->warn('⚠️  Nenhum cliente encontrado.');

            return self::SUCCESS;
        }

        $preview = $this->option('preview');

        if ($preview) {
            $this->info('👁️  MODO PREVIEW - Nenhuma ação será executada');
            $this->newLine();
        }

        $results = [];
        foreach ($clients as $client) {
            $summary = $this->processClient($client, $preview);
            if ($summary !== null) {
                $results[] = $summary;
            }
        }

        $this->newLine();
        $this->info('✅ Vinculação concluída.');

        if ($results !== []) {
            $this->sendLinkSalesCompletedNotification($preview, $results, $clients->count());
        }

        return self::SUCCESS;
    }

    /**
     * Envia notificação (database + broadcast) de conclusão do sync:link-sales.
     *
     * @param  array<int, array{client_name: string, linked: int, remaining: int}>  $results
     */
    protected function sendLinkSalesCompletedNotification(bool $preview, array $results, int $totalClients): void
    {
        try {
            $users = User::all();
            if ($users->isEmpty()) {
                return;
            }
            $notification = new LinkSalesCompletedNotification($preview, $results, $totalClients);
            foreach ($users as $user) {
                $user->notify($notification);
            }
            Log::info('Notificação de conclusão do sync:link-sales enviada', [
                'users_count' => $users->count(),
                'results_count' => count($results),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar notificação de conclusão do sync:link-sales', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Processa um cliente específico.
     *
     * @return array{client_name: string, linked: int, remaining: int}|null Resumo ou null em caso de falha de conexão
     */
    protected function processClient(Client $client, bool $preview): ?array
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

        // 1. Contar vendas sem product_id
        $salesWithoutProduct = DB::connection($connection)
            ->table('sales')
            ->whereNull('product_id')
            ->whereNotNull('codigo_erp')
            ->count();

        $this->info("   📊 Vendas sem product_id: {$salesWithoutProduct}");

        if ($salesWithoutProduct === 0) {
            $this->line('   ✓ Todas as vendas já estão vinculadas');

            return [
                'client_name' => $client->name,
                'linked' => 0,
                'remaining' => 0,
            ];
        }

        // 2. Contar produtos disponíveis (inclui soft-deleted)
        $productsCount = DB::connection($connection)
            ->table('products')
            ->whereNotNull('codigo_erp')
            ->count();

        $deletedProductsCount = DB::connection($connection)
            ->table('products')
            ->whereNotNull('codigo_erp')
            ->whereNotNull('deleted_at')
            ->count();

        $this->info("   📦 Produtos com codigo_erp: {$productsCount} ({$deletedProductsCount} soft-deleted)");

        if ($preview) {
            $this->showPreview($connection, $salesWithoutProduct);

            return [
                'client_name' => $client->name,
                'linked' => 0,
                'remaining' => $salesWithoutProduct,
            ];
        }

        // 3. Executar vinculação em batch usando UPDATE com JOIN
        $this->info('   🔄 Vinculando vendas aos produtos...');

        $updated = $this->linkSalesToProducts($connection);

        $this->info("   ✅ {$updated} vendas vinculadas");

        // 4. Verificar vendas que não puderam ser vinculadas
        $remaining = DB::connection($connection)
            ->table('sales')
            ->whereNull('product_id')
            ->whereNotNull('codigo_erp')
            ->count();

        if ($remaining > 0) {
            $this->warn("   ⚠️  {$remaining} vendas sem produto correspondente (codigo_erp não encontrado)");

            // Listar alguns codigo_erp não encontrados
            $orphanCodes = DB::connection($connection)
                ->table('sales')
                ->whereNull('product_id')
                ->whereNotNull('codigo_erp')
                ->distinct()
                ->limit(10)
                ->pluck('codigo_erp');

            $this->line('   Exemplos de codigo_erp sem produto:');
            foreach ($orphanCodes as $code) {
                $this->line("      - {$code}");
            }
        }

        Log::info('Vendas vinculadas aos produtos', [
            'client_id' => $client->id,
            'updated' => $updated,
            'remaining' => $remaining,
        ]);

        return [
            'client_name' => $client->name,
            'linked' => $updated,
            'remaining' => $remaining,
        ];
    }

    /**
     * Mostra preview do que seria vinculado
     */
    protected function showPreview(string $connection, int $total): void
    {
        // Pegar amostra de vendas que seriam vinculadas
        $sampleSales = DB::connection($connection)
            ->table('sales as s')
            ->join('products as p', 's.codigo_erp', '=', 'p.codigo_erp')
            ->whereNull('s.product_id')
            ->select('s.codigo_erp', 'p.id as product_id', 'p.ean', 'p.name')
            ->limit(10)
            ->get();

        if ($sampleSales->isEmpty()) {
            $this->warn('   ⚠️  Nenhuma venda pode ser vinculada (produtos não encontrados)');

            return;
        }

        $this->newLine();
        $this->info('   📋 Amostra de vinculações que seriam feitas:');

        foreach ($sampleSales as $sale) {
            $name = mb_substr($sale->name ?? 'Sem nome', 0, 35);
            $this->line("      {$sale->codigo_erp} → {$sale->ean} | {$name}");
        }

        // Contar quantas poderiam ser vinculadas
        $linkable = DB::connection($connection)
            ->table('sales as s')
            ->join('products as p', 's.codigo_erp', '=', 'p.codigo_erp')
            ->whereNull('s.product_id')
            ->count();

        $this->newLine();
        $this->info("   📊 Total que seriam vinculadas: {$linkable} de {$total}");

        $notLinkable = $total - $linkable;
        if ($notLinkable > 0) {
            $this->warn("   ⚠️  {$notLinkable} vendas não têm produto correspondente");
        }
    }

    /**
     * Vincula vendas aos produtos usando UPDATE com JOIN
     */
    protected function linkSalesToProducts(string $connection): int
    {
        // PostgreSQL: UPDATE com FROM
        $sql = '
            UPDATE sales 
            SET 
                product_id = p.id,
                ean = p.ean,
                updated_at = NOW()
            FROM products p
            WHERE sales.codigo_erp = p.codigo_erp
              AND sales.product_id IS NULL
              AND sales.codigo_erp IS NOT NULL
        ';

        return DB::connection($connection)->affectingStatement($sql);
    }
}
