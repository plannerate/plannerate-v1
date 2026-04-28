<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Integrations\Dispatch\DispatchTenantIntegrationDailySyncJob;
use App\Models\TenantIntegration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('integrations:dispatch-daily {--tenant=}')]
#[Description('Dispara sincronizacao diaria e reprocessamento de lacunas')]
class DispatchDailyCommand extends Command
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
            DispatchTenantIntegrationDailySyncJob::dispatch($integration->id);
            $this->line(sprintf('Daily sync dispatched for tenant %s', $integration->tenant_id));
        }

        if ($integrations->isEmpty()) {
            $this->warn('Nenhuma integracao ativa encontrada para sincronizacao diaria.');
        }

        return self::SUCCESS;
    }
}
