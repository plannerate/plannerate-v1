<?php

namespace App\Jobs\Integrations\Products;

use App\Events\Tenant\IntegrationProcessFinished;
use App\Models\IntegrationSyncDay;
use App\Models\Store;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Integrations\Support\IntegrationServiceResolver;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use App\Support\BroadcastPayload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
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
        IntegrationServiceResolver $integrationServiceResolver,
        TenantIntegrationConfigNormalizer $configNormalizer,
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
            if (! $integrationServiceResolver->isPerStore($integration)) {
                // Não per-store (ex: GesCooper): pagina sincronamente para que o
                // chain só avance ao RunTenantIntegrationPostSyncJob depois de tudo concluído.
                $this->syncAllPagesSync($integration, $integrationServiceResolver, $configNormalizer);
            } else {
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

    private function syncAllPagesSync(
        TenantIntegration $integration,
        IntegrationServiceResolver $integrationServiceResolver,
        TenantIntegrationConfigNormalizer $configNormalizer,
    ): void {
        $productsService = $integrationServiceResolver->resolveProductsService($integration);
        $processing = $configNormalizer->normalize($integration)['processing'];
        $pageSize = (int) ($processing['products_page_size'] ?? 1000);
        $referenceDate = Carbon::parse($this->referenceDate)->toDateString();
        $page = 1;
        $maxPage = 500;

        do {
            $items = $productsService->fetchProducts($integration, [
                'date' => $this->fullSync ? null : $referenceDate,
                'store_id' => null,
                'empresa' => null,
                'page' => $page,
                'page_size' => $pageSize,
                'partner_key' => (string) ($processing['partner_key'] ?? ''),
            ]);

            $page++;
        } while (count($items) >= $pageSize && $page <= $maxPage);

        if ($page > $maxPage) {
            Log::warning('Global products sync reached page safety limit.', [
                'integration_id' => $integration->id,
                'tenant_id' => $integration->tenant_id,
                'reference_date' => $referenceDate,
                'page_limit' => $maxPage,
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
