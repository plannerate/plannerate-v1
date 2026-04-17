<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportLegacyMainCommand extends Command
{
    protected $signature = 'import:source-main
        {--table= : Import only specific table}
        {--dry-run : Show what would be imported}
        {--fresh : Truncate tables before import}';

    protected $aliases = ['import:legacy-main'];

    protected $description = 'Importa tabelas-base da base de origem para o banco principal';

    private array $tables = [
        'tenants',
        'users',
        'clients',
        'client_integrations',
        'stores',
        'clusters',
        'addresses',
        'roles',
        'permissions',
        'permission_role',
        'permission_user',
        'role_user',
    ];

    private $legacy;

    private $target;

    public function handle(): int
    {
        if (! $this->connectLegacy()) {
            return self::FAILURE;
        }

        $this->target = DB::connection(config('database.default'));
        $tables = $this->option('table') ? [$this->option('table')] : $this->tables;

        $this->info('🚀 Importing main database tables...');
        $this->newLine();

        foreach ($tables as $table) {
            $this->importTable($table);
        }

        $this->newLine();
        $this->info('✅ Import completed!');

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

    private function importTable(string $table): void
    {
        if (! Schema::connection('mysql_legacy')->hasTable($table)) {
            $this->warn("⚠️  {$table}: not found in source database");

            return;
        }

        if (! Schema::hasTable($table)) {
            $this->warn("⚠️  {$table}: not found locally");

            return;
        }

        $remoteCount = $this->legacy->table($table)->count();

        if ($this->option('dry-run')) {
            $localCount = $this->target->table($table)->count();
            $this->line("📊 {$table}: {$remoteCount} remote, {$localCount} local");

            return;
        }

        if ($this->option('fresh')) {
            $this->target->table($table)->truncate();
        }

        $targetColumns = Schema::getColumnListing($table);
        $hasId = in_array('id', $targetColumns);
        $imported = 0;

        // Para tabelas pivot sem ID, importar tudo de uma vez
        if (! $hasId) {
            $rows = $this->legacy->table($table)->get()
                ->map(fn ($r) => $this->prepareRow((array) $r, $targetColumns))
                ->toArray();

            if (! empty($rows)) {
                $this->target->table($table)->insertOrIgnore($rows);
                $imported = count($rows);
            }

            $this->info("✅ {$table}: {$imported} imported");

            return;
        }

        $this->legacy->table($table)
            ->orderBy('id')
            ->chunk(500, function ($records) use ($table, $targetColumns, &$imported) {
                $rows = $records->map(fn ($r) => $this->prepareRow((array) $r, $targetColumns));

                if (! $this->option('fresh')) {
                    $existingIds = $this->target->table($table)
                        ->whereIn('id', $rows->pluck('id'))
                        ->pluck('id')
                        ->toArray();

                    $rows = $rows->filter(fn ($r) => ! in_array($r['id'], $existingIds));
                }

                if ($rows->isNotEmpty()) {
                    $this->target->table($table)->insertOrIgnore($rows->values()->toArray());
                    $imported += $rows->count();
                }
            });

        $this->info("✅ {$table}: {$imported} imported");
    }

    private function prepareRow(array $row, array $columns): array
    {
        $row = collect($row)->only($columns)->toArray();

        // Fix invalid dates
        foreach (['created_at', 'updated_at', 'deleted_at'] as $field) {
            if (isset($row[$field]) && str_starts_with($row[$field], '0000')) {
                $row[$field] = null;
            }
        }

        return $row;
    }
}
