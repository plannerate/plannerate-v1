<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Store;
use Callcocam\LaravelRaptor\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Serviço centralizado para resolução de bancos de dados em multi-tenancy
 *
 * Responsabilidade única: determinar qual banco usar baseado na hierarquia
 * Store > Client > Tenant > Default
 *
 * Documentação completa: docs/database-architecture.md
 */
class TenantDatabaseResolver
{
    /**
     * Hierarquia de prioridade para busca de banco
     */
    protected const HIERARCHY = [
        'store' => Store::class,
        'client' => Client::class,
        'tenant' => Tenant::class,
    ];

    /**
     * Resolve qual banco usar para um tenant/client/store
     *
     * Hierarquia: Store > Client > Tenant > Default
     *
     * @param  string|null  $storeId  ID do store (prioridade máxima)
     * @param  string|null  $clientId  ID do client (prioridade média)
     * @param  string|null  $tenantId  ID do tenant (prioridade baixa)
     * @return string Nome do banco de dados a ser usado
     */
    public function resolveDatabaseName(
        ?string $storeId = null,
        ?string $clientId = null,
        ?string $tenantId = null
    ): string {
        $database = $this->getDatabaseFromHierarchy($storeId, $clientId, $tenantId);

        // Se não encontrou em nenhuma hierarquia, usa o padrão
        return $database ?? config('database.connections.'.config('database.default').'.database');
    }

    /**
     * Busca banco por ordem de prioridade (Store > Client > Tenant)
     *
     * @return string|null Nome do banco ou null se não encontrado
     */
    public function getDatabaseFromHierarchy(
        ?string $storeId = null,
        ?string $clientId = null,
        ?string $tenantId = null
    ): ?string {
        // 1. Prioridade máxima: Store
        if ($storeId) {
            $database = $this->getDatabaseFromEntity(Store::class, $storeId);
            if ($database) {
                Log::debug('[TenantDatabaseResolver] Banco resolvido do Store', [
                    'store_id' => $storeId,
                    'database' => $database,
                ]);

                return $database;
            }
        }

        // 2. Prioridade média: Client
        if ($clientId) {
            $database = $this->getDatabaseFromEntity(Client::class, $clientId);
            if ($database) {
                Log::debug('[TenantDatabaseResolver] Banco resolvido do Client', [
                    'client_id' => $clientId,
                    'database' => $database,
                ]);

                return $database;
            }
        }

        // 3. Prioridade baixa: Tenant
        if ($tenantId) {
            $database = $this->getDatabaseFromEntity(Tenant::class, $tenantId);
            if ($database) {
                Log::debug('[TenantDatabaseResolver] Banco resolvido do Tenant', [
                    'tenant_id' => $tenantId,
                    'database' => $database,
                ]);

                return $database;
            }
        }

        return null;
    }

    /**
     * Busca o campo 'database' de uma entidade específica
     *
     * @param  string  $modelClass  Classe do model (Store::class, Client::class, Tenant::class)
     * @param  string  $id  ID da entidade
     * @return string|null Nome do banco ou null
     */
    protected function getDatabaseFromEntity(string $modelClass, string $id): ?string
    {
        $cacheKey = "database:{$modelClass}:{$id}";

        return Cache::remember($cacheKey, 3600, function () use ($modelClass, $id) {
            try {
                $entity = $modelClass::find($id);

                if (! $entity) {
                    return null;
                }

                $database = $entity->getAttribute('database');

                return $database && is_string($database) ? $database : null;
            } catch (\Exception $e) {
                Log::warning('[TenantDatabaseResolver] Erro ao buscar database da entidade', [
                    'model' => $modelClass,
                    'id' => $id,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Lista todos os bancos de tenant do sistema
     *
     * Busca em: Stores > Clients > Tenants
     * Remove duplicados e retorna coleção única
     *
     * @return Collection Coleção de arrays com ['type', 'id', 'name', 'database']
     */
    public function getAllTenantDatabases(): Collection
    {
        $databases = collect();

        // 1. Busca bancos de Stores
        Store::whereNotNull('database')
            ->where('database', '!=', '')
            ->get()
            ->each(function ($store) use ($databases) {
                $databases->push([
                    'type' => 'Store',
                    'id' => $store->id,
                    'name' => $store->name,
                    'database' => $store->database,
                ]);
            });

        // 2. Busca bancos de Clients (se não tiver store com mesmo banco)
        Client::whereNotNull('database')
            ->where('database', '!=', '')
            ->get()
            ->each(function ($client) use ($databases) {
                // Evita duplicados: se já existe um store com esse banco, pula
                if (! $databases->contains('database', $client->database)) {
                    $databases->push([
                        'type' => 'Client',
                        'id' => $client->id,
                        'name' => $client->name,
                        'database' => $client->database,
                    ]);
                }
            });

        // 3. Busca bancos de Tenants (se não tiver client/store com mesmo banco)
        Tenant::whereNotNull('database')
            ->where('database', '!=', '')
            ->get()
            ->each(function ($tenant) use ($databases) {
                // Evita duplicados
                if (! $databases->contains('database', $tenant->database)) {
                    $databases->push([
                        'type' => 'Tenant',
                        'id' => $tenant->id,
                        'name' => $tenant->name ?? "Tenant {$tenant->id}",
                        'database' => $tenant->database,
                    ]);
                }
            });

        return $databases;
    }

    /**
     * Verifica se está em contexto de banco único (shared) ou múltiplos
     *
     * @return bool True se todos compartilham o mesmo banco
     */
    public function isSharedDatabase(): bool
    {
        $databases = $this->getAllTenantDatabases();

        // Se não há bancos configurados, é shared (usa apenas o padrão)
        if ($databases->isEmpty()) {
            return true;
        }

        // Se todos os bancos configurados apontam para o mesmo, é shared
        $uniqueDatabases = $databases->pluck('database')->unique();

        return $uniqueDatabases->count() === 1 &&
               $uniqueDatabases->first() === config('database.connections.'.config('database.default').'.database');
    }

    /**
     * Retorna estratégia de multi-tenancy em uso
     *
     * @return string 'shared', 'per_client', 'per_store', 'mixed', 'per_tenant'
     */
    public function getStrategy(): string
    {
        $databases = $this->getAllTenantDatabases();

        if ($databases->isEmpty()) {
            return 'shared';
        }

        $types = $databases->pluck('type')->unique();

        // Se tem múltiplos tipos, é mixed
        if ($types->count() > 1) {
            return 'mixed';
        }

        // Se tem apenas um tipo
        $singleType = $types->first();

        return match ($singleType) {
            'Store' => 'per_store',
            'Client' => 'per_client',
            'Tenant' => 'per_tenant',
            default => 'unknown',
        };
    }

    /**
     * Busca dados completos de um domainable (Store/Client) incluindo seu banco
     *
     * @param  string  $domainableType  Classe do domainable
     * @param  string  $domainableId  ID do domainable
     * @return object|null Objeto com database e model
     */
    public function getDomainableWithDatabase(string $domainableType, string $domainableId): ?object
    {
        if (! class_exists($domainableType)) {
            return null;
        }

        try {
            $domainable = $domainableType::find($domainableId);

            if (! $domainable) {
                return null;
            }

            $database = $domainable->getAttribute('database');

            return (object) [
                'model' => $domainable,
                'database' => $database && is_string($database) ? $database : null,
                'type' => $domainableType,
                'id' => $domainableId,
            ];
        } catch (\Exception $e) {
            Log::warning('[TenantDatabaseResolver] Erro ao buscar domainable', [
                'type' => $domainableType,
                'id' => $domainableId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Limpa cache de bancos (útil após alterações)
     */
    public function clearCache(): void
    {
        // Limpa cache de stores
        Store::all()->each(function ($store) {
            Cache::forget('database:'.Store::class.":{$store->id}");
        });

        // Limpa cache de clients
        Client::all()->each(function ($client) {
            Cache::forget('database:'.Client::class.":{$client->id}");
        });

        // Limpa cache de tenants
        Tenant::all()->each(function ($tenant) {
            Cache::forget('database:'.Tenant::class.":{$tenant->id}");
        });

        Log::info('[TenantDatabaseResolver] Cache de bancos limpo');
    }
}
