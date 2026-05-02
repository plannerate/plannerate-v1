<?php

namespace App\Support\Database;

use Illuminate\Database\Connection;
use InvalidArgumentException;

final class DatabaseCreator
{
    public function ensureExists(Connection $connection, string $database): void
    {
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $databaseExists = $connection->table('pg_database')
                ->where('datname', $database)
                ->exists();

            if (! $databaseExists) {
                $connection->statement(sprintf('CREATE DATABASE "%s"', str_replace('"', '""', $database)));
            }

            return;
        }

        throw new InvalidArgumentException(sprintf('Automatic database provisioning is not supported for driver [%s].', $driver));
    }
}
