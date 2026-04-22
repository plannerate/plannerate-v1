<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportLegacyClientCommand extends Command
{
    private const TENANT_IMPORT_CONNECTION = 'import_target_tenant';

    protected $signature = 'import:legacy-products
        {--dry-run : Apenas exibe contagens sem persistir}
        {--fresh : Trunca a tabela products no destino antes de importar}
        {--to= : Nome da conexao de destino (padrao: conexao default do .env)}
        {--tenant= : ULID, slug ou host (tenant_domains) do tenant: importa no banco definido em tenants.database}';

    protected $aliases = ['import:legacy-client', 'import:source-client'];

    protected $description = 'Importa a tabela products de mysql_legacy para o banco de destino local';

    private const TABLE = 'products';

    private mixed $legacy;

    private mixed $target;

    public function handle(): int
    {
        if (! $this->connectLegacy()) {
            return self::FAILURE;
        }

        $targetConnection = $this->resolveTargetConnectionName();
        if ($targetConnection === null) {
            return self::FAILURE;
        }

        if (! $this->connectTarget($targetConnection)) {
            return self::FAILURE;
        }

        if (! Schema::connection('mysql_legacy')->hasTable(self::TABLE)) {
            $this->error('Tabela products nao existe em mysql_legacy.');

            return self::FAILURE;
        }

        if (! Schema::connection($targetConnection)->hasTable(self::TABLE)) {
            $this->missingProductsTableMessage($targetConnection);

            return self::FAILURE;
        }

        $query = $this->legacy->table(self::TABLE);
        $remoteCount = (clone $query)->count();

        if ($this->option('dry-run')) {
            $localCount = $this->target->table(self::TABLE)->count();
            $this->info("products: origem (mysql_legacy)={$remoteCount}, destino [{$targetConnection}]={$localCount}");

            return self::SUCCESS;
        }

        if ($this->option('fresh')) {
            $this->target->table(self::TABLE)->truncate();
        }

        $targetColumns = Schema::connection($targetConnection)->getColumnListing(self::TABLE);
        $hasId = in_array('id', $targetColumns, true);
        $imported = 0;

        $query
            ->when($hasId, fn ($builder) => $builder->orderBy('id'))
            ->chunk(500, function ($records) use ($targetColumns, $hasId, &$imported): void {
                $rows = $records->map(fn ($row) => $this->prepareRow((array) $row, $targetColumns));

                if ($hasId && ! $this->option('fresh')) {
                    $existingIds = $this->target->table(self::TABLE)
                        ->whereIn('id', $rows->pluck('id'))
                        ->pluck('id')
                        ->toArray();

                    $rows = $rows->reject(fn ($row) => in_array($row['id'], $existingIds, true));
                }

                if ($rows->isNotEmpty()) {
                    $this->target->table(self::TABLE)->insertOrIgnore($rows->values()->toArray());
                    $imported += $rows->count();
                }
            });

        $this->info("products: {$imported} registros importados para [{$targetConnection}].");

        return self::SUCCESS;
    }

    private function resolveTargetConnectionName(): ?string
    {
        $tenantKey = $this->option('tenant');
        if ($tenantKey === null || $tenantKey === '') {
            return $this->option('to') ?: (string) config('database.default');
        }

        if ($this->option('to')) {
            $this->warn('Ignorando --to= porque --tenant= foi informado.');
        }

        $tenant = DB::connection('landlord')
            ->table('tenants')
            ->where('id', $tenantKey)
            ->orWhere('slug', $tenantKey)
            ->first();

        if ($tenant === null) {
            $tenant = DB::connection('landlord')
                ->table('tenants')
                ->join('tenant_domains', 'tenants.id', '=', 'tenant_domains.tenant_id')
                ->where('tenant_domains.host', $tenantKey)
                ->select('tenants.*')
                ->first();
        }

        if ($tenant === null) {
            $this->error("Tenant nao encontrado em landlord para [{$tenantKey}].");

            return null;
        }

        if ($tenant->database === null || $tenant->database === '') {
            $this->error("Tenant [{$tenant->name}] sem campo database preenchido no landlord.");

            return null;
        }

        $baseConnectionName = (string) config('database.default');
        $baseConfig = config("database.connections.{$baseConnectionName}");
        if (! is_array($baseConfig)) {
            $this->error("Config da conexao [{$baseConnectionName}] invalida.");

            return null;
        }

        Config::set('database.connections.'.self::TENANT_IMPORT_CONNECTION, array_merge($baseConfig, [
            'database' => $tenant->database,
        ]));
        DB::purge(self::TENANT_IMPORT_CONNECTION);

        $this->line("Destino: tenant {$tenant->name} (banco {$tenant->database}).");

        return self::TENANT_IMPORT_CONNECTION;
    }

    private function missingProductsTableMessage(string $targetConnection): void
    {
        $database = '';
        try {
            $database = (string) DB::connection($targetConnection)->getDatabaseName();
        } catch (\Throwable) {
            // ignorar
        }

        $this->error('Tabela products nao existe no destino.');
        $this->line("  Conexao: [{$targetConnection}]".($database !== '' ? ", base: [{$database}]" : ''));

        if ($this->option('tenant') === null || $this->option('tenant') === '') {
            $this->line('  Em apps multitenant, products costuma ficar no banco do tenant, nao no landlord.');
            $this->line('  Tente: php artisan import:legacy-products --tenant=SEU_SLUG_OU_ULID --dry-run');
        }

        $this->line('  Ou rode as migrations nessa base: php artisan migrate (ou tenants:artisan migrate conforme seu fluxo).');
    }

    private function connectLegacy(): bool
    {
        try {
            $this->legacy = DB::connection('mysql_legacy');
            $this->legacy->getPdo();
            $this->info('Conectado em mysql_legacy.');

            return true;
        } catch (\Throwable $exception) {
            $this->error('Falha ao conectar em mysql_legacy: '.$exception->getMessage());

            return false;
        }
    }

    private function connectTarget(string $connection): bool
    {
        try {
            $this->target = DB::connection($connection);
            $this->target->getPdo();
            $this->info("Destino: conexao [{$connection}].");

            return true;
        } catch (\Throwable $exception) {
            $this->error("Falha ao conectar na conexao [{$connection}]: ".$exception->getMessage());

            return false;
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function prepareRow(array $row, array $columns): array
    {
        $row = collect($row)->only($columns)->toArray();

        foreach (['created_at', 'updated_at', 'deleted_at', 'sync_at'] as $field) {
            if (isset($row[$field]) && str_starts_with((string) $row[$field], '0000')) {
                $row[$field] = null;
            }
        }

        foreach (['status', 'type'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = strtolower((string) $row[$field]);
            }
        }

        return $row;
    }
}
