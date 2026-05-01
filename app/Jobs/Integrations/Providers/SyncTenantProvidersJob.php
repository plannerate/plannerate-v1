<?php

namespace App\Jobs\Integrations\Providers;

use App\Events\Tenant\IntegrationProcessFinished;
use App\Models\IntegrationSyncDay;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Integrations\Support\IntegrationServiceResolver;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use App\Support\BroadcastPayload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Multitenancy\Jobs\TenantAware;
use Throwable;

class SyncTenantProvidersJob implements ShouldQueue, TenantAware
{
    use Queueable;

    private const MAX_PROGRESSIVE_PAGE = 200;

    public int $timeout = 600;

    public function __construct(
        public string $integrationId,
        public int $page = 1,
        public bool $suppressSuccessNotifications = false,
    ) {}

    public function handle(
        IntegrationServiceResolver $integrationServiceResolver,
        TenantIntegrationConfigNormalizer $configNormalizer,
    ): void {
        $integration = TenantIntegration::query()
            ->whereKey($this->integrationId)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            Log::warning('Integrations providers sync aborted: integration not found or inactive.', [
                'integration_id' => $this->integrationId,
                'page' => $this->page,
            ]);

            return;
        }

        $syncDay = IntegrationSyncDay::query()->firstOrCreate(
            [
                'tenant_integration_id' => $integration->id,
                'resource' => 'providers',
                'reference_date' => now()->toDateString(),
            ],
            [
                'status' => 'pending',
            ]
        );

        if ($this->page === 1) {
            $syncDay->markRunning();
        }

        try {
            $processing = $configNormalizer->normalize($integration)['processing'];
            $pageSize = (int) ($processing['providers_page_size'] ?? 500);

            Log::info('Integrations providers sync starting.', [
                'integration_id' => $this->integrationId,
                'tenant_id' => (string) $integration->tenant_id,
                'page' => $this->page,
                'page_size' => $pageSize,
            ]);

            $providersService = $integrationServiceResolver->resolveProvidersService($integration);

            $items = $providersService->fetchProviders($integration, [
                'page' => $this->page,
                'page_size' => $pageSize,
                'partner_key' => (string) ($processing['partner_key'] ?? ''),
            ]);

            $itemsCount = count($items);

            Log::info('Integrations providers sync page fetched.', [
                'integration_id' => $this->integrationId,
                'tenant_id' => (string) $integration->tenant_id,
                'page' => $this->page,
                'items_count' => $itemsCount,
            ]);

            if ($itemsCount >= $pageSize && $this->page < self::MAX_PROGRESSIVE_PAGE) {
                Log::info('Integrations providers sync dispatching next page.', [
                    'integration_id' => $this->integrationId,
                    'tenant_id' => (string) $integration->tenant_id,
                    'next_page' => $this->page + 1,
                ]);

                SyncTenantProvidersJob::dispatch(
                    integrationId: (string) $integration->id,
                    page: $this->page + 1,
                    suppressSuccessNotifications: $this->suppressSuccessNotifications,
                );

                return;
            }

            $syncDay->markSuccess();

            Log::info('Integrations providers sync completed successfully.', [
                'integration_id' => $this->integrationId,
                'tenant_id' => (string) $integration->tenant_id,
                'last_page' => $this->page,
            ]);

            if (! $this->suppressSuccessNotifications) {
                broadcast(new IntegrationProcessFinished(
                    tenantId: (string) $integration->tenant_id,
                    integrationId: (string) $integration->id,
                    resource: 'providers',
                    referenceDate: now()->toDateString(),
                    status: 'success',
                ));
                $this->notifyTenantUsers(
                    title: 'Sincronização de fornecedores concluída',
                    message: sprintf('Integração %s finalizou fornecedores com sucesso.', $integration->id),
                    type: 'success',
                );
            }
        } catch (Throwable $exception) {
            $shortErrorMessage = BroadcastPayload::shortenErrorMessage($exception->getMessage());

            $syncDay->markFailed($exception->getMessage());
            broadcast(new IntegrationProcessFinished(
                tenantId: (string) $integration->tenant_id,
                integrationId: (string) $integration->id,
                resource: 'providers',
                referenceDate: now()->toDateString(),
                status: 'failed',
                errorMessage: $shortErrorMessage,
            ));
            $this->notifyTenantUsers(
                title: 'Falha na sincronização de fornecedores',
                message: sprintf('Integração %s falhou em fornecedores: %s', $integration->id, $shortErrorMessage ?? 'Erro sem detalhe'),
                type: 'error',
            );
            Log::error('Integrations providers sync failed.', [
                'integration_id' => $this->integrationId,
                'page' => $this->page,
                'error' => $exception->getMessage(),
            ]);
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
