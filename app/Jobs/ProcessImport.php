<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Jobs;

use App\Models\Client;
use Callcocam\LaravelRaptor\Events\ImportCompleted;
use Callcocam\LaravelRaptor\Notifications\ImportCompletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Job para processar importações de arquivos Excel
 *
 * Versão local com suporte a multi-database (serializa Client para configurar conexão correta)
 */
class ProcessImport implements ShouldQueue
{
    use \App\Concerns\BelongsToConnection, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de tentativas do job
     */
    public int $tries = 3;

    /**
     * Tempo máximo de execução (10 minutos para importações grandes)
     */
    public int $timeout = 600;

    /**
     * @param  string  $filePath  Caminho do arquivo no storage
     * @param  string  $modelClass  Classe do model a ser importado
     * @param  array|null  $columnMapping  Mapeamento de colunas
     * @param  string  $importClass  Classe do import a ser usado
     * @param  string  $resourceName  Nome do recurso (para notificação)
     * @param  int|string  $userId  ID do usuário que iniciou a importação
     * @param  Client|null  $client  Cliente para configurar conexão correta
     */
    public function __construct(
        protected string $filePath,
        protected string $modelClass,
        protected ?array $columnMapping,
        protected string $importClass,
        protected string $resourceName,
        protected int|string $userId,
        protected ?Client $client = null
    ) {}

    /**
     * Tags para organizar no Horizon
     */
    public function tags(): array
    {
        return [
            'import',
            'excel',
            class_basename($this->modelClass),
            $this->client ? "client:{$this->client->id}" : 'no-client',
        ];
    }

    public function handle(): void
    {
        try {
            // Configura a conexão do client se fornecido
            if ($this->client) {
                $connection = $this->setupClientConnection($this->client);
                Log::info('ProcessImport - Conexão configurada', [
                    'client_id' => $this->client->id,
                    'database' => $this->client->database,
                    'connection' => $connection,
                ]);
            }

            // Cria a instância do import
            $import = new $this->importClass($this->modelClass, $this->columnMapping);

            // Processa a importação
            Excel::import($import, $this->filePath, 'local');

            // Remove o arquivo temporário
            if (file_exists(storage_path('app/'.$this->filePath))) {
                unlink(storage_path('app/'.$this->filePath));
            }

            // Obtém estatísticas da importação (se disponível)
            $totalRows = $import->getRowCount() ?? 0;
            $successfulRows = $import->getSuccessfulCount() ?? $totalRows;
            $failedRows = $import->getFailedCount() ?? 0;

            // Envia notificação ao usuário
            $user = \App\Models\User::find($this->userId);
            if ($user) {
                $user->notify(new ImportCompletedNotification(
                    $this->resourceName,
                    true // Indica que foi processado via job
                ));
            }

            // Dispara evento de broadcast para atualização em tempo real
            event(new ImportCompleted(
                userId: $this->userId,
                modelName: class_basename($this->modelClass),
                totalRows: $totalRows,
                successfulRows: $successfulRows,
                failedRows: $failedRows,
                fileName: basename($this->filePath)
            ));

            Log::info('ProcessImport - Importação concluída', [
                'model' => class_basename($this->modelClass),
                'total_rows' => $totalRows,
                'successful' => $successfulRows,
                'failed' => $failedRows,
                'client_id' => $this->client?->id,
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessImport - Erro durante importação', [
                'error' => $e->getMessage(),
                'file' => $this->filePath,
                'model' => $this->modelClass,
                'client_id' => $this->client?->id,
            ]);

            throw $e;
        }
    }
}
