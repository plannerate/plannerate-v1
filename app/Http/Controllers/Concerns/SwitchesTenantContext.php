<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Models\Tenant as CurrentTenantModel;

trait SwitchesTenantContext
{
    /**
     * Executa o callback temporariamente com o tenant informado como "atual" (troca a
     * conexão de banco), restaurando o tenant/conexão anterior ao final (mesmo em caso
     * de exceção).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    protected function runInTenantContext(Tenant $tenant, callable $callback): mixed
    {
        $tenantConnectionName = $this->resolveTenantConnectionName();
        $originalTenantDatabase = config("database.connections.{$tenantConnectionName}.database");
        $originalTenant = CurrentTenantModel::current();
        $tenant->makeCurrent();

        try {
            return $callback();
        } finally {
            if ($originalTenant !== null) {
                $originalTenant->makeCurrent();
            } else {
                CurrentTenantModel::forgetCurrent();
                config([
                    "database.connections.{$tenantConnectionName}.database" => $originalTenantDatabase,
                ]);
                DB::purge($tenantConnectionName);
            }
        }
    }

    /**
     * Nome da conexão de banco usada para tenants, conforme configurado em multitenancy.php.
     */
    protected function resolveTenantConnectionName(): string
    {
        return (string) (config('multitenancy.tenant_database_connection_name') ?: 'tenant');
    }
}
