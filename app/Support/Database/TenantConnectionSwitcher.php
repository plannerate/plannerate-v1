<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Aponta a conexão de tenant para um banco específico.
 *
 * Trocar apenas o `config` não basta: o `SwitchTenantDatabaseTask` do Spatie registra uma
 * extension no DatabaseManager (`app('db')->extend()`) com o nome do banco capturado na
 * closure, e `DatabaseManager::makeConnection()` dá precedência à extension sobre o config.
 * Num worker de fila isso é permanente no processo: antes de um job `NotTenantAware` o
 * pacote chama `Tenant::forgetCurrent()`, que deixa a extension registrada com
 * `database = null` — daí qualquer `config()` + `DB::purge()` posterior é ignorado e a
 * conexão resolve para um banco vazio.
 */
final class TenantConnectionSwitcher
{
    public function useDatabase(string $connectionName, ?string $database): void
    {
        config(["database.connections.{$connectionName}.database" => $database]);

        app('db')->extend($connectionName, function (array $config, string $name) use ($database) {
            $config['database'] = $database;

            return app('db.factory')->make($config, $name);
        });

        DB::purge($connectionName);
    }
}
