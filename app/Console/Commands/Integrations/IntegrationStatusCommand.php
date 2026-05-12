<?php

namespace App\Console\Commands\Integrations;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class IntegrationStatusCommand extends Command
{
    protected $signature = 'integration:status';

    protected $description = 'Inspeciona e gerencia dados das tabelas de integração por tenant';

    public function handle(): int
    {
        // 1 — seleciona tenant
        $tenant = $this->pickTenant();

        if ($tenant === null) {
            return self::SUCCESS;
        }

        // 2 — carrega integrações do tenant e descobre tabelas
        $tables = $this->discoverTables($tenant);

        if ($tables === []) {
            $this->warn('Nenhuma tabela de integração encontrada para este tenant.');

            return self::SUCCESS;
        }

        // 3 — seleciona tabelas
        $selected = multiselect(
            label: 'Quais tabelas você quer inspecionar?',
            options: $tables,
            default: $tables,
            required: true,
        );

        // 4 — ação: visualizar ou excluir
        $action = select(
            label: 'O que deseja fazer?',
            options: [
                'view' => 'Visualizar stats + amostra de dados',
                'delete' => 'Excluir registros',
            ],
        );

        if ($action === 'view') {
            $this->showStats($tenant, $selected);
        } else {
            $this->deleteRecords($tenant, $selected);
        }

        return self::SUCCESS;
    }

    // ─── Tenant selection ────────────────────────────────────────────────────

    private function pickTenant(): ?Tenant
    {
        $tenants = Tenant::query()
            ->orderBy('name')
            ->get(['id', 'name', 'database']);

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return null;
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

    /** @param list<string> $tables */
    private function deleteRecords(Tenant $tenant, array $tables): void
    {
        $list = implode(', ', $tables);

        if (! confirm("Confirma excluir TODOS os registros de [{$list}] do tenant {$tenant->name}?", default: false)) {
            $this->info('Operação cancelada.');

            return;
        }

        $countsBeforeDelete = [];

        $tenant->execute(function () use ($tables, &$countsBeforeDelete): void {
            foreach ($tables as $table) {
                if (! Schema::connection('tenant')->hasTable($table)) {
                    $countsBeforeDelete[$table] = 0;

                    continue;
                }

                $countsBeforeDelete[$table] = DB::connection('tenant')->table($table)->count();
            }
        });

        foreach ($tables as $table) {
            $tenant->execute(function () use ($table): void {
                if (! Schema::connection('tenant')->hasTable($table)) {
                    return;
                }

                DB::connection('tenant')->statement("TRUNCATE TABLE \"{$table}\" CASCADE");
            });

            $this->line(sprintf('  [%s] %d registros removidos.', $table, (int) ($countsBeforeDelete[$table] ?? 0)));
        }

        $this->newLine();
        $this->info('Limpeza concluída.');
    }
}
