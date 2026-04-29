<?php

/**
 * Job para deletar vendas órfãs (sem produto correspondente).
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

class CleanupOrphanSalesJob implements ShouldQueue
{
    use BelongsToConnection, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        protected Client $client,
        protected array $saleIds
    ) {}

    public function handle(): void
    {
        Log::info('Iniciando limpeza de vendas órfãs', [
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'count' => count($this->saleIds),
        ]);

        // Configurar contexto do tenant
        config([
            'app.current_tenant_id' => $this->client->tenant_id,
            'app.current_client_id' => $this->client->id,
        ]);

        $this->setupClientConnection($this->client);
        $connection = $this->getClientConnection();

        if (! $connection) {
            Log::error('Falha ao configurar conexão para limpeza de vendas órfãs', [
                'client_id' => $this->client->id,
            ]);

            return;
        }

        // Processar em chunks para não sobrecarregar
        $chunks = array_chunk($this->saleIds, 500);
        $totalDeleted = 0;

        foreach ($chunks as $chunk) {
            // Delete permanente (não soft delete, pois são dados inválidos)
            $deleted = DB::connection($connection)
                ->table('sales')
                ->whereIn('id', $chunk)
                ->delete();

            $totalDeleted += $deleted;
        }

        Log::info('Limpeza de vendas órfãs concluída', [
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'total_deleted' => $totalDeleted,
        ]);
    }

    public function tags(): array
    {
        return [
            'cleanup',
            'orphan-sales',
            "client:{$this->client->id}",
        ];
    }
}