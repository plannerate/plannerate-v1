<?php

namespace App\Console\Commands\Sync;

use App\Console\Commands\Sync\Concerns\IntegrationConfigTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RestoreProductsInActiveLayersCommand extends Command
{
    use \App\Concerns\BelongsToConnection;
    use IntegrationConfigTrait;

    protected $signature = 'sync:restore-products-in-active-layers
                            {--client= : ID do cliente específico}
                            {--preview : Apenas mostra o que seria restaurado}
                            {--chunk=500 : Quantidade de registros por lote}';

    protected $description = 'Restaura produtos soft-deleted que ainda estão vinculados a layers ativas';

    public function handle(): int
    {
        $clients = $this->getClients();

        if ($clients->isEmpty()) {
            $this->warn('⚠️  Nenhum cliente encontrado.');

            return self::SUCCESS;
        }

        $preview = (bool) $this->option('preview');
        $chunkSize = max(1, (int) $this->option('chunk'));

        if ($preview) {
            $this->info('👁️  MODO PREVIEW - Nenhuma ação será executada');
            $this->newLine();
        }

        $totalCandidates = 0;
        $totalRestored = 0;

        foreach ($clients as $client) {
            $this->newLine();
            $this->info('═══════════════════════════════════════════════════════');
            $this->info("🏢 {$client->name}");
            $this->info('═══════════════════════════════════════════════════════');

            $this->configureTenantContext($client);
            $connection = $this->getClientConnection();

            if (! $connection) {
                $this->error('   ❌ Falha na conexão');

                continue;
            }

            $result = $this->restoreProductsForClient($connection, $client->id, $preview, $chunkSize);
            $candidateCount = $result['candidate_count'];
            $totalCandidates += $candidateCount;

            if ($candidateCount === 0) {
                $this->line('   ✓ Nenhum produto deletado em layer ativa');

                continue;
            }

            $this->warn("   ⚠️  {$candidateCount} produto(s) soft-deleted em layers ativas");

            foreach ($result['sample'] as $product) {
                $name = mb_substr((string) ($product->name ?? 'Sem nome'), 0, 40);
                $ean = $product->ean ?? 'sem EAN';
                $this->line("      - {$ean}: {$name}");
            }

            if ($candidateCount > 5) {
                $remaining = $candidateCount - 5;
                $this->line("      ... e mais {$remaining} produto(s)");
            }

            if ($preview) {
                continue;
            }

            $restoredByClient = $result['restored_count'];

            $totalRestored += $restoredByClient;
            $this->info("   ✅ Restaurados: {$restoredByClient}");

            Log::info('Produtos restaurados por layer ativa', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'candidates' => $candidateCount,
                'restored' => $restoredByClient,
            ]);
        }

        $this->newLine();
        $this->info('✅ Processo concluído.');
        $this->line("   Total candidatos: {$totalCandidates}");

        if (! $preview) {
            $this->line("   Total restaurados: {$totalRestored}");
        }

        return self::SUCCESS;
    }

    /**
     * @return array{candidate_count:int,restored_count:int,sample:\Illuminate\Support\Collection<int,object>}
     */
    protected function restoreProductsForClient(string $connection, string $clientId, bool $preview, int $chunkSize): array
    {
        $query = DB::connection($connection)
            ->table('products')
            ->whereNotNull('products.deleted_at')
            ->where('products.client_id', $clientId)
            ->whereExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('layers')
                    ->whereColumn('layers.product_id', 'products.id')
                    ->whereNull('layers.deleted_at');
            });

        $candidateCount = (clone $query)->count();
        $sample = (clone $query)
            ->select('products.id', 'products.ean', 'products.name', 'products.deleted_at')
            ->limit(5)
            ->get();

        if ($preview || $candidateCount === 0) {
            return [
                'candidate_count' => $candidateCount,
                'restored_count' => 0,
                'sample' => $sample,
            ];
        }

        $ids = (clone $query)->pluck('products.id')->all();
        $restoredCount = 0;

        foreach (array_chunk($ids, $chunkSize) as $idsChunk) {
            $restored = DB::connection($connection)
                ->table('products')
                ->whereIn('id', $idsChunk)
                ->whereNotNull('deleted_at')
                ->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);

            $restoredCount += $restored;
        }

        return [
            'candidate_count' => $candidateCount,
            'restored_count' => $restoredCount,
            'sample' => $sample,
        ];
    }
}
