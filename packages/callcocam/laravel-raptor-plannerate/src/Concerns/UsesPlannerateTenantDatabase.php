<?php

namespace Callcocam\LaravelRaptorPlannerate\Concerns;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait UsesPlannerateTenantDatabase
{
    protected function plannerateTenantConnectionName(): string
    {
        $tenantConnection = config('multitenancy.tenant_database_connection_name');

        return is_string($tenantConnection) && $tenantConnection !== ''
            ? $tenantConnection
            : (string) config('database.default');
    }

    protected function plannerateTenantDatabase(): Connection
    {
        return DB::connection($this->plannerateTenantConnectionName());
    }

    protected function plannerateTenantTable(string $table): Builder
    {
        return $this->plannerateTenantDatabase()->table($table);
    }

    /**
     * A tabela existe no banco do tenant?
     *
     * Para tabelas OPCIONAIS (caches que podem não ter sido criadas em todos os tenants):
     * consultar direto uma tabela ausente derruba a operação inteira com "relation does not
     * exist", em vez de cair no caminho alternativo que já existe.
     */
    protected function plannerateTenantHasTable(string $table): bool
    {
        return $this->plannerateTenantDatabase()->getSchemaBuilder()->hasTable($table);
    }
}
