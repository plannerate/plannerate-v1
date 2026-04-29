<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Concerns;

use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait BelongsToConnection
{
    protected ?string $tenantConnection = null;

    /**
     * @return string Nome da conexão configurada ('tenant')
     */
    protected function setupTenantConnection(Tenant $tenant): string
    {
        $tenantId = $tenant->id;

        $this->tenantConnection = null;

        try {
            if (! $tenant) {
                throw new \Exception("Tenant {$tenantId} não encontrado");
            }

            if (empty($tenant->database)) {
                throw new \Exception("Tenant {$tenantId} não possui database configurado");
            }

            $connectionName = $this->tenantConnectionName();

            // Copia a configuração da conexão tenant atual
            $tenantConfig = config("database.connections.{$connectionName}");

            // Atualiza o database para o database do tenant
            $tenantConfig['database'] = $tenant->database;

            // Atualiza a configuração da conexão tenant
            Config::set("database.connections.{$connectionName}", $tenantConfig);

            // Limpa a conexão existente para forçar reconexão com novo database
            DB::purge($connectionName);

            // Armazena para uso futuro
            $this->tenantConnection = $connectionName;

            return $connectionName;
        } catch (\Exception $e) {
            Log::error('BelongsToConnection - Erro ao configurar conexão do tenant', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
                'database' => $tenant->database ?? 'não informado',
            ]);

            // Fallback para conexão tenant padrão
            return $this->tenantConnectionName();
        }
    }

    /**
     * Configura o database da conexão tenant pelo nome.
     * Usado em jobs quando o tenant é passado por id/database no dispatch.
     */
    protected function setTenantDatabase(string $database): string
    {
        $connectionName = $this->tenantConnectionName();
        $tenantConfig = config("database.connections.{$connectionName}");
        $tenantConfig['database'] = $database;
        Config::set("database.connections.{$connectionName}", $tenantConfig);
        DB::purge($connectionName);
        $this->tenantConnection = $connectionName;

        return $connectionName;
    }

    public function getTenantConnection(): ?string
    {
        return $this->tenantConnection;
    }

    protected function tenantConnectionName(): string
    {
        $tenantConnection = config('multitenancy.tenant_database_connection_name');

        return is_string($tenantConnection) && $tenantConnection !== ''
            ? $tenantConnection
            : (string) config('database.default');
    }
}
