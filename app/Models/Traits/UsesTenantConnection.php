<?php

namespace App\Models\Traits;

trait UsesTenantConnection
{
    public function getConnectionName(): ?string
    {
        return $this->getConnectionNameFromTenantConfig();
    }

    protected function getConnectionNameFromTenantConfig(): ?string
    {
        $tenantConnection = config('multitenancy.tenant_database_connection_name');

        if (is_string($tenantConnection) && $tenantConnection !== '') {
            return $tenantConnection;
        }

        $defaultConnection = config('database.default');

        return is_string($defaultConnection) && $defaultConnection !== '' ? $defaultConnection : null;
    }
}
