<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Integrations\Dispatch\DispatchTenantIntegrationInitialSyncJob;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\ValidateIntegrationStoresService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('integrations:dispatch-initial {--tenant=}')]
#[Description('Dispara sincronizacao inicial por dias para integracoes ativas')]
class DispatchInitialCommand extends Command
{
    public function handle(
        ValidateIntegrationStoresService $validateIntegrationStoresService,
    ): int {
        $query = TenantIntegration::query()
            ->where('is_active', true);

        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        $integrations = $query->get(['id', 'tenant_id']);

        foreach ($integrations as $integration) {
            if (! $validateIntegrationStoresService->validateBeforeDispatch($integration, 'inicial')) {
                $this->warn(sprintf('Initial sync skipped for tenant %s due to invalid store/API configuration.', $integration->tenant_id));

                continue;
            }

            DispatchTenantIntegrationInitialSyncJob::dispatch($integration->id);
            $this->line(sprintf('Initial sync dispatched for tenant %s', $integration->tenant_id));
        }

        if ($integrations->isEmpty()) {
            $this->warn('Nenhuma integracao ativa encontrada para sincronizacao inicial.');
        }

        return self::SUCCESS;
    }
}
