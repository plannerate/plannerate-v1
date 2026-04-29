<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Integrations\Dispatch\DispatchTenantIntegrationDailySyncJob;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Integrations\Support\ValidateIntegrationStoresService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

#[Signature('integrations:dispatch-daily {--tenant=}')]
#[Description('Dispara sincronizacao diaria e reprocessamento de lacunas')]
class DispatchDailyCommand extends Command
{
    public function handle(ValidateIntegrationStoresService $validateIntegrationStoresService): int
    {
        $query = TenantIntegration::query()
            ->where('is_active', true);

        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        $integrations = $query->get();

        foreach ($integrations as $integration) {
            if (! $validateIntegrationStoresService->validateBeforeDispatch($integration, 'diária')) {
                $this->warn(sprintf('Daily sync skipped for tenant %s due to invalid store/API configuration.', $integration->tenant_id));

                continue;
            }

            $this->notifyDailySyncStarted($integration);
            DispatchTenantIntegrationDailySyncJob::dispatch($integration->id);
            $this->line(sprintf('Daily sync dispatched for tenant %s', $integration->tenant_id));
        }

        if ($integrations->isEmpty()) {
            $this->warn('Nenhuma integracao ativa encontrada para sincronizacao diaria.');
        }

        return self::SUCCESS;
    }

    private function notifyDailySyncStarted(TenantIntegration $integration): void
    {
        $tenant = $integration->tenant;
        if (! $tenant) {
            return;
        }

        try {
            $tenant->execute(function () use ($integration): void {
                $users = User::query()
                    ->where('is_active', true)
                    ->get();

                if ($users->isEmpty()) {
                    return;
                }

                Notification::send($users, new AppNotification(
                    title: 'Sincronização diária iniciada',
                    message: sprintf('Integração %s foi enfileirada para o tenant %s.', $integration->id, $integration->tenant_id),
                    type: 'info',
                ));
            });
        } catch (Throwable $exception) {
            Log::warning('Falha ao enviar notificação de início da sincronização diária.', [
                'integration_id' => $integration->id,
                'tenant_id' => $integration->tenant_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
