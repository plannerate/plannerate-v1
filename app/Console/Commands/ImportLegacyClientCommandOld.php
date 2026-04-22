<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportLegacyClientCommandOld extends Command
{
    protected $signature = 'import:source-client-old
        {client? : Client ID or slug}
        {--table= : Import only specific table}
        {--dry-run : Show what would be imported}
        {--fresh : Truncate tables before import}
        {--create-db : Create database if not exists}';

    protected $aliases = ['import:legacy-client-old'];

    protected $description = 'Importa tabelas especificas do cliente a partir da base de origem';

    private array $tables = [
        // 'categories',
        'products',
        // 'planograms',
        // 'gondolas',
        // 'sections',
        // 'shelves',
        // 'segments',
        // 'layers',
        // 'sales',
        // 'purchases',
        // 'providers',
        // 'store_maps',
        // 'store_map_gondolas',
    ];

    private $legacy;

    private $client;

    private $clientDb;

    public function handle(): int
    {
        if (! $this->connectLegacy()) {
            return self::FAILURE;
        }

        $clients = $this->getClients();
        if ($clients->isEmpty()) {
            $this->error('❌ No clients found');

            return self::FAILURE;
        }

        foreach ($clients as $client) {
            $this->importClientData($client);
        }

        $this->newLine();
        $this->info('✅ All imports completed!');

        return self::SUCCESS;
    }

    private function connectLegacy(): bool
    {
        try {
            $this->legacy = DB::connection('mysql_legacy');
            $this->legacy->getPdo();
            $this->info('✅ Connected to source database');

            return true;
        } catch (\Exception $e) {
            $this->error('❌ Connection failed: '.$e->getMessage());

            return false;
        }
    }

    private function getClients()
    {
        $query = DB::table('clients')->where('status', 'published');

        if ($filter = $this->argument('client')) {
            $query->where(fn ($q) => $q->where('id', $filter)->orWhere('slug', $filter));
        }

        return $query->get();
    }

    private function importClientData(object $client): void
    {
        $this->newLine();
        $this->info("🔄 Client: {$client->name}");

        if (! $this->setupClientDatabase($client)) {
            return;
        }

        $this->client = $client;
        $this->resetCachedIds(); // Limpa cache de IDs do client anterior

        $tables = $this->option('table') ? [$this->option('table')] : $this->tables;

        foreach ($tables as $table) {
            $this->importTable($table);
        }
    }

    private function resetCachedIds(): void
    {
        $this->planogramIds = null;
        $this->gondolaIds = null;
        $this->sectionIds = null;
        $this->shelfIds = null;
        $this->segmentIds = null;
        $this->storeIds = null;
        $this->storeMapIds = null;
    }

    private function setupClientDatabase(object $client): bool
    {
        if (empty($client->database)) {
            if ($this->option('create-db')) {
                $dbName = 'plannerate_'.Str::slug($client->name, '_');
                $this->createDatabase($dbName);
            } else {
                $this->warn('  ⚠️  No database configured. Use --create-db to create one.');

                return false;
            }

            // Atualiza o cliente com o nome do banco
            DB::table('clients')->where('id', $client->id)->update(['database' => $dbName]);
            $client->database = $dbName;
        }

        // Setup connection
        $defaultConfig = config('database.connections.'.config('database.default'));
        Config::set('database.connections.client_db', array_merge($defaultConfig, [
            'database' => $client->database,
        ]));

        DB::purge('client_db');

        try {
            $this->clientDb = DB::connection('client_db');
            $this->clientDb->getPdo();

            return true;
        } catch (\Exception $e) {
            $this->error("  ❌ Cannot connect to {$client->database}: ".$e->getMessage());

            return false;
        }
    }

    private function createDatabase(string $name): void
    {
        $driver = config('database.default');

        if (config("database.connections.{$driver}.driver") === 'pgsql') {
            DB::statement("CREATE DATABASE \"{$name}\"");
        } else {
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$name}`");
        }

        $this->info("  ✅ Database {$name} created");

        // Run migrations
        $this->call('tenant:migrate', ['--force' => true]);
    }

    private function importTable(string $table): void
    {
        if (! Schema::connection('mysql_legacy')->hasTable($table)) {
            return;
        }

        if (! Schema::connection('client_db')->hasTable($table)) {
            $this->warn("  ⚠️  {$table}: not found in client DB");

            return;
        }

        $query = $this->buildQuery($table);
        $remoteCount = (clone $query)->count();

        if ($this->option('dry-run')) {
            $localCount = $this->clientDb->table($table)->count();
            $this->line("  📊 {$table}: {$remoteCount} remote, {$localCount} local");

            return;
        }

        if ($this->option('fresh')) {
            $this->clientDb->table($table)->truncate();
        }

        $targetColumns = Schema::connection('client_db')->getColumnListing($table);
        $hasId = in_array('id', $targetColumns);
        $imported = 0;

        $query->when($hasId, fn ($q) => $q->orderBy('id'))
            ->chunk(500, function ($records) use ($table, $targetColumns, $hasId, &$imported) {
                $rows = $records->map(fn ($r) => $this->prepareRow((array) $r, $table, $targetColumns));

                if ($hasId && ! $this->option('fresh')) {
                    $existingIds = $this->clientDb->table($table)
                        ->whereIn('id', $rows->pluck('id'))
                        ->pluck('id')
                        ->toArray();

                    $rows = $rows->filter(fn ($r) => ! in_array($r['id'], $existingIds));
                }

                if ($rows->isNotEmpty()) {
                    $this->clientDb->table($table)->insertOrIgnore($rows->values()->toArray());
                    $imported += $rows->count();
                }
            });

        if ($imported > 0) {
            $this->info("  ✅ {$table}: {$imported} imported");
        }
    }

    private function buildQuery(string $table)
    {
        $query = $this->legacy->table($table);
        $clientId = $this->client->id;

        return match ($table) {
            // Tables with direct client_id
            'planograms', 'sales', 'purchases' => $query->where('client_id', $clientId),

            // Categories/Providers: global per tenant (no client_id), import all
            'categories', 'providers' => $query,

            // Products: get from pivot table client_product and add client_id
            'products' => $this->legacy->table('products')
                ->join('client_product', 'products.id', '=', 'client_product.product_id')
                ->where('client_product.client_id', $clientId)
                ->select('products.*'),

            // Gondolas: via planograms
            'gondolas' => $query->whereIn('planogram_id', $this->getPlanogramIds()),

            // Store maps: via stores
            'store_maps' => $query->whereIn('store_id', $this->getStoreIds()),

            // Store map gondolas: via store_maps
            'store_map_gondolas' => $query->whereIn('store_map_id', $this->getStoreMapIds()),

            // Sections: via gondolas
            'sections' => $query->whereIn('gondola_id', $this->getGondolaIds()),

            // Shelves: via sections
            'shelves' => $query->whereIn('section_id', $this->getSectionIds()),

            // Segments: via shelves
            'segments' => $query->whereIn('shelf_id', $this->getShelfIds()),

            // Layers: via segments
            'layers' => $query->whereIn('segment_id', $this->getSegmentIds()),

            default => $query,
        };
    }

    // Cached ID getters for hierarchical queries
    private ?array $planogramIds = null;

    private ?array $gondolaIds = null;

    private ?array $sectionIds = null;

    private ?array $shelfIds = null;

    private ?array $segmentIds = null;

    private ?array $storeIds = null;

    private ?array $storeMapIds = null;

    private function getPlanogramIds(): array
    {
        return $this->planogramIds ??= $this->legacy->table('planograms')
            ->where('client_id', $this->client->id)
            ->pluck('id')
            ->toArray();
    }

    private function getGondolaIds(): array
    {
        return $this->gondolaIds ??= $this->legacy->table('gondolas')
            ->whereIn('planogram_id', $this->getPlanogramIds())
            ->pluck('id')
            ->toArray();
    }

    private function getSectionIds(): array
    {
        return $this->sectionIds ??= $this->legacy->table('sections')
            ->whereIn('gondola_id', $this->getGondolaIds())
            ->pluck('id')
            ->toArray();
    }

    private function getShelfIds(): array
    {
        return $this->shelfIds ??= $this->legacy->table('shelves')
            ->whereIn('section_id', $this->getSectionIds())
            ->pluck('id')
            ->toArray();
    }

    private function getSegmentIds(): array
    {
        return $this->segmentIds ??= $this->legacy->table('segments')
            ->whereIn('shelf_id', $this->getShelfIds())
            ->pluck('id')
            ->toArray();
    }

    private function getStoreIds(): array
    {
        return $this->storeIds ??= $this->legacy->table('stores')
            ->where('client_id', $this->client->id)
            ->pluck('id')
            ->toArray();
    }

    private function getStoreMapIds(): array
    {
        return $this->storeMapIds ??= $this->legacy->table('store_maps')
            ->whereIn('store_id', $this->getStoreIds())
            ->pluck('id')
            ->toArray();
    }

    private function prepareRow(array $row, string $table, array $columns): array
    {
        // Add client_id to products (source schema uses pivot table)
        if ($table === 'products') {
            $row['client_id'] = $this->client->id;
        }

        // Filter only existing columns
        $row = collect($row)->only($columns)->toArray();

        // Fix invalid dates
        foreach (['created_at', 'updated_at', 'deleted_at', 'sync_at'] as $field) {
            if (isset($row[$field]) && str_starts_with((string) $row[$field], '0000')) {
                $row[$field] = null;
            }
        }

        // Normalize status/type fields
        foreach (['status', 'type'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = strtolower($row[$field]);
            }
        }

        return $row;
    }
}
