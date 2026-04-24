<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Concerns;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait BelongsToConnection
{
    protected ?string $clientConnection = null;

    /**
     * Configura a conexão dinâmica do banco de dados do client
     *
     * @param  Client  $client  Cliente para configurar conexão
     * @return string Nome da conexão configurada ('tenant')
     */
    protected function setupClientConnection(Client $client): string
    {
        $clientId = $client->id;

        // Forçar reconfiguração da conexão para cada cliente
        $this->clientConnection = null;

        try {
            if (! $client) {
                throw new \Exception("Client {$clientId} não encontrado");
            }

            if (empty($client->database)) {
                throw new \Exception("Client {$clientId} não possui database configurado");
            }

            // SOLUÇÃO: Atualiza a conexão 'tenant' existente ao invés de criar uma nova
            // Isso garante que os models que usam ->on('tenant') funcionem corretamente
            $connectionName = 'tenant';

            // Copia a configuração da conexão tenant atual
            $tenantConfig = config("database.connections.{$connectionName}");

            // Atualiza o database para o database do client
            $tenantConfig['database'] = $client->database;

            // Atualiza a configuração da conexão tenant
            Config::set("database.connections.{$connectionName}", $tenantConfig);

            // Limpa a conexão existente para forçar reconexão com novo database
            DB::purge($connectionName);

            // Armazena para uso futuro
            $this->clientConnection = $connectionName;

            return $connectionName;
        } catch (\Exception $e) {
            Log::error('BelongsToConnection - Erro ao configurar conexão do client', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
                'database' => $client->database ?? 'não informado',
            ]);

            // Fallback para conexão tenant padrão
            return 'tenant';
        }
    }

    /**
     * Configura o database da conexão tenant pelo nome (sem precisar do model Client).
     * Usado em jobs quando o client é passado por id/database no dispatch.
     */
    protected function setTenantDatabase(string $database): string
    {
        $connectionName = 'tenant';
        $tenantConfig = config("database.connections.{$connectionName}");
        $tenantConfig['database'] = $database;
        Config::set("database.connections.{$connectionName}", $tenantConfig);
        DB::purge($connectionName);
        $this->clientConnection = $connectionName;

        return $connectionName;
    }

    public function getClientConnection(): ?string
    {
        return $this->clientConnection;
    }
}
