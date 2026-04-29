<?php

namespace Callcocam\LaravelRaptorPlannerate\Models\Traits;

trait UsesPlannerateTenantConnection
{
    public function getConnectionName(): ?string
    {
        $tenantConnection = config('multitenancy.tenant_database_connection_name');

        if (is_string($tenantConnection) && $tenantConnection !== '') {
            return $tenantConnection;
        }

        $defaultConnection = config('database.default');

        return is_string($defaultConnection) && $defaultConnection !== '' ? $defaultConnection : null;
    }
}
