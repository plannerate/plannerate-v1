<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Jobs\Export;

use App\Concerns\BelongsToConnection;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

abstract class AbstractExportJob implements ShouldQueue
{
    use BelongsToConnection, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    /**
     * @param  array<string, mixed>  $filters
     * @param  int|string|null  $clientId  ID do client
     * @param  string|null  $clientDatabase  Nome do database do tenant (passado no dispatch para o worker configurar a conexão sem buscar Client)
     * @param  int|string|null  $clientTenantId  tenant_id do client (opcional, para config)
     */
    public function __construct(
        protected array $filters,
        protected string $fileName,
        protected string $filePath,
        protected string $resourceName,
        protected int|string $userId,
        protected int|string|null $clientId = null,
        protected ?string $clientDatabase = null,
        protected int|string|null $clientTenantId = null
    ) {}

    public function tags(): array
    {
        return [
            'export',
            $this->jobTag(),
            $this->clientId !== null ? "client:{$this->clientId}" : 'no-client',
        ];
    }

    public function handle(): void
    {
        $this->setupExportConnection();

        try {
            $totalRows = $this->getExportService()::make()->exportToFile(
                $this->filters,
                $this->filePath,
                $this->fileName,
                $this->resourceName,
                $this->userId
            );

            Log::info($this->logMessage('concluído'), [
                'fileName' => $this->fileName,
                'totalRows' => $totalRows,
                'userId' => $this->userId,
            ]);
        } catch (\Throwable $e) {
            Log::error($this->logMessage('falhou'), [
                'fileName' => $this->fileName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function setupExportConnection(): void
    {
        // 1) Se temos database do client no payload (passado no dispatch), usamos direto — evita buscar Client no worker
        if ($this->clientDatabase !== null && $this->clientDatabase !== '') {
            $this->setTenantDatabase($this->clientDatabase);
            if ($this->clientTenantId !== null) {
                config(['app.current_tenant_id' => $this->clientTenantId]);
            }
            if ($this->clientId !== null) {
                config(['app.current_client_id' => $this->clientId]);
            }
            Log::info($this->logMessage('Conexão configurada'), [
                'client_id' => $this->clientId,
                'database' => $this->clientDatabase,
            ]);

            return;
        }

        // 2) Senão, tenta resolver Client na conexão landlord (pode falhar no worker)
        if ($this->clientId !== null) {
            $connection = config('raptor.database.landlord_connection_name', 'landlord');
            $client = Client::on($connection)->find($this->clientId);
            if ($client) {
                $this->setupClientConnection($client);
                config(['app.current_tenant_id' => $client->tenant_id]);
                config(['app.current_client_id' => $client->id]);
                Log::info($this->logMessage('Conexão configurada'), [
                    'client_id' => $client->id,
                    'database' => $client->database ?? 'n/a',
                ]);

                return;
            }
        }

        // 3) Fallback: só config de tenant/client a partir dos filters
        $tenantId = $this->filters['tenant_id'] ?? config('app.current_tenant_id');
        $clientId = $this->filters['client_id'] ?? config('app.current_client_id');
        if ($tenantId !== null) {
            config(['app.current_tenant_id' => $tenantId]);
        }
        if ($clientId !== null) {
            config(['app.current_client_id' => $clientId]);
        }
    }

    /** Nome do job para logs (ex.: 'ExportCategoryJob'). */
    protected function logMessage(string $suffix): string
    {
        return class_basename(static::class).' '.$suffix;
    }

    /** Classe do service de export (ex.: CategoryExportService::class). */
    abstract protected function getExportService(): string;

    /** Tag para o Horizon (ex.: 'categories', 'products'). */
    abstract protected function jobTag(): string;
}
