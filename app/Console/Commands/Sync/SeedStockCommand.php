<?php

/**
 * Comando para popular a coluna current_stock com valores aleatórios (uso em testes).
 *
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Console\Commands\Sync;

use App\Console\Commands\Sync\Concerns\IntegrationConfigTrait;
use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedStockCommand extends Command
{
    use \App\Concerns\BelongsToConnection;
    use IntegrationConfigTrait;

    protected $signature = 'seed:stock
                            {--client= : ID do cliente específico}
                            {--min=0 : Estoque mínimo aleatório}
                            {--max=1 : Estoque máximo aleatório}
                            {--chunk=1000 : Tamanho do lote de atualização}';

    protected $description = 'Popula current_stock com valores aleatórios para testes';

    public function handle(): int
    {
        $min = (int) $this->option('min');
        $max = (int) $this->option('max');
        $chunkSize = (int) $this->option('chunk');

        if ($min > $max) {
            $this->error('❌ O valor de --min não pode ser maior que --max.');

            return self::FAILURE;
        }

        $clients = $this->getClients();

        if ($clients->isEmpty()) {
            $this->warn('⚠️  Nenhum cliente encontrado.');

            return self::SUCCESS;
        }

        foreach ($clients as $client) {
            $this->seedClientStock($client, $min, $max, $chunkSize);
        }

        $this->info('✅ Estoque populado com sucesso!');

        return self::SUCCESS;
    }

    protected function seedClientStock(Client $client, int $min, int $max, int $chunkSize): void
    {
        $this->configureTenantContext($client);

        $conn = $this->getClientConnection();
        $ids = DB::connection($conn)->table('products')->pluck('id');

        if ($ids->isEmpty()) {
            $this->warn("   ⚠️  [{$client->name}] Nenhum produto encontrado.");

            return;
        }

        $this->info("   🔄 [{$client->name}] {$ids->count()} produtos — estoque entre {$min} e {$max}");

        $updatedAt = now()->format('Y-m-d H:i:s');
        $totalUpdated = 0;

        foreach ($ids->chunk($chunkSize) as $chunk) {
            $cases = '';
            $bindings = [];

            foreach ($chunk as $id) {
                $stock = random_int($min, $max);
                $cases .= 'WHEN id = ? THEN ? ';
                $bindings[] = $id;
                $bindings[] = $stock;
            }

            $bindings[] = $updatedAt;
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $bindings = array_merge($bindings, $chunk->all());

            $affected = DB::connection($conn)->update(
                "UPDATE products SET current_stock = CASE {$cases}END, updated_at = ? WHERE id IN ({$placeholders})",
                $bindings
            );

            $totalUpdated += $affected;
            $this->line("      ✓ Lote de {$affected} produtos atualizado");
        }

        $this->info("   ✅ [{$client->name}] {$totalUpdated} produtos atualizados");
    }
}
