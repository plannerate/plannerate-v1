<?php

namespace App\Console\Commands\Integrations;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class IntegrationStatusCommand extends Command
{
    protected $signature = 'integration:status
        {--tenant= : ID ou slug do tenant. Informado, o comando roda sem prompts (cron, CI, exec -T)}';

    protected $description = 'Inspeciona e gerencia dados das tabelas de integração por tenant';

    public function handle(): int
    {
        // 1 — seleciona tenant
        $tenant = $this->pickTenant();

        if ($tenant === null) {
            return self::SUCCESS;
        }

        // 2 — ação: visualizar ou excluir. Com --tenant o comando é de
        // diagnóstico e roda sem prompt algum; exclusão continua exigindo a
        // sessão interativa, com seus pickers de loja/tabela e a confirmação.
        $action = $this->runsWithoutPrompts()
            ? 'view'
            : select(
                label: 'O que deseja fazer?',
                options: [
                    'view' => 'Visualizar stats + amostra de dados',
                    'delete' => 'Excluir registros (filtra por loja e tabela)',
                ],
            );

        if ($action === 'view') {
            // 3 — carrega integrações do tenant e descobre tabelas
            $tables = $this->discoverTables($tenant);

            if ($tables === []) {
                $this->warn('Nenhuma tabela de integração encontrada para este tenant.');

                return self::SUCCESS;
            }

            // 4 — seleciona tabelas
            $selected = $this->runsWithoutPrompts()
                ? $tables
                : multiselect(
                    label: 'Quais tabelas você quer inspecionar?',
                    options: $tables,
                    default: $tables,
                    required: true,
                );

            $this->showStats($tenant, $selected);
        } else {
            $this->deleteRecordsByStores($tenant);
        }

        return self::SUCCESS;
    }

    private function runsWithoutPrompts(): bool
    {
        return trim((string) $this->option('tenant')) !== '';
    }

    // ─── Tenant selection ────────────────────────────────────────────────────

    private function pickTenant(): ?Tenant
    {
        $tenants = Tenant::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'database']);

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return null;
        }

        // Sem --tenant o comando abre um select() e morre fora de terminal
        // interativo (cron, CI, `docker compose exec -T`) — justamente onde um
        // comando de diagnóstico precisa funcionar.
        $requested = trim((string) $this->option('tenant'));

        if ($requested !== '') {
            return $this->resolveRequestedTenant($tenants, $requested);
        }

        if ($tenants->count() === 1) {
            $tenant = $tenants->first();

            if (! $this->hasDatabaseConfigured($tenant)) {
                $this->warn("Tenant {$tenant->name} sem database configurado.");

                return null;
            }

            $this->line("Tenant: <info>{$tenant->name}</info>");

            return $tenant;
        }

        $eligibleTenants = $tenants->filter(fn (Tenant $tenant): bool => $this->hasDatabaseConfigured($tenant));

        if ($eligibleTenants->isEmpty()) {
            $this->warn('Nenhum tenant com database configurado encontrado.');

            return null;
        }

        if ($eligibleTenants->count() !== $tenants->count()) {
            $ignored = $tenants->count() - $eligibleTenants->count();
            $this->warn("{$ignored} tenant(s) ignorado(s) por não ter database configurado.");
        }

        $options = $eligibleTenants->mapWithKeys(fn (Tenant $t): array => [
            (string) $t->id => "{$t->name}",
        ])->all();

        $tenantId = select(
            label: 'Selecione o tenant:',
            options: $options,
        );

        return $eligibleTenants->firstWhere('id', $tenantId);
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     */
    private function resolveRequestedTenant(Collection $tenants, string $requested): ?Tenant
    {
        $tenant = $tenants->first(
            fn (Tenant $t): bool => (string) $t->id === $requested || (string) $t->slug === $requested,
        );

        if ($tenant === null) {
            $this->error("Tenant não encontrado: {$requested}");

            return null;
        }

        if (! $this->hasDatabaseConfigured($tenant)) {
            $this->warn("Tenant {$tenant->name} sem database configurado.");

            return null;
        }

        $this->line("Tenant: <info>{$tenant->name}</info>");

        return $tenant;
    }

    private function hasDatabaseConfigured(Tenant $tenant): bool
    {
        $database = $tenant->getAttribute('database');

        return is_string($database) && trim($database) !== '';
    }

    // ─── Table discovery ─────────────────────────────────────────────────────

    /** @return list<string> */
    private function discoverTables(Tenant $tenant): array
    {
        $integration = $tenant->integration()->with('api')->first();

        if ($integration === null || ! $integration->is_active || $integration->api === null || ! $integration->api->is_active) {
            return [];
        }

        $paths = (array) data_get($integration->api->requests ?? [], 'paths', []);
        $tables = [];

        foreach ($paths as $pathConfig) {
            if (! is_array($pathConfig)) {
                continue;
            }

            $table = (string) data_get($pathConfig, 'target_table', '');

            if ($table !== '' && ! in_array($table, $tables, true)) {
                $tables[] = $table;
            }

            $pivotTables = (array) data_get($pathConfig, 'pivot_tables', []);

            foreach ($pivotTables as $pivotConfig) {
                if (! is_array($pivotConfig)) {
                    continue;
                }

                $pivotTable = (string) data_get($pivotConfig, 'table', '');

                if ($pivotTable !== '' && ! in_array($pivotTable, $tables, true)) {
                    $tables[] = $pivotTable;
                }
            }
        }

        return $tables;
    }

    // ─── View ────────────────────────────────────────────────────────────────

    /** @param list<string> $tables */
    private function showStats(Tenant $tenant, array $tables): void
    {
        foreach ($tables as $table) {
            $this->newLine();
            $this->info("── {$table} ──────────────────────────────────────");

            $tenant->execute(function () use ($table): void {
                if (! Schema::connection('tenant')->hasTable($table)) {
                    $this->warn("  Tabela '{$table}' não existe no banco do tenant.");

                    return;
                }

                $q = DB::connection('tenant')->table($table);
                $count = $q->count();
                $lastUpdated = $q->max('updated_at');

                $this->line(sprintf('  Registros : %s', number_format($count, 0, ',', '.')));
                $this->line(sprintf('  Último update : %s', $lastUpdated ? substr((string) $lastUpdated, 0, 16) : '—'));

                if (Schema::connection('tenant')->hasColumn($table, 'sale_date')) {
                    $min = $q->min('sale_date');
                    $max = $q->max('sale_date');
                    $this->line(sprintf('  Período (sale_date) : %s → %s', $min ?? '—', $max ?? '—'));
                }
            });
        }

        $this->newLine();
    }

    // ─── Delete ──────────────────────────────────────────────────────────────

    private function deleteRecordsByStores(Tenant $tenant): void
    {
        $startedAt = microtime(true);

        $stores = $this->pickStores($tenant);

        if ($stores->isEmpty()) {
            $this->info('Operação cancelada.');

            return;
        }

        $tables = $this->pickTablesToDelete();

        if ($tables === []) {
            $this->info('Operação cancelada.');

            return;
        }

        $storeIds = $stores->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();
        $storeNames = $stores->pluck('name')->map(fn (mixed $name): string => trim((string) $name) !== '' ? (string) $name : '(sem nome)')->all();
        $tableList = implode(', ', $tables);
        $storeList = implode(', ', $storeNames);

        if (! confirm("Confirma excluir [{$tableList}] das lojas [{$storeList}] do tenant {$tenant->name}?", default: false)) {
            $this->info('Operação cancelada.');

            return;
        }

        $summary = [
            'sales_deleted' => 0,
            'product_store_deleted' => 0,
            'products_deleted' => 0,
        ];

        $tenantId = (string) $tenant->id;

        $tenant->execute(function () use ($storeIds, $tables, &$summary, $tenantId): void {
            $connection = DB::connection('tenant');

            $connection->transaction(function () use ($connection, $storeIds, $tables, &$summary, $tenantId): void {
                if (in_array('sales', $tables, true) && Schema::connection('tenant')->hasTable('sales')) {
                    $this->line('  Limpando [sales]...');

                    $summary['sales_deleted'] = $connection
                        ->table('sales')
                        ->where('tenant_id', $tenantId)
                        ->whereIn('store_id', $storeIds)
                        ->delete();
                }

                $productIdsFromSelectedStores = collect();

                if (in_array('product_store', $tables, true) && Schema::connection('tenant')->hasTable('product_store')) {
                    $this->line('  Mapeando produtos vinculados nas lojas selecionadas...');

                    $productIdsFromSelectedStores = $connection
                        ->table('product_store')
                        ->where('tenant_id', $tenantId)
                        ->whereIn('store_id', $storeIds)
                        ->pluck('product_id')
                        ->map(fn (mixed $productId): string => (string) $productId)
                        ->unique()
                        ->filter();

                    $this->line('  Limpando [product_store]...');

                    $summary['product_store_deleted'] = $connection
                        ->table('product_store')
                        ->where('tenant_id', $tenantId)
                        ->whereIn('store_id', $storeIds)
                        ->delete();
                }

                if (! in_array('products', $tables, true) || ! Schema::connection('tenant')->hasTable('products') || $productIdsFromSelectedStores->isEmpty()) {
                    return;
                }

                $this->line('  Limpando [products] órfãos...');

                $summary['products_deleted'] = $connection
                    ->table('products')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('id', $productIdsFromSelectedStores->values()->all())
                    ->whereNotExists(function ($query) use ($tenantId): void {
                        $query->selectRaw('1')
                            ->from('product_store as ps')
                            ->where('ps.tenant_id', $tenantId)
                            ->whereColumn('ps.product_id', 'products.id');
                    })
                    ->delete();
            });
        });

        if (in_array('sales', $tables, true)) {
            $this->line(sprintf('  [sales] %d registros removidos.', $summary['sales_deleted']));
        }

        if (in_array('product_store', $tables, true)) {
            $this->line(sprintf('  [product_store] %d registros removidos.', $summary['product_store_deleted']));
        }

        if (in_array('products', $tables, true)) {
            $this->line(sprintf('  [products] %d registros removidos (somente órfãos sem loja).', $summary['products_deleted']));
        }

        $this->line(sprintf('  Tempo total: %.2fs', microtime(true) - $startedAt));

        $this->newLine();
        $this->info('Limpeza concluída.');
    }

    /** @return list<string> */
    private function pickTablesToDelete(): array
    {
        $options = [
            'sales' => 'sales — vendas das lojas selecionadas',
            'product_store' => 'product_store — vínculos produto↔loja',
            'products' => 'products — produtos órfãos (sem loja após limpeza)',
        ];

        return multiselect(
            label: 'Quais tabelas deseja limpar?',
            options: $options,
            default: array_keys($options),
            required: true,
        );
    }

    /** @return Collection<int, object{id: string, name: string|null}> */
    private function pickStores(Tenant $tenant): Collection
    {
        /** @var Collection<int, object{id: string, name: string|null}> $stores */
        $stores = $tenant->execute(function (): Collection {
            if (! Schema::connection('tenant')->hasTable('stores')) {
                return collect();
            }

            return DB::connection('tenant')
                ->table('stores')
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name']);
        });

        if ($stores->isEmpty()) {
            $this->warn('Nenhuma loja encontrada para este tenant.');

            return collect();
        }

        $options = $stores->mapWithKeys(function (object $store): array {
            $name = trim((string) ($store->name ?? ''));

            return [
                (string) $store->id => ($name !== '' ? $name : '(sem nome)').' ['.(string) $store->id.']',
            ];
        })->all();

        $selectedStoreIds = multiselect(
            label: 'Selecione uma ou mais lojas para excluir dados:',
            options: $options,
            required: true,
        );

        if ($selectedStoreIds === []) {
            return collect();
        }

        return $stores
            ->filter(fn (object $store): bool => in_array((string) $store->id, $selectedStoreIds, true))
            ->values();
    }
}
