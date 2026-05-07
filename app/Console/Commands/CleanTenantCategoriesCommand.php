<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

class CleanTenantCategoriesCommand extends Command
{
    protected $signature = 'tenant:clean-categories
        {--tenant=* : ID ou slug dos tenants (pode repetir ou separar por vírgula)}
        {--force : Não pede confirmação antes de limpar}';

    protected $description = 'Limpa a tabela de categorias de um ou mais tenants';

    public function handle(): int
    {
        try {
            DB::connection('landlord')->getPdo();
        } catch (\Exception $e) {
            $this->error('❌ Não foi possível conectar ao banco landlord: '.$e->getMessage());
            $this->line('<fg=yellow>Aguarde o banco inicializar e tente novamente.</>');

            return self::FAILURE;
        }

        $tenants = $this->resolveTenants();

        if (empty($tenants)) {
            $this->warn('Nenhum tenant selecionado.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info(sprintf('🏢 %d tenant(s) selecionado(s).', count($tenants)));

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->line('═══════════════════════════════════════════════════════');
            $this->info("🔄 {$tenant->name} ({$tenant->database})");

            if (! $this->setupTenantDatabase($tenant)) {
                continue;
            }

            if (! Schema::connection('tenant_clean')->hasTable('categories')) {
                $this->warn("  ⚠️  Tabela 'categories' não encontrada no banco do tenant.");
                DB::purge('tenant_clean');

                continue;
            }

            $count = DB::connection('tenant_clean')->table('categories')->count();
            $this->line("  📊 Registros encontrados: <fg=cyan>{$count}</>");

            if ($count === 0) {
                $this->line('  <fg=gray>–  Nada a limpar.</>');
                DB::purge('tenant_clean');

                continue;
            }

            if (! $this->option('force')) {
                $confirmed = confirm(
                    label: "  Confirma a limpeza de {$count} categorias do tenant \"{$tenant->name}\"?",
                    default: false,
                );

                if (! $confirmed) {
                    $this->line('  <fg=yellow>⏭  Pulado.</>');
                    DB::purge('tenant_clean');

                    continue;
                }
            }

            DB::connection('tenant_clean')->table('categories')->truncate();

            $this->info("  ✅ {$count} categoria(s) removida(s).");

            DB::purge('tenant_clean');
        }

        $this->newLine();
        $this->info('✅ Concluído!');

        return self::SUCCESS;
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
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'database']);

        if ($all->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return [];
        }

        $selected = multiselect(
            label: 'Selecione os tenants para limpar categorias',
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

        Config::set('database.connections.tenant_clean', array_merge($baseConfig, [
            'database' => $tenant->database,
        ]));

        DB::purge('tenant_clean');

        try {
            DB::connection('tenant_clean')->getPdo();
            $this->line("  ✅ Banco: {$tenant->database}");

            return true;
        } catch (\Exception $e) {
            $this->error("  ❌ Não foi possível conectar a '{$tenant->database}': ".$e->getMessage());

            return false;
        }
    }
}
