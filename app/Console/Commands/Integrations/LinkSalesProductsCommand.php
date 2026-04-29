<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Integrations\Products\ReconcileSalesProductsChunkJob;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LinkSalesProductsCommand extends Command
{
    protected $signature = 'integrations:link-sales-products
                            {--tenant= : ID do tenant específico}
                            {--chunk=100 : Tamanho do lote por codigo_erp}
                            {--preview : Apenas mostra quantos lotes seriam enviados}';

    protected $description = 'Enfileira reconciliacao de sales.product_id/ean por products.codigo_erp';

    public function handle(): int
    {
        $chunkSize = max(10, (int) $this->option('chunk'));
        $preview = (bool) $this->option('preview');

        $query = Tenant::query()->where('status', 'active');
        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->whereKey($tenantId);
        }

        $tenants = $query->get(['id', 'name', 'database']);
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        $configuredTenantConnection = config('multitenancy.tenant_database_connection_name');
        $tenantConnectionName = (string) ($configuredTenantConnection ?: config('database.default'));
        $shouldSwitchTenantContext = is_string($configuredTenantConnection) && $configuredTenantConnection !== '';

        foreach ($tenants as $tenant) {
            $tenantDatabase = is_string($tenant->database) ? trim($tenant->database) : '';
            if ($shouldSwitchTenantContext && $tenantDatabase === '') {
                $this->warn(sprintf('Tenant %s ignorado: sem database.', $tenant->id));

                continue;
            }

            $totalChunks = 0;
            $totalCodes = 0;

            $runner = function () use (
                $tenant,
                $chunkSize,
                $preview,
                $tenantConnectionName,
                $shouldSwitchTenantContext,
                &$totalChunks,
                &$totalCodes
            ): void {
                DB::connection($tenantConnectionName)->table('products')
                    ->where('tenant_id', (string) $tenant->id)
                    ->whereNotNull('codigo_erp')
                    ->orderBy('id')
                    ->select(['codigo_erp'])
                    ->chunk($chunkSize, function ($rows) use (
                        $tenant,
                        $preview,
                        $tenantConnectionName,
                        $shouldSwitchTenantContext,
                        &$totalChunks,
                        &$totalCodes
                    ): void {
                        $erpCodes = collect($rows)
                            ->pluck('codigo_erp')
                            ->filter(fn (mixed $codigo): bool => is_string($codigo) && trim($codigo) !== '')
                            ->map(fn (string $codigo): string => trim($codigo))
                            ->unique()
                            ->values()
                            ->all();

                        if ($erpCodes === []) {
                            return;
                        }

                        $totalChunks++;
                        $totalCodes += count($erpCodes);

                        if ($preview) {
                            return;
                        }

                        ReconcileSalesProductsChunkJob::dispatch(
                            tenantId: (string) $tenant->id,
                            erpCodes: $erpCodes,
                            tenantConnectionName: $tenantConnectionName,
                            executeInTenantContext: $shouldSwitchTenantContext,
                        )->onQueue('reconcile');
                    });
            };

            if ($shouldSwitchTenantContext) {
                $tenant->execute($runner);
            } else {
                $runner();
            }

            $this->line(sprintf(
                '%s (%s): chunks=%d codigos=%d %s',
                $tenant->name,
                $tenant->id,
                $totalChunks,
                $totalCodes,
                $preview ? '[preview]' : '[queued]',
            ));
        }

        return self::SUCCESS;
    }
}
