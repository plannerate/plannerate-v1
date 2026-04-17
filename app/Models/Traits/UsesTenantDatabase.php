<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\Config;

trait UsesTenantDatabase
{
    /**
     * Retorna a conexão a ser usada pelo model.
     * 
     * Segue a hierarquia: Store > Client > Tenant > Default
     * Se a conexão 'tenant' existir (configurada pelo TenantMiddleware), usa ela.
     * Caso contrário, usa a conexão padrão.
     */
    public function getConnectionName(): ?string
    {
        // Se a conexão 'tenant' foi configurada pelo TenantMiddleware, usa ela
        if (Config::has('database.connections.tenant')) {
            return 'tenant';
        }

        // Usa a conexão padrão do model ou do sistema
        return parent::getConnectionName() ?? config('database.default');
    }
}

