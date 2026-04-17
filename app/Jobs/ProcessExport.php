<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Jobs;

use App\Models\Client;
use Callcocam\LaravelRaptor\Events\ExportCompleted;
use Callcocam\LaravelRaptor\Exports\DefaultExport;
use Callcocam\LaravelRaptor\Notifications\ExportCompletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Job para processar exportações para Excel
 *
 * Versão local com suporte a multi-database (serializa Client para configurar conexão correta)
 */
class ProcessExport implements ShouldQueue
{
    use \App\Concerns\BelongsToConnection, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de tentativas do job
     */
    public int $tries = 3;

    /**
     * Tempo máximo de execução (15 minutos para exportações grandes)
     */
    public int $timeout = 900;

    /**
     * @param  string  $modelClass  Classe do model a ser exportado
     * @param  array  $filters  Filtros a serem aplicados na query
     * @param  array  $columns  Colunas a serem exportadas
     * @param  string  $fileName  Nome do arquivo final
     * @param  string  $filePath  Caminho onde salvar o arquivo
     * @param  string  $resourceName  Nome do recurso (para notificação)
     * @param  int|string  $userId  ID do usuário que iniciou a exportação
     * @param  Client|null  $client  Cliente para configurar conexão correta
     */
    public function __construct(
        protected string $modelClass,
        protected array $filters,
        protected array $columns,
        protected string $fileName,
        protected string $filePath,
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
            'export',
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
                Log::info('ProcessExport - Conexão configurada', [
                    'client_id' => $this->client->id,
                    'database' => $this->client->database,
                    'connection' => $connection,
                ]);
            }

            // Reconstrói a query a partir do model class
            $query = app($this->modelClass)->newQuery();

            // Aplica os filtros
            if (! empty($this->filters)) {
                foreach ($this->filters as $column => $value) {
                    if (is_array($value)) {
                        $query->whereIn($column, $value);
                    } elseif (! empty($value)) {
                        $query->where($column, 'like', "%{$value}%");
                    }
                }
            }

            // Cria o export
            $export = new DefaultExport($query, $this->columns);

            // Gera o arquivo
            Excel::store($export, $this->filePath, 'local');

            // Obtém o total de linhas exportadas
            $totalRows = $query->count();

            // Gera a URL de download
            $downloadUrl = route('download.export', ['filename' => $this->fileName]);

            // Envia notificação ao usuário
            $user = \App\Models\User::find($this->userId);
            if ($user) {
                $user->notify(new ExportCompletedNotification(
                    $this->fileName,
                    $downloadUrl,
                    $this->resourceName,
                    true // Indica que foi processado via job
                ));
            }

            // Dispara evento de broadcast para atualização em tempo real
            event(new ExportCompleted(
                userId: $this->userId,
                modelName: class_basename($this->modelClass),
                totalRows: $totalRows,
                filePath: $this->filePath,
                fileName: $this->fileName
            ));

            Log::info('ProcessExport - Exportação concluída', [
                'model' => class_basename($this->modelClass),
                'total_rows' => $totalRows,
                'file' => $this->fileName,
                'client_id' => $this->client?->id,
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessExport - Erro durante exportação', [
                'error' => $e->getMessage(),
                'file' => $this->filePath,
                'model' => $this->modelClass,
                'client_id' => $this->client?->id,
            ]);

            throw $e;
        }
    }
}
