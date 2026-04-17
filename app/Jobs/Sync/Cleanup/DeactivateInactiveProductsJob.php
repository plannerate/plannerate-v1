<?php

/**
 * Job para desativar produtos sem vendas (soft delete).
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

class DeactivateInactiveProductsJob implements ShouldQueue
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
        Log::info('Iniciando desativação de produtos inativos', [
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
            Log::error('Falha ao configurar conexão para desativação de produtos', [
                'client_id' => $this->client->id,
            ]);

            return;
        }

        // Processar em chunks
        $chunks = array_chunk($this->productIds, 500);
        $totalDeactivated = 0;

        foreach ($chunks as $chunk) {
            // Soft delete - apenas marca deleted_at
            $deactivated = DB::connection($connection)
                ->table('products')
                ->whereIn('id', $chunk)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);

            $totalDeactivated += $deactivated;
        }

        Log::info('Desativação de produtos inativos concluída', [
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'total_deactivated' => $totalDeactivated,
        ]);
    }

    public function tags(): array
    {
        return [
            'cleanup',
            'inactive-products',
            "client:{$this->client->id}",
        ];
    }
}
