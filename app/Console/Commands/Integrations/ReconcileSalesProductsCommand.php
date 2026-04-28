<?php

namespace App\Console\Commands\Integrations;

use App\Models\Tenant;
use App\Services\Integrations\Sysmo\SysmoProductsIntegrationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('integrations:reconcile-sales-products {--tenant=} {--chunk=500} {--with-ean-references}')]
#[Description('Reconcila products por ean_references e sales por products.codigo_erp')]
class ReconcileSalesProductsCommand extends Command
{
    public function __construct(
        private readonly SysmoProductsIntegrationService $sysmoProductsIntegrationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('chunk') !== null) {
            $this->line('Opcao --chunk mantida por compatibilidade e ignorada no modo atual.');
        }

        $withEanReferences = (bool) $this->option('with-ean-references');

        $query = Tenant::query()->where('status', 'active');
        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->whereKey($tenantId);
        }

        $tenants = $query->get(['id', 'database']);

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant ativo encontrado para reconciliacao.');

            return self::SUCCESS;
        }

        $configuredTenantConnection = config('multitenancy.tenant_database_connection_name');
        $tenantConnectionName = (string) ($configuredTenantConnection ?: config('database.default'));
        $shouldSwitchTenantContext = is_string($configuredTenantConnection) && $configuredTenantConnection !== '';

        foreach ($tenants as $tenant) {
            $runReconciliation = function () use ($tenant, $shouldSwitchTenantContext, $withEanReferences): void {
                $this->sysmoProductsIntegrationService->finalizePersistedProductsSync(
                    tenantId: (string) $tenant->id,
                    enqueueSalesReconciliation: true,
                    executeInTenantContextForJobs: $shouldSwitchTenantContext,
                    enqueueProductsReconciliation: $withEanReferences,
                );
            };

            $tenantDatabase = is_string($tenant->getAttribute('database'))
                ? trim((string) $tenant->getAttribute('database'))
                : '';

            if ($shouldSwitchTenantContext && $tenantDatabase === '') {
                $this->warn(sprintf(
                    'Tenant %s sem database configurado; reconciliacao ignorada para evitar execucao na base landlord.',
                    $tenant->id,
                ));

                continue;
            }

            if ($shouldSwitchTenantContext) {
                $tenant->execute($runReconciliation);
            } else {
                $runReconciliation();
            }

            $this->line(sprintf(
                $withEanReferences
                    ? 'Reconciliacao enfileirada para tenant %s (ean_references + sales por codigo_erp)'
                    : 'Reconciliacao enfileirada para tenant %s (sales por codigo_erp)',
                $tenant->id,
            ));
        }

        return self::SUCCESS;
    }
}
