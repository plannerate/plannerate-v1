<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Services;

use App\Models\Client;
use Callcocam\LaravelRaptor\Services\TenantDatabaseManager;
use Illuminate\Contracts\Config\Repository;

/**
 * Executa as migrações do diretório database/migrations/clients no banco do client.
 * Se o client tiver o campo database preenchido, usa esse banco (criando-o se não existir).
 * Caso contrário, roda no banco central (conexão default).
 * Inclui o path de migrations do pacote flow (flow.client_migrations_path) quando definido.
 */
class ClientMigrationService
{
    public function __construct(
        protected TenantDatabaseManager $tenantDatabaseManager,
        protected Repository $config
    ) {}

    /**
     * Garante o banco do client (se dedicado) e roda apenas as migrações de database/migrations/clients.
     * Restaura a conexão default ao final para não afetar o restante do request.
     */
    public function runClientMigrations(Client $client): void
    {
        $database = ! empty($client->database)
            ? $client->database
            : $this->tenantDatabaseManager->getDefaultDatabaseName();

        if (empty($database)) {
            return;
        }

        $restoreDatabase = $this->tenantDatabaseManager->getDefaultDatabaseName();

        $paths = array_values(array_filter([
            'database/migrations/clients',
            $this->config->get('flow.client_migrations_path'),
        ]));

        try {
            $this->tenantDatabaseManager->ensureDatabaseAndRunMigrations(
                $database,
                $paths,
                null
            );
        } finally {
            $this->tenantDatabaseManager->setupConnection($restoreDatabase);
        }
    }
}
