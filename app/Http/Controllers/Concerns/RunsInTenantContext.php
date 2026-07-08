<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Controllers\Landlord\WorkflowTemplateController;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Models\Tenant as CurrentTenantModel;

/**
 * Executa um callback no contexto de banco de um tenant específico e restaura o
 * contexto anterior no final — usado por controllers do landlord que precisam
 * ler/gravar dados de um tenant (ex.: templates de kanban, mercadológico).
 *
 * Espelha exatamente o comportamento originalmente privado no
 * {@see WorkflowTemplateController}.
 */
trait RunsInTenantContext
{
    /**
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

    protected function resolveTenantConnectionName(): string
    {
        return (string) (config('multitenancy.tenant_database_connection_name') ?: 'tenant');
    }
}
