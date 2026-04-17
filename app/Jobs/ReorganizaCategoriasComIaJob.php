<?php

namespace App\Jobs;

use App\Concerns\BelongsToConnection;
use App\Models\Client;
use App\Models\User;
use App\Notifications\ReorganizacaoMercadologicoProntaNotification;
use App\Services\ReorganizaCategoriasComIa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job em fila que gera a sugestão de reorganização do mercadológico com IA
 * e notifica o usuário quando estiver pronto.
 */
class ReorganizaCategoriasComIaJob implements ShouldQueue
{
    use BelongsToConnection, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  int|string  $userId  ID do usuário que disparou (para notificar)
     * @param  int|string|null  $clientId  ID do cliente (tenant)
     * @param  string|null  $clientDatabase  Database do cliente (para conexão no worker)
     * @param  int|string|null  $clientTenantId  tenant_id do cliente
     * @param  string|null  $mercadologicoIndexUrl  URL da página mercadológico (gerada no controller; no worker a rota não existe)
     */
    public function __construct(
        protected int|string $userId,
        protected int|string|null $clientId = null,
        protected ?string $clientDatabase = null,
        protected int|string|null $clientTenantId = null,
        protected ?string $mercadologicoIndexUrl = null
    ) {}

    public function handle(): void
    {
        $previousDefaultConnection = config('database.default');
        $this->setupTenantConnection();

        try {
            $service = new ReorganizaCategoriasComIa;
            $log = $service->sugerir();

            $user = User::on(config('raptor.database.landlord_connection_name', 'landlord'))->find($this->userId);
            if ($user) {
                $user->notify(new ReorganizacaoMercadologicoProntaNotification($log->id, $this->mercadologicoIndexUrl));
            }

            Log::info('Reorganização mercadológico (IA) concluída', [
                'log_id' => $log->id,
                'user_id' => $this->userId,
                'client_id' => $this->clientId,
            ]);
        } catch (\Throwable $e) {
            Log::error('ReorganizaCategoriasComIaJob falhou', [
                'user_id' => $this->userId,
                'client_id' => $this->clientId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            config(['database.default' => $previousDefaultConnection]);
            DB::purge('tenant');
        }
    }

    private function setupTenantConnection(): void
    {
        if ($this->clientDatabase !== null && $this->clientDatabase !== '') {
            $this->setTenantDatabase($this->clientDatabase);
            if ($this->clientTenantId !== null) {
                config(['app.current_tenant_id' => $this->clientTenantId]);
            }
            if ($this->clientId !== null) {
                config(['app.current_client_id' => $this->clientId]);
            }
            $this->useTenantAsDefaultConnection();

            return;
        }

        if ($this->clientId !== null) {
            $connection = config('raptor.database.landlord_connection_name', 'landlord');
            $client = Client::on($connection)->find($this->clientId);
            if ($client) {
                $this->setupClientConnection($client);
                config(['app.current_tenant_id' => $client->tenant_id]);
                config(['app.current_client_id' => $client->id]);
                $this->useTenantAsDefaultConnection();
            }
        }
    }

    /**
     * Define a conexão 'tenant' como padrão para esta execução, para que
     * os models (Category, Product, etc.) usem o banco do cliente.
     */
    private function useTenantAsDefaultConnection(): void
    {
        config(['database.default' => 'tenant']);
    }

    public function tags(): array
    {
        return [
            'reorganize-mercadologico',
            $this->clientId !== null ? "client:{$this->clientId}" : 'no-client',
        ];
    }
}
