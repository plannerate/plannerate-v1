<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Integrations\Maintenance\RunTenantIntegrationNightlyMaintenanceJob;
use App\Models\TenantIntegration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('integrations:dispatch-nightly-maintenance {--tenant=}')]
#[Description('Dispara limpeza noturna de vendas e ciclo de vida de produtos')]
class DispatchNightlyMaintenanceCommand extends Command
{
    public function handle(): int
    {
        $query = TenantIntegration::query()
            ->where('is_active', true);

        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        $integrations = $query->get(['id', 'tenant_id']);

        foreach ($integrations as $integration) {
            RunTenantIntegrationNightlyMaintenanceJob::dispatch($integration->id);
            $this->line(sprintf('Nightly maintenance dispatched for tenant %s', $integration->tenant_id));
        }

        if ($integrations->isEmpty()) {
            $this->warn('Nenhuma integracao ativa encontrada para manutencao noturna.');
        }

        return self::SUCCESS;
    }
}
