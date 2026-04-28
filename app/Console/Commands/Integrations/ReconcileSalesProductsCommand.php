<?php

namespace App\Console\Commands\Integrations;

use App\Models\Tenant;
use App\Services\Integrations\Support\SyncSalesProductReferencesService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Signature('integrations:reconcile-sales-products {--tenant=} {--chunk=500}')]
#[Description('Reconcila product_id/ean em vendas a partir de products.codigo_erp')]
class ReconcileSalesProductsCommand extends Command
{
    public function __construct(
        private readonly SyncSalesProductReferencesService $syncSalesProductReferencesService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));

        $query = Tenant::query()->where('status', 'active');
        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->whereKey($tenantId);
        }

        $tenants = $query->get(['id']);

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant ativo encontrado para reconciliacao.');

            return self::SUCCESS;
        }

        $configuredTenantConnection = config('multitenancy.tenant_database_connection_name');
        $tenantConnectionName = (string) ($configuredTenantConnection ?: config('database.default'));
        $shouldSwitchTenantContext = is_string($configuredTenantConnection) && $configuredTenantConnection !== '';

        foreach ($tenants as $tenant) {
            $processedChunks = 0;
            $processedCodes = 0;

            $runReconciliation = function () use ($tenant, $tenantConnectionName, $chunkSize, &$processedChunks, &$processedCodes): void {
                DB::connection($tenantConnectionName)
                    ->table('products')
                    ->whereNotNull('codigo_erp')
                    ->orderBy('id')
                    ->select(['codigo_erp'])
                    ->chunk($chunkSize, function ($rows) use ($tenant, $tenantConnectionName, &$processedChunks, &$processedCodes): void {
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

                        $this->syncSalesProductReferencesService->syncByCodigoErp(
                            tenantConnectionName: $tenantConnectionName,
                            tenantId: (string) $tenant->id,
                            erpCodes: $erpCodes,
                            now: Carbon::now(),
                        );

                        $processedChunks++;
                        $processedCodes += count($erpCodes);
                    });
            };

            if ($shouldSwitchTenantContext) {
                $tenant->execute($runReconciliation);
            } else {
                $runReconciliation();
            }

            $this->line(sprintf(
                'Reconciliacao concluida para tenant %s (chunks=%d, codigos=%d)',
                $tenant->id,
                $processedChunks,
                $processedCodes,
            ));
        }

        return self::SUCCESS;
    }
}
