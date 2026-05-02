<?php

namespace App\Support\Database;

use PDO;
use Pdo\Mysql;

final class DatabaseConnectionConfigBuilder
{
    public function build(string $driver, array $config): array
    {
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

        return [
            'driver' => $driver,
            'url' => $config['url'] ?? null,
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? '3306',
            'database' => $config['database'] ?? 'laravel',
            'username' => $config['username'] ?? 'root',
            'password' => $config['password'] ?? '',
            'unix_socket' => $config['unix_socket'] ?? '',
            'charset' => $config['charset'] ?? 'utf8mb4',
            'collation' => $config['collation'] ?? 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => $config['mysql_ssl_ca'] ?? env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ];
    }
}
