<?php

/**
 * Job para restaurar produtos deletados que tiveram vendas.
 *
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Jobs\Sync\Cleanup;

use App\Concerns\BelongsToConnection;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RestoreSoldProductsJob implements ShouldQueue
{
    use BelongsToConnection, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        protected Client $client,
        protected array $productIds
    ) {}

    public function handle(): void
    {
        Log::info('Iniciando restauração de produtos com vendas', [
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'count' => count($this->productIds),
        ]);

        // Configurar contexto do tenant
        config([
            'app.current_tenant_id' => $this->client->tenant_id,
            'app.current_client_id' => $this->client->id,
        ]);

        $this->setupClientConnection($this->client);
        $connection = $this->getClientConnection();

        if (! $connection) {
            Log::error('Falha ao configurar conexão para restauração de produtos', [
                'client_id' => $this->client->id,
            ]);

            return;
        }

        // Processar em chunks
        $chunks = array_chunk($this->productIds, 500);
        $totalRestored = 0;

        foreach ($chunks as $chunk) {
            // Restore - remove deleted_at
            $restored = DB::connection($connection)
                ->table('products')
                ->whereIn('id', $chunk)
                ->whereNotNull('deleted_at')
                ->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);

            $totalRestored += $restored;
        }

        Log::info('Restauração de produtos com vendas concluída', [
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'total_restored' => $totalRestored,
        ]);
    }

    public function tags(): array
    {
        return [
            'cleanup',
            'restore-products',
            "client:{$this->client->id}",
        ];
    }
}