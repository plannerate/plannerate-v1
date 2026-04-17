<?php

namespace App\Console\Commands;
 
use Illuminate\Support\Facades\DB;
use Callcocam\LaravelRaptor\Models\Tenant;
use Illuminate\Console\Command;

class CreateTenantDatabasesCommand extends Command
{
    protected $signature = 'raptor:create-tenant-databases';
    protected $description = 'Create databases for all tenants using superuser privileges';

    public function handle(): int
    {
        $this->info('🚀 Creating tenant databases...');

        $clients = Tenant::select('id', 'name', 'database')->get();

        if ($clients->isEmpty()) {
            $this->warn('No tenants found in the system.');
            return 0;
        }

        // Check if superuser environment variables are explicitly configured
        $hasSuperuserConfig = !empty(env('DB_SUPERUSER_USERNAME')) || !empty(env('DB_SUPERUSER_PASSWORD'));
        
        if ($hasSuperuserConfig) {
            $superuserConfig = [
                'host' => config('database.connections.pgsql.host'),
                'port' => config('database.connections.pgsql.port'),
                'username' => env('DB_SUPERUSER_USERNAME', 'postgres'),
                'password' => env('DB_SUPERUSER_PASSWORD', config('database.connections.pgsql.password')),
            ];
            $this->line("   Using superuser credentials: {$superuserConfig['username']}");
        } else {
            $superuserConfig = [
                'host' => config('database.connections.pgsql.host'),
                'port' => config('database.connections.pgsql.port'),
                'username' => config('database.connections.pgsql.username'),
                'password' => config('database.connections.pgsql.password'),
            ];
            $this->line("   Using regular DB credentials: {$superuserConfig['username']}");
        }

        // Use the main database instead of 'postgres'
        $mainDatabase = config('database.connections.pgsql.database');

        foreach ($clients as $client) {
            $databaseName = $client->database;
            
            // Skip if database name is empty
            if (empty($databaseName)) {
                $this->warn("   ⚠️  Skipping {$client->name}: database name is empty");
                continue;
            }

            $this->info("📦 Processing: {$client->name} (DB: {$databaseName})");

            try {
                // Connect to main database instead of 'postgres'
                $dsn = "pgsql:host={$superuserConfig['host']};port={$superuserConfig['port']};dbname={$mainDatabase}";
                $pdo = new \PDO($dsn, $superuserConfig['username'], $superuserConfig['password']);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                // Check if database exists
                $stmt = $pdo->prepare("SELECT 1 FROM pg_database WHERE datname = ?");
                $stmt->execute([$databaseName]);
                
                if ($stmt->fetch()) {
                    $this->line("   ✅ Database already exists: {$databaseName}");
                } else {
                    // Create database
                    $pdo->exec("CREATE DATABASE \"{$databaseName}\"");
                    $this->line("   ✅ Created database: {$databaseName}");
                }

            } catch (\Exception $e) {
                $this->error("   ❌ Error processing {$databaseName}: " . $e->getMessage());
                continue; // Continue to next tenant instead of returning
            }
        }

        $this->info('🎉 All tenant databases processed successfully!');
        return 0;
    }
} 