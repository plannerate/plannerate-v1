<?php

namespace App\Support\Database;

final class DatabaseConnectionConfigBuilder
{
    public function build(string $driver, array $config): array
    {
        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'url' => $config['url'] ?? null,
                'database' => $config['database'] ?? database_path('database.sqlite'),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        if ($driver === 'pgsql') {
            return [
                'driver' => 'pgsql',
                'url' => $config['url'] ?? null,
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? '5432',
                'database' => $config['database'] ?? 'laravel',
                'username' => $config['username'] ?? 'root',
                'password' => $config['password'] ?? '',
                'charset' => $config['charset'] ?? 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => $config['search_path'] ?? 'public',
                'sslmode' => $config['sslmode'] ?? 'prefer',
            ];
        }

        return $this->build('pgsql', $config);
    }
}
