<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\search;

class ImportLegacyBaseClientCommand extends Command
{
    protected $signature = 'import:source-client
        {tenant? : ULID ou slug do tenant destino (interativo se omitido)}
        {--table= : Tabela especifica para importar (se nao setada, importa todas as tabelas)}
        {--dry-run : Mostra o que seria importado sem realmente importar}
        {--fresh : Apaga os dados da tabela de destino antes de importar}';

    protected $aliases = ['import:legacy-client'];

    protected $description = 'Importa dados de um cliente da base legada para o banco de um tenant';

    private array $tables = [
        'stores',
        'clusters',
        // 'categories',
        // 'products',
        'planograms',
        'gondolas',
        'sections',
        'shelves',
        'segments',
        'layers',
        // 'sales',
        // 'purchases',
        // 'providers',
        // 'store_maps',
        // 'store_map_gondolas',
    ];

    private Connection $legacy;

    private Connection $tenantDb;

    private object $client;

    private Tenant $tenant;

    public function handle(): int
    {
        if (! $this->connectLegacy()) {
            return self::FAILURE;
        }

        $tenant = $this->resolveTenant();
        if (! $tenant) {
            return self::FAILURE;
        }
        $this->tenant = $tenant;

        if (! $this->setupTenantDatabase()) {
            return self::FAILURE;
        }

        $client = $this->resolveClientFromTenant();
        if (! $client) {
            return self::FAILURE;
        }
        $this->client = $client;

        $this->newLine();
        $this->info("🔄 Importando cliente: {$client->name} → tenant: {$tenant->name} ({$tenant->database})");
        $this->newLine();

        $this->resetCachedIds();

        $tables = $this->option('table') ? [$this->option('table')] : $this->tables;
        $results = [];

        foreach ($tables as $table) {
            $stats = $table === 'layers'
                ? $this->importLayers()
                : $this->importTable($table);

            if ($stats !== null) {
                $results[] = $stats;
            }
        }

        $this->newLine();

        if (! empty($results)) {
            $this->table(
                ['Tabela', 'Origem', 'Importados', 'Ignorados'],
                array_map(fn ($r) => [
                    $r['table'],
                    $r['total'],
                    $r['imported'] > 0 ? "<fg=green>{$r['imported']}</>" : '0',
                    $r['skipped'] > 0 ? "<fg=yellow>{$r['skipped']}</>" : '0',
                ], $results)
            );
        }

        $totalImported = array_sum(array_column($results, 'imported'));
        $this->newLine();
        $this->info("✅ Concluído! {$totalImported} registros importados.");

        return self::SUCCESS;
    }

    private function connectLegacy(): bool
    {
        try {
            $this->legacy = DB::connection('mysql_legacy');
            $this->legacy->getPdo();
            $this->info('✅ Conectado à base de origem (mysql_legacy)');

            return true;
        } catch (\Exception $e) {
            $this->error('❌ Falha na conexão com mysql_legacy: '.$e->getMessage());

            return false;
        }
    }

    private function resolveTenant(): ?Tenant
    {
        $filter = $this->argument('tenant') ?? search(
            label: 'Selecione o tenant de destino',
            options: fn (string $value) => Tenant::on('landlord')
                ->where(
                    fn ($q) => $q
                        ->where('name', 'like', "%{$value}%")
                        ->orWhere('slug', 'like', "%{$value}%")
                )
                ->pluck('name', 'id')
                ->toArray(),
            placeholder: 'Digite o nome ou slug...',
        );

        $tenant = Tenant::on('landlord')
            ->with('integration')
            ->where(fn ($q) => $q->where('id', $filter)->orWhere('slug', $filter))
            ->first();

        if (! $tenant) {
            $this->error("❌ Tenant não encontrado: {$filter}");

            return null;
        }

        if (empty($tenant->database)) {
            $this->error("❌ Tenant '{$tenant->name}' não possui database configurado");

            return null;
        }

        return $tenant;
    }

    private function setupTenantDatabase(): bool
    {
        // Usa landlord como base — tenant está no mesmo servidor, só muda o database
        $baseConfig = config('database.connections.landlord');

        if (empty($baseConfig)) {
            $this->error('❌ Connection [landlord] não encontrada em database.php');

            return false;
        }

        Config::set('database.connections.tenant_import', array_merge($baseConfig, [
            'database' => $this->tenant->database,
        ]));

        DB::purge('tenant_import');

        try {
            $this->tenantDb = DB::connection('tenant_import');
            $this->tenantDb->getPdo();
            $this->info("✅ Banco do tenant: {$this->tenant->database}");

            return true;
        } catch (\Exception $e) {
            $this->error("❌ Não foi possível conectar a '{$this->tenant->database}': ".$e->getMessage());

            return false;
        }
    }

    private function resolveClientFromTenant(): ?object
    {

        $client = $this->legacy->table('clients')->where('id', $this->tenant->id)->first();

        if (! $client) {
            $this->error("❌ Cliente não encontrado na base legada com ID: {$this->tenant->id}");

            return null;
        }

        return $client;
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

    /** @return array{table: string, total: int, imported: int, skipped: int}|null */
    private function importTable(string $table): ?array
    {
        if (! Schema::connection('mysql_legacy')->hasTable($table)) {
            return null;
        }

        if (! Schema::connection('tenant_import')->hasTable($table)) {
            $this->warn("  ⚠️  {$table}: tabela não encontrada no banco do tenant");

            return null;
        }

        $query = $this->buildQuery($table);
        $remoteCount = (clone $query)->count();

        if ($this->option('dry-run')) {
            $localCount = $this->tenantDb->table($table)->count();
            $this->line("  📊 <fg=cyan>{$table}</>: {$remoteCount} na origem, {$localCount} no destino");

            return ['table' => $table, 'total' => $remoteCount, 'imported' => 0, 'skipped' => 0];
        }

        if ($remoteCount === 0) {
            $this->line("  <fg=gray>–  {$table}: sem registros</>");

            return null;
        }

        $this->line("  <fg=cyan>↓  {$table}</>: {$remoteCount} registros encontrados");

        if ($this->option('fresh')) {
            $this->tenantDb->table($table)->truncate();
        }

        $targetColumns = Schema::connection('tenant_import')->getColumnListing($table);
        $hasId = in_array('id', $targetColumns);
        $imported = 0;
        $skipped = 0;

        $this->output->progressStart($remoteCount);

        $query->when($hasId, fn ($q) => $q->orderBy('id'))
            ->chunk(500, function ($records) use ($table, $targetColumns, $hasId, &$imported, &$skipped) {
                $rows = $records->map(fn ($r) => $this->prepareRow((array) $r, $targetColumns));

                if ($hasId && ! $this->option('fresh')) {
                    $existingIds = $this->tenantDb->table($table)
                        ->whereIn('id', $rows->pluck('id'))
                        ->pluck('id')
                        ->toArray();

                    $skipped += count($existingIds);
                    $rows = $rows->filter(fn ($r) => ! in_array($r['id'], $existingIds));
                }

                if ($rows->isNotEmpty()) {
                    $this->tenantDb->table($table)->insertOrIgnore($rows->values()->toArray());
                    $imported += $rows->count();
                }

                $this->output->progressAdvance($records->count());
            });

        $this->output->progressFinish();

        return ['table' => $table, 'total' => $remoteCount, 'imported' => $imported, 'skipped' => $skipped];
    }

    private function buildQuery(string $table)
    {
        $query = $this->legacy->table($table);
        $clientId = $this->client->id;

        return match ($table) {
            'planograms', 'sales', 'purchases' => $query->where('client_id', $clientId),

            'providers' => $query,

            'gondolas' => $query->whereIn('planogram_id', $this->getPlanogramIds()),

            'store_maps' => $query->whereIn('store_id', $this->getStoreIds()),

            'store_map_gondolas' => $query->whereIn('store_map_id', $this->getStoreMapIds()),

            'sections' => $query->whereIn('gondola_id', $this->getGondolaIds()),

            'shelves' => $query->whereIn('section_id', $this->getSectionIds()),

            'segments' => $query->whereIn('shelf_id', $this->getShelfIds()),

            'layers' => $query
                ->whereIn('layers.segment_id', $this->getSegmentIds())
                ->leftJoin('products as lp', 'lp.id', '=', 'layers.product_id')
                ->select([
                    'layers.*',
                    DB::raw('lp.ean as ean'),
                ]),

            'stores' => $query->where('client_id', $this->client->id),

            'clusters' => $query->where('client_id', $this->client->id)
                ->whereNotNull('store_id'),

            default => $query,
        };
    }

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

    /** @return array{table: string, total: int, imported: int, skipped: int}|null */
    private function importLayers(): ?array
    {
        $table = 'layers';

        if (! Schema::connection('mysql_legacy')->hasTable($table)) {
            return null;
        }

        if (! Schema::connection('tenant_import')->hasTable($table)) {
            $this->warn("  ⚠️  {$table}: tabela não encontrada no banco do tenant");

            return null;
        }

        $query = $this->buildQuery($table);
        $remoteCount = (clone $query)->count();

        if ($this->option('dry-run')) {
            $localCount = $this->tenantDb->table($table)->count();
            $this->line("  📊 <fg=cyan>{$table}</>: {$remoteCount} na origem, {$localCount} no destino");

            return ['table' => $table, 'total' => $remoteCount, 'imported' => 0, 'skipped' => 0];
        }

        if ($remoteCount === 0) {
            $this->line("  <fg=gray>–  {$table}: sem registros</>");

            return null;
        }

        $this->line("  <fg=cyan>↓  {$table}</>: {$remoteCount} registros encontrados");

        if ($this->option('fresh')) {
            $this->tenantDb->table($table)->truncate();
        }
        $this->tenantDb->table($table)->truncate();

        $targetColumns = Schema::connection('tenant_import')->getColumnListing($table);
        $hasEan = in_array('ean', $targetColumns);
        $hasGondolaId = in_array('gondola_id', $targetColumns);
        $imported = 0;
        $skipped = 0;

        $this->output->progressStart($remoteCount);

        $generator = app(DeterministicIdGenerator::class);
        $tenantId = (string) $this->client->id;

        // Monta mapa segment_id → gondola_id uma única vez antes dos chunks
        $gondolaBySegment = $this->legacy
            ->table('segments as sg')
            ->join('shelves as sh', 'sh.id', '=', 'sg.shelf_id')
            ->join('sections as sc', 'sc.id', '=', 'sh.section_id')
            ->whereIn('sg.id', $this->getSegmentIds())
            ->whereNotNull('sc.gondola_id')
            ->pluck('sc.gondola_id', 'sg.id')
            ->all();

        $query->orderBy('layers.id')
            ->chunk(500, function ($records) use ($table, $targetColumns, $hasEan, $hasGondolaId, $generator, $tenantId, $gondolaBySegment, &$imported, &$skipped): void {
                $rows = $records->map(function ($record) use ($targetColumns, $hasEan, $hasGondolaId, $generator, $tenantId, $gondolaBySegment): array {
                    $row = $this->prepareRow((array) $record, $targetColumns);

                    $ean = is_string($record->ean ?? null) ? trim($record->ean) : '';

                    // Gera product_id deterministico via EAN (sem consultar o banco)
                    $row['product_id'] = $ean !== ''
                        ? $generator->productId($tenantId, $ean, null)
                        : null;

                    // Salva o ean na layer se a coluna existir
                    if ($hasEan && $ean !== '') {
                        $row['ean'] = $ean;
                    }

                    // Preenche gondola_id via mapa segment_id → gondola_id
                    if ($hasGondolaId) {
                        $segmentId = is_string($record->segment_id ?? null) ? $record->segment_id : '';
                        $row['gondola_id'] = $gondolaBySegment[$segmentId] ?? null;
                    }

                    return $row;
                });

                $existingIds = $this->tenantDb->table($table)
                    ->whereIn('id', $rows->pluck('id'))
                    ->pluck('id')
                    ->toArray();

                $skipped += count($existingIds);
                $rows = $rows->filter(fn ($r) => ! in_array($r['id'], $existingIds));

                if ($rows->isNotEmpty()) {
                    $this->tenantDb->table($table)->insertOrIgnore($rows->values()->toArray());
                    $imported += $rows->count();
                }

                $this->output->progressAdvance($records->count());
            });

        $this->output->progressFinish();

        return ['table' => $table, 'total' => $remoteCount, 'imported' => $imported, 'skipped' => $skipped];
    }

    private function prepareRow(array $row, array $columns): array
    {
        // Garante tenant_id correto em todas as tabelas que o possuem no destino
        // tenant_id = client_id da base legada
        if (in_array('tenant_id', $columns)) {
            $row['tenant_id'] = $this->client->id;
        }

        // Filter to only destination columns
        $row = collect($row)->only($columns)->toArray();

        // Fix invalid dates
        foreach (['created_at', 'updated_at', 'deleted_at', 'sync_at'] as $field) {
            if (isset($row[$field]) && str_starts_with((string) $row[$field], '0000')) {
                $row[$field] = null;
            }
        }

        // Normalize status/type enum fields
        foreach (['status', 'type'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = strtolower($row[$field]);
            }
        }

        return $row;
    }
}
