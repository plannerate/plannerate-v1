<?php

/**
 * Comando para vincular vendas aos produtos usando codigo_erp.
 *
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Console\Commands\Integrations;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LinkSalesProductsCommand extends Command
{
    protected $signature = 'sync:link-sales 
                            {--tenant= : ID do tenant específico}
                            {--preview : Apenas mostra o que seria feito}';

    protected $description = 'Vincula vendas aos produtos usando codigo_erp';

    public function handle(): int
    {
        $tenants = $this->getTenants();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        $preview = $this->option('preview');

        if ($preview) {
            $this->warn('MODO PREVIEW ativo: nenhuma alteração será aplicada.');
        }

        $results = [];
        foreach ($tenants as $tenant) {
            $summary = $this->processTenant($tenant, $preview);
            if ($summary !== null) {
                $results[] = $summary;
            }
        }

        $this->newLine();
        $this->info('✅ Vinculação concluída.');

        if ($results !== []) {
            $this->sendLinkSalesCompletedNotification($preview, $results, $tenants->count());
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>
     */
    protected function getTenants(): Collection
    {
        $query = Tenant::query()->where('status', 'active');

        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->whereKey($tenantId);
        }

        return $query->get(['id', 'name', 'database']);
    }

    /**
     * Envia notificação (database + broadcast) de conclusão do sync:link-sales.
     *
     * @param  array<int, array{tenant_name: string, linked: int, remaining: int}>  $results
     */
    protected function sendLinkSalesCompletedNotification(bool $preview, array $results, int $totalTenants): void
    {
        try {
            $users = User::all();
            if ($users->isEmpty()) {
                return;
            }

            $linked = array_sum(array_column($results, 'linked'));
            $remaining = array_sum(array_column($results, 'remaining'));
            $notification = new AppNotification(
                title: $preview ? 'Preview da vinculação de vendas' : 'Vinculação de vendas concluída',
                message: sprintf(
                    '%d tenant(s) processado(s), %d venda(s) vinculada(s), %d pendente(s).',
                    $totalTenants,
                    $linked,
                    $remaining,
                ),
                type: $preview ? 'info' : 'success',
            );

            foreach ($users as $user) {
                $user->notify($notification);
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar notificação de conclusão do sync:link-sales', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Processa um tenant específico.
     *
     * @return array{tenant_name: string, linked: int, remaining: int}|null Resumo ou null em caso de falha de conexão
     */
    protected function processTenant(Tenant $tenant, bool $preview): ?array
    {
        $configuredTenantConnection = config('multitenancy.tenant_database_connection_name');
        $connection = (string) ($configuredTenantConnection ?: config('database.default'));
        $shouldSwitchTenantContext = is_string($configuredTenantConnection) && $configuredTenantConnection !== '';

        $tenantDatabase = is_string($tenant->getAttribute('database'))
            ? trim((string) $tenant->getAttribute('database'))
            : '';

        if ($shouldSwitchTenantContext && $tenantDatabase === '') {
            $this->error(sprintf(
                '   ❌ Tenant %s sem database configurado; vinculação ignorada para evitar execução na base landlord.',
                $tenant->id,
            ));

            return null;
        }

        $process = fn (): ?array => $this->processTenantOnConnection(
            tenant: $tenant,
            connection: $connection,
            preview: $preview,
        );

        if ($shouldSwitchTenantContext) {
            return $tenant->execute($process);
        }

        return $process();
    }

    /**
     * @return array{tenant_name: string, linked: int, remaining: int}
     */
    protected function processTenantOnConnection(Tenant $tenant, string $connection, bool $preview): array
    {
        $tenantId = (string) $tenant->id;

        // 1. Contar vendas sem product_id
        $salesWithoutProduct = DB::connection($connection)
            ->table('sales')
            ->where('tenant_id', $tenantId)
            ->whereNull('product_id')
            ->whereNotNull('codigo_erp')
            ->count();

        if ($salesWithoutProduct === 0) {
            return [
                'tenant_name' => $tenant->name,
                'linked' => 0,
                'remaining' => 0,
            ];
        }

        if ($preview) {
            $this->showPreview($connection, $tenantId, $salesWithoutProduct);

            return [
                'tenant_name' => $tenant->name,
                'linked' => 0,
                'remaining' => $salesWithoutProduct,
            ];
        }

        // 3. Executar vinculação em batch usando UPDATE com JOIN
        $updated = $this->linkSalesToProducts($connection, $tenantId);

        // 4. Verificar vendas que não puderam ser vinculadas
        $remaining = DB::connection($connection)
            ->table('sales')
            ->where('tenant_id', $tenantId)
            ->whereNull('product_id')
            ->whereNotNull('codigo_erp')
            ->count();

        if ($remaining > 0) {
            $this->warn("   ⚠️  {$remaining} vendas sem produto correspondente (codigo_erp não encontrado)");
        }

        return [
            'tenant_name' => $tenant->name,
            'linked' => $updated,
            'remaining' => $remaining,
        ];
    }

    /**
     * Mostra preview do que seria vinculado
     */
    protected function showPreview(string $connection, string $tenantId, int $total): void
    {
        // Pegar amostra de vendas que seriam vinculadas
        $sampleSales = DB::connection($connection)
            ->table('sales as s')
            ->join('products as p', 's.codigo_erp', '=', 'p.codigo_erp')
            ->where('s.tenant_id', $tenantId)
            ->whereColumn('p.tenant_id', 's.tenant_id')
            ->whereNull('s.product_id')
            ->select(['s.codigo_erp', 'p.id as product_id', 'p.ean', 'p.name'])
            ->limit(10)
            ->get();

        if ($sampleSales->isEmpty()) {
            $this->warn('Nenhuma venda pode ser vinculada (produtos não encontrados).');

            return;
        }

        // Contar quantas poderiam ser vinculadas
        $linkable = DB::connection($connection)
            ->table('sales as s')
            ->join('products as p', 's.codigo_erp', '=', 'p.codigo_erp')
            ->where('s.tenant_id', $tenantId)
            ->whereColumn('p.tenant_id', 's.tenant_id')
            ->whereNull('s.product_id')
            ->count();

        $this->info("Preview: {$linkable} de {$total} vendas podem ser vinculadas.");

        $notLinkable = $total - $linkable;
        if ($notLinkable > 0) {
            $this->warn("Preview: {$notLinkable} vendas não têm produto correspondente.");
        }
    }

    /**
     * Vincula vendas aos produtos usando UPDATE com JOIN
     */
    protected function linkSalesToProducts(string $connection, string $tenantId): int
    {
        $database = DB::connection($connection);
        $driver = $database->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $sql = '
                UPDATE sales
                INNER JOIN products p
                    ON p.tenant_id = sales.tenant_id
                   AND p.codigo_erp = sales.codigo_erp
                SET
                    sales.product_id = p.id,
                    sales.ean = p.ean,
                    sales.updated_at = CURRENT_TIMESTAMP
                WHERE sales.tenant_id = ?
                  AND sales.product_id IS NULL
                  AND sales.codigo_erp IS NOT NULL
            ';

            return $database->affectingStatement($sql, [$tenantId]);
        }

        $sql = '
            UPDATE sales 
            SET 
                product_id = p.id,
                ean = p.ean,
                updated_at = CURRENT_TIMESTAMP
            FROM products p
            WHERE sales.tenant_id = ?
              AND p.tenant_id = sales.tenant_id
              AND sales.codigo_erp = p.codigo_erp
              AND sales.product_id IS NULL
              AND sales.codigo_erp IS NOT NULL
        ';

        return $database->affectingStatement($sql, [$tenantId]);
    }
}
