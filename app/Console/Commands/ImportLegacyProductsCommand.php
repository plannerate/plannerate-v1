<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\multiselect;

// TODO: Este comando importa produtos para a base de dados individual de cada tenant.
// No futuro, pode ser adaptado para apontar a uma base de produtos compartilhada
// entre tenants, ou ser eliminado se a sincronização via API tornar-se o padrão único.
class ImportLegacyProductsCommand extends Command
{
    protected $signature = 'import:legacy-products
        {--tenant=* : ID ou slug dos tenants destino (pode repetir ou separar por vírgula)}
        {--dry-run : Mostra o que seria importado sem realmente importar}
        {--fresh : Apaga os dados das tabelas antes de importar}
        {--skip-categories : Pula a importação de categorias}';

    protected $description = 'Importa produtos (e categorias) da base legada para um ou mais tenants';

    private Connection $legacy;

    public function handle(DeterministicIdGenerator $generator): int
    {
        if (! $this->connectLegacy()) {
            return self::FAILURE;
        }

        $tenants = $this->resolveTenants();

        if (empty($tenants)) {
            $this->warn('Nenhum tenant selecionado.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info(sprintf('🏢 %d tenant(s) selecionado(s).', count($tenants)));

        $results = [];

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->line('═══════════════════════════════════════════════════════');
            $this->info("🔄 {$tenant->name} ({$tenant->database})");

            $client = $this->legacy->table('clients')->where('id', $tenant->id)->first();

            if (! $client) {
                $this->warn("  ⚠️  Cliente não encontrado na base legada para o tenant: {$tenant->name}");

                continue;
            }

            if (! $this->setupTenantDatabase($tenant)) {
                continue;
            }

            $tenantId = (string) $tenant->id;
            $categoriesStats = null;

            if (! $this->option('skip-categories')) {
                $categoriesStats = $this->importCategories($tenantId);
            }

            $productsStats = $this->importProducts($client, $tenantId, $generator);

            $results[] = [
                'tenant' => $tenant->name,
                'categories' => $categoriesStats,
                'products' => $productsStats,
            ];

            DB::purge('tenant_import');
        }

        $this->newLine();
        $this->table(
            ['Tenant', 'Categorias (orig/imp/ign)', 'Produtos (orig/imp/ign)'],
            array_map(fn ($r) => [
                $r['tenant'],
                $r['categories']
                    ? "{$r['categories']['total']} / {$r['categories']['imported']} / {$r['categories']['skipped']}"
                    : '—',
                $r['products']
                    ? "{$r['products']['total']} / {$r['products']['imported']} / {$r['products']['skipped']}"
                    : '—',
            ], $results)
        );

        $this->newLine();
        $this->info('✅ Concluído!');

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

    /** @return list<Tenant> */
    private function resolveTenants(): array
    {
        $tenantOptions = $this->option('tenant');

        if (! empty($tenantOptions)) {
            $ids = collect($tenantOptions)
                ->flatMap(fn (string $v) => explode(',', $v))
                ->map(fn (string $v) => trim($v))
                ->filter()
                ->values()
                ->toArray();

            return Tenant::on('landlord')
                ->where(fn ($q) => $q->whereIn('id', $ids)->orWhereIn('slug', $ids))
                ->get()
                ->all();
        }

        $all = Tenant::on('landlord')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'database']);

        if ($all->isEmpty()) {
            $this->warn('Nenhum tenant ativo encontrado.');

            return [];
        }

        $selected = multiselect(
            label: 'Selecione os tenants para importar produtos',
            options: $all->pluck('name', 'id')->toArray(),
            hint: 'Use espaço para selecionar, enter para confirmar',
        );

        return $all->whereIn('id', $selected)->values()->all();
    }

    private function setupTenantDatabase(Tenant $tenant): bool
    {
        $baseConfig = config('database.connections.landlord');

        if (empty($baseConfig)) {
            $this->error('❌ Connection [landlord] não encontrada em database.php');

            return false;
        }

        Config::set('database.connections.tenant_import', array_merge($baseConfig, [
            'database' => $tenant->database,
        ]));

        DB::purge('tenant_import');

        try {
            DB::connection('tenant_import')->getPdo();
            $this->line("  ✅ Banco: {$tenant->database}");

            return true;
        } catch (\Exception $e) {
            $this->error("  ❌ Não foi possível conectar a '{$tenant->database}': ".$e->getMessage());

            return false;
        }
    }

    /** @return array{total: int, imported: int, skipped: int} */
    private function importCategories(string $tenantId): array
    {
        $table = 'categories';

        if (! Schema::connection('mysql_legacy')->hasTable($table)) {
            $this->warn("  ⚠️  {$table}: tabela não encontrada na base legada");

            return ['total' => 0, 'imported' => 0, 'skipped' => 0];
        }

        if (! Schema::connection('tenant_import')->hasTable($table)) {
            $this->warn("  ⚠️  {$table}: tabela não encontrada no banco do tenant");

            return ['total' => 0, 'imported' => 0, 'skipped' => 0];
        }

        $query = $this->legacy->table($table);
        $total = (clone $query)->count();

        if ($this->option('dry-run')) {
            $localCount = DB::connection('tenant_import')->table($table)->count();
            $this->line("  📊 <fg=cyan>{$table}</>: {$total} na origem, {$localCount} no destino");

            return ['total' => $total, 'imported' => 0, 'skipped' => 0];
        }

        if ($total === 0) {
            $this->line("  <fg=gray>–  {$table}: sem registros</>");

            return ['total' => 0, 'imported' => 0, 'skipped' => 0];
        }

        $this->line("  <fg=cyan>↓  {$table}</>: {$total} registros encontrados");

        if ($this->option('fresh')) {
            DB::connection('tenant_import')->table($table)->truncate();
        }

        $targetColumns = Schema::connection('tenant_import')->getColumnListing($table);
        $imported = 0;
        $skipped = 0;

        $this->output->progressStart($total);

        $query->orderBy('id')->chunk(500, function ($records) use ($table, $targetColumns, $tenantId, &$imported, &$skipped): void {
            $rows = $records->map(fn ($r) => $this->prepareRow((array) $r, $targetColumns, $tenantId));

            $existingIds = DB::connection('tenant_import')
                ->table($table)
                ->whereIn('id', $rows->pluck('id'))
                ->pluck('id')
                ->toArray();

            $skipped += count($existingIds);
            $rows = $rows->filter(fn ($r) => ! in_array($r['id'], $existingIds));

            if ($rows->isNotEmpty()) {
                DB::connection('tenant_import')->table($table)->insertOrIgnore($rows->values()->toArray());
                $imported += $rows->count();
            }

            $this->output->progressAdvance($records->count());
        });

        $this->output->progressFinish();

        return ['total' => $total, 'imported' => $imported, 'skipped' => $skipped];
    }

    /** @return array{total: int, imported: int, skipped: int} */
    private function importProducts(object $client, string $tenantId, DeterministicIdGenerator $generator): array
    {
        $table = 'products';
        $clientId = $client->id;

        if (! Schema::connection('mysql_legacy')->hasTable($table)) {
            $this->warn("  ⚠️  {$table}: tabela não encontrada na base legada");

            return ['total' => 0, 'imported' => 0, 'skipped' => 0];
        }

        if (! Schema::connection('tenant_import')->hasTable($table)) {
            $this->warn("  ⚠️  {$table}: tabela não encontrada no banco do tenant");

            return ['total' => 0, 'imported' => 0, 'skipped' => 0];
        }

        $query = $this->legacy->table('products')
            ->join('client_product', 'products.id', '=', 'client_product.product_id')
            ->leftJoin('dimensions', 'products.id', '=', 'dimensions.product_id')
            ->leftJoin('product_additional_data', function ($join) use ($clientId): void {
                $join->on('products.id', '=', 'product_additional_data.product_id')
                    ->where('product_additional_data.client_id', $clientId);
            })
            ->where('client_product.client_id', $clientId)
            ->select([
                'products.*',
                'dimensions.width',
                'dimensions.height',
                'dimensions.depth',
                'dimensions.weight',
                'dimensions.unit',
                'dimensions.status as dimensions_status',
                'dimensions.description as dimensions_description',
                'product_additional_data.type',
                'product_additional_data.reference',
                'product_additional_data.fragrance',
                'product_additional_data.flavor',
                'product_additional_data.color',
                'product_additional_data.brand',
                'product_additional_data.subbrand',
                'product_additional_data.packaging_type',
                'product_additional_data.packaging_size',
                'product_additional_data.measurement_unit',
                'product_additional_data.packaging_content',
                'product_additional_data.unit_measure',
                'product_additional_data.auxiliary_description',
                'product_additional_data.additional_information',
                'product_additional_data.sortiment_attribute',
            ]);

        $total = (clone $query)->count();

        if ($this->option('dry-run')) {
            $localCount = DB::connection('tenant_import')->table($table)->count();
            $this->line("  📊 <fg=cyan>{$table}</>: {$total} na origem, {$localCount} no destino");

            return ['total' => $total, 'imported' => 0, 'skipped' => 0];
        }

        if ($total === 0) {
            $this->line("  <fg=gray>–  {$table}: sem registros</>");

            return ['total' => 0, 'imported' => 0, 'skipped' => 0];
        }

        $this->line("  <fg=cyan>↓  {$table}</>: {$total} registros encontrados");

        if ($this->option('fresh')) {
            DB::connection('tenant_import')->table($table)->truncate();
        }

        $targetColumns = Schema::connection('tenant_import')->getColumnListing($table);
        $imported = 0;
        $skipped = 0;

        $this->output->progressStart($total);

        $query->orderBy('products.id')->chunk(500, function ($records) use ($table, $targetColumns, $tenantId, $generator, &$imported, &$skipped): void {
            $rows = $records->map(function ($record) use ($targetColumns, $tenantId, $generator): array {
                $row = $this->prepareRow((array) $record, $targetColumns, $tenantId);

                $ean = isset($record->ean) && is_string($record->ean) && trim($record->ean) !== ''
                    ? trim($record->ean)
                    : null;

                $codigoErp = isset($record->codigo_erp) && is_string($record->codigo_erp) && trim($record->codigo_erp) !== ''
                    ? trim($record->codigo_erp)
                    : null;

                $row['id'] = $generator->productId($tenantId, $ean, $codigoErp);

                return $row;
            });

            $existingIds = DB::connection('tenant_import')
                ->table($table)
                ->whereIn('id', $rows->pluck('id'))
                ->pluck('id')
                ->toArray();

            $skipped += count($existingIds);
            $rows = $rows->filter(fn ($r) => ! in_array($r['id'], $existingIds));

            if ($rows->isNotEmpty()) {
                DB::connection('tenant_import')->table($table)->insertOrIgnore($rows->values()->toArray());
                $imported += $rows->count();
            }

            $this->output->progressAdvance($records->count());
        });

        $this->output->progressFinish();

        return ['total' => $total, 'imported' => $imported, 'skipped' => $skipped];
    }

    /** @return array<string, mixed> */
    private function prepareRow(array $row, array $columns, string $tenantId): array
    {
        if (in_array('tenant_id', $columns)) {
            $row['tenant_id'] = $tenantId;
        }

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
