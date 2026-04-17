<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TenantCreateDatabaseCommand extends Command
{
    protected $signature = 'tenant:create-database 
                            {database : Nome do banco de dados a criar}
                            {--force : Força a execução sem confirmação}';

    protected $description = 'Cria um novo banco de dados para client e executa as migrations (não apaga nada existente)';

    protected string $defaultConnection;

    public function handle(): int
    {
        $this->defaultConnection = config('database.default');
        $database = $this->argument('database');
        $force = $this->option('force');

        $this->info("🚀 Criando banco de dados: {$database}");
        $this->newLine();

        // Verificar se o banco já existe
        $exists = $this->databaseExists($database);

        if ($exists) {
            $this->info("   ℹ️  Banco '{$database}' já existe.");
            
            if (! $force && ! $this->confirm('Deseja executar as migrations pendentes?', true)) {
                $this->warn('Operação cancelada.');

                return self::FAILURE;
            }
        } else {
            if (! $force && ! $this->confirm("Deseja criar o banco '{$database}' e executar migrations?", true)) {
                $this->warn('Operação cancelada.');

                return self::FAILURE;
            }

            $this->info("   📦 Criando banco '{$database}'...");
            $this->createDatabase($database);
            $this->info('   ✅ Banco criado!');
        }

        // Configurar conexão tenant
        $this->setupTenantConnection($database);

        // Executar migrations (apenas as pendentes, nunca fresh)
        $this->newLine();
        $this->info('   🔄 Executando migrations...');

        $paths = array_values(array_filter([
            'database/migrations/clients',
            config('flow.client_migrations_path'),
        ]));

        foreach ($paths as $path) {
            $realpath = str_starts_with($path, DIRECTORY_SEPARATOR)
                || (PHP_OS_FAMILY === 'Windows' && preg_match('#^[A-Z]:#i', $path));
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => $path,
                '--realpath' => $realpath,
            ]);
            $output = trim(Artisan::output());
            if ($output) {
                $this->line($output);
            }
        }

        $this->newLine();
        $this->info("✅ Banco '{$database}' pronto para uso!");

        return self::SUCCESS;
    }

    protected function setupTenantConnection(string $database): void
    {
        $defaultConfig = config("database.connections.{$this->defaultConnection}");

        Config::set('database.connections.tenant', array_merge($defaultConfig, [
            'database' => $database,
        ]));

        DB::purge('tenant');
    }

    protected function databaseExists(string $database): bool
    {
        try {
            $driver = config("database.connections.{$this->defaultConnection}.driver");

            if ($driver === 'pgsql') {
                $result = DB::connection($this->defaultConnection)
                    ->select('SELECT 1 FROM pg_database WHERE datname = ?', [$database]);
            } else {
                $result = DB::connection($this->defaultConnection)
                    ->select('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$database]);
            }

            return count($result) > 0;

        } catch (\Exception $e) {
            return false;
        }
    }

    protected function createDatabase(string $database): void
    {
        $driver = config("database.connections.{$this->defaultConnection}.driver");
        $charset = config("database.connections.{$this->defaultConnection}.charset", 'utf8');

        if ($driver === 'pgsql') {
            DB::connection($this->defaultConnection)
                ->statement("CREATE DATABASE \"{$database}\" ENCODING '{$charset}'");
        } else {
            DB::connection($this->defaultConnection)
                ->statement("CREATE DATABASE `{$database}` CHARACTER SET {$charset}");
        }
    }
}
