<?php

/**
 * Job para deletar vendas anteriores ao período configurado na integração.
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

class CleanupOldSalesJob implements ShouldQueue
{
    use BelongsToConnection, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 900; // 15 minutos (pode ter muitas vendas)

    public function __construct(
        protected Client $client,
        protected array $saleIds
    ) {}

    public function handle(): void
    {
        Log::info('Iniciando limpeza de vendas antigas', [
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
            Log::error('Falha ao configurar conexão para limpeza de vendas antigas', [
                'client_id' => $this->client->id,
            ]);

            return;
        }

        // Processar em chunks para não sobrecarregar
        $chunks = array_chunk($this->saleIds, 1000);
        $totalDeleted = 0;

        foreach ($chunks as $index => $chunk) {
            // Delete permanente
            $deleted = DB::connection($connection)
                ->table('sales')
                ->whereIn('id', $chunk)
                ->delete();

            $totalDeleted += $deleted;

            // Log de progresso a cada chunk
            $progress = ($index + 1) * 1000;
            Log::debug("Progresso limpeza vendas antigas: {$progress}/{$this->saleIds} processados", [
                'client_id' => $this->client->id,
            ]);
        }

        Log::info('Limpeza de vendas antigas concluída', [
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'total_deleted' => $totalDeleted,
        ]);
    }

    public function tags(): array
    {
        return [
            'cleanup',
            'old-sales',
            "client:{$this->client->id}",
        ];
    }
}
