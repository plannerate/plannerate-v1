<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Integrations\Dispatch\DispatchTenantIntegrationInitialSyncJob;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\ValidateIntegrationStoresService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('integrations:dispatch-initial {--tenant=} {--resource=all} {--ignore-synced-days}')]
#[Description('Dispara sincronizacao inicial por dias para integracoes ativas')]
class DispatchInitialCommand extends Command
{
    public function handle(
        ValidateIntegrationStoresService $validateIntegrationStoresService,
    ): int {
        $resource = $this->resolveResourceOption();
        if ($resource === false) {
            return self::FAILURE;
        }

        $query = TenantIntegration::query()
            ->where('is_active', true);

        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        $integrations = $query->get();

        foreach ($integrations as $integration) {
            if (! $validateIntegrationStoresService->validateBeforeDispatch($integration, 'inicial')) {
                $this->warn(sprintf('Initial sync skipped for tenant %s due to invalid store/API configuration.', $integration->tenant_id));

                continue;
            }

            DispatchTenantIntegrationInitialSyncJob::dispatch(
                $integration->id,
                $resource,
                (bool) $this->option('ignore-synced-days'),
            );
            $this->line(sprintf('Initial sync dispatched for tenant %s', $integration->tenant_id));
        }

        if ($integrations->isEmpty()) {
            $this->warn('Nenhuma integracao ativa encontrada para sincronizacao inicial.');
        }

        return self::SUCCESS;
    }

    private function resolveResourceOption(): string|null|false
    {
        $resource = strtolower((string) $this->option('resource'));

        if ($resource === '' || $resource === 'all') {
            return null;
        }

        if (! in_array($resource, ['sales', 'products'], true)) {
            $this->error('Opcao --resource invalida. Use: sales, products ou all.');

            return false;
        }

        return $resource;
    }
}
