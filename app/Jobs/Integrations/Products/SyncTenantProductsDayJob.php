<?php

namespace App\Jobs\Integrations\Products;

use App\Events\Tenant\IntegrationProcessFinished;
use App\Models\IntegrationSyncDay;
use App\Models\Store;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\BroadcastPayload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Spatie\Multitenancy\Jobs\TenantAware;
use Throwable;

class SyncTenantProductsDayJob implements ShouldQueue, TenantAware
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public string $integrationId,
        public string $referenceDate,
        public bool $fullSync = false,
    ) {}

    public function handle(
    ): void {
        $integration = TenantIntegration::query()
            ->whereKey($this->integrationId)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return;
        }

        $syncDay = IntegrationSyncDay::query()->firstOrCreate(
            [
                'tenant_integration_id' => $integration->id,
                'resource' => 'products',
                'reference_date' => $this->referenceDate,
            ],
            [
                'status' => 'pending',
            ]
        );

        $syncDay->markRunning();

        try {
            $stores = Store::query()
                ->where('tenant_id', $integration->tenant_id)
                ->where('status', 'published')
                ->whereNull('deleted_at')
                ->get(['id']);

            foreach ($stores as $store) {
                DispatchTenantProductStorePagesJob::dispatch(
                    integrationId: $integration->id,
                    referenceDate: $this->referenceDate,
                    storeId: (string) $store->id,
                    fullSync: $this->fullSync,
                );
            }

            $syncDay->markSuccess();
            if (! $this->fullSync) {
                broadcast(new IntegrationProcessFinished(
                    tenantId: (string) $integration->tenant_id,
                    integrationId: (string) $integration->id,
                    resource: 'products',
                    referenceDate: $this->referenceDate,
                    status: 'success',
                ));
                $this->notifyTenantUsers(
                    title: 'Sincronização de produtos concluída',
                    message: sprintf('Integração %s finalizou produtos para %s com sucesso.', $integration->id, $this->referenceDate),
                    type: 'success',
                );
            }
        } catch (Throwable $exception) {
            $shortErrorMessage = BroadcastPayload::shortenErrorMessage($exception->getMessage());

            $syncDay->markFailed($exception->getMessage());
            broadcast(new IntegrationProcessFinished(
                tenantId: (string) $integration->tenant_id,
                integrationId: (string) $integration->id,
                resource: 'products',
                referenceDate: $this->referenceDate,
                status: 'failed',
                errorMessage: $shortErrorMessage,
            ));
            $this->notifyTenantUsers(
                title: 'Falha na sincronização de produtos',
                message: sprintf('Integração %s falhou em produtos para %s: %s', $integration->id, $this->referenceDate, $shortErrorMessage ?? 'Erro sem detalhe'),
                type: 'error',
            );

            throw $exception;
        }
    }

    private function notifyTenantUsers(string $title, string $message, string $type): void
    {
        $users = User::query()
            ->where('is_active', true)
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new AppNotification(
            title: $title,
            message: $message,
            type: $type,
        ));
    }
}
