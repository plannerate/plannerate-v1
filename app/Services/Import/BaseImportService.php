<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Services\Import;

use App\Models\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class BaseImportService
{
    /**
     * Configura a conexão dinâmica do banco de dados do client
     *
     * @param  string  $clientId  ID do client
     * @return string Nome da conexão configurada
     */
    protected function setupClientConnection(string $clientId): string
    {
        // Se já foi configurada, retorna a conexão existente
        if ($this->clientConnection) {
            return $this->clientConnection;
        }

        try {
            // Busca o client no banco landlord (conexão padrão)
            $client = Client::find($clientId);

            if (! $client) {
                throw new \Exception("Client {$clientId} não encontrado");
            }

            if (empty($client->database)) {
                throw new \Exception("Client {$clientId} não possui database configurado");
            }

            // SOLUÇÃO: Atualiza a conexão 'tenant' existente ao invés de criar uma nova
            // Isso garante que os models usem o database correto
            $connectionName = 'tenant';

            // Copia a configuração da conexão tenant atual
            $tenantConfig = config("database.connections.{$connectionName}");

            // Atualiza o database para o database do client
            $tenantConfig['database'] = $client->database;

            // Atualiza a configuração da conexão tenant
            Config::set("database.connections.{$connectionName}", $tenantConfig);

            // Limpa a conexão existente para forçar reconexão com novo database
            \DB::purge($connectionName);

            // Armazena para uso futuro
            $this->clientConnection = $connectionName;

            Log::info('BaseImportService - Conexão tenant atualizada para o client', [
                'client_id' => $clientId,
                'database' => $client->database,
                'connection_name' => $connectionName,
            ]);

            return $connectionName;
        } catch (\Exception $e) {
            Log::error('BaseImportService - Erro ao configurar conexão do client', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);

            // Fallback para conexão tenant padrão
            return 'tenant';
        }
    }
}
