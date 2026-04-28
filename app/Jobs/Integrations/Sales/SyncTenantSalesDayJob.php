<?php

namespace App\Jobs\Integrations\Sales;

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

class SyncTenantSalesDayJob implements ShouldQueue, TenantAware
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public string $integrationId,
        public string $referenceDate,
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
            return;
        }

        $syncDay = IntegrationSyncDay::query()->firstOrCreate(
            [
                'tenant_integration_id' => $integration->id,
                'resource' => 'sales',
                'reference_date' => $this->referenceDate,
            ],
            [
                'status' => 'pending',
            ]
        );

        $syncDay->markRunning();

        try {
            $salesIntegrationService = $integrationServiceResolver->resolveSalesService($integration);
            $processing = $configNormalizer->normalize($integration)['processing'];
            $stores = Store::query()
                ->where('status', 'published')
                ->whereNull('deleted_at')
                ->get(['id', 'code', 'document']);
            $referenceDate = Carbon::parse($this->referenceDate)->toDateString();
            $executed = false;

            Log::info('Integrations sales sync stores loaded.', [
                'integration_id' => $integration->id,
                'tenant_id' => $integration->tenant_id,
                'reference_date' => $referenceDate,
                'stores_count' => $stores->count(),
            ]);

            foreach ($stores as $store) {
                $empresa = $this->resolveEmpresaForStore($store->code, $store->document, $processing);

                if ($empresa === null) {
                    Log::warning('Integrations sales sync skipped store due to missing/invalid empresa.', [
                        'integration_id' => $integration->id,
                        'tenant_id' => $integration->tenant_id,
                        'reference_date' => $referenceDate,
                        'store_id' => (string) $store->id,
                        'store_code' => $store->code,
                        'store_document' => $store->document,
                        'processing_empresa' => $processing['empresa'] ?? null,
                    ]);

                    continue;
                }

                $filters = [
                    'date' => $referenceDate,
                    'store_id' => (string) $store->id,
                    'store_document' => (string) $store->document,
                    'empresa' => $empresa,
                    'page_size' => (int) ($processing['sales_page_size'] ?? 20000),
                    'tipo_consulta' => (string) ($processing['sales_tipo_consulta'] ?? 'produto'),
                    'partner_key' => (string) ($processing['partner_key'] ?? ''),
                ];

                Log::info('Integrations sales sync request filters.', [
                    'integration_id' => $integration->id,
                    'tenant_id' => $integration->tenant_id,
                    'reference_date' => $referenceDate,
                    'store_id' => (string) $store->id,
                    'store_code' => $store->code,
                    'store_document' => $store->document,
                    'filters' => $filters,
                ]);

                $salesIntegrationService->fetchSales($integration, $filters);

                $executed = true;
            }

            if (! $executed) {
                $fallbackEmpresa = $this->normalizeEmpresaValue($processing['empresa'] ?? null);
                if ($fallbackEmpresa === null) {
                    Log::warning('Integrations sales sync fallback skipped due to missing/invalid empresa.', [
                        'integration_id' => $integration->id,
                        'tenant_id' => $integration->tenant_id,
                        'reference_date' => $referenceDate,
                        'processing_empresa' => $processing['empresa'] ?? null,
                    ]);

                    $syncDay->markFailed('Empresa invalida para sincronizacao de vendas.');

                    return;
                }

                $fallbackFilters = [
                    'date' => $referenceDate,
                    'empresa' => $fallbackEmpresa,
                    'page_size' => (int) ($processing['sales_page_size'] ?? 20000),
                    'tipo_consulta' => (string) ($processing['sales_tipo_consulta'] ?? 'produto'),
                    'partner_key' => (string) ($processing['partner_key'] ?? ''),
                ];

                Log::info('Integrations sales sync fallback request filters.', [
                    'integration_id' => $integration->id,
                    'tenant_id' => $integration->tenant_id,
                    'reference_date' => $referenceDate,
                    'filters' => $fallbackFilters,
                ]);

                $salesIntegrationService->fetchSales($integration, $fallbackFilters);
            }

            $syncDay->markSuccess();
            if (! $this->suppressSuccessNotifications) {
                broadcast(new IntegrationProcessFinished(
                    tenantId: (string) $integration->tenant_id,
                    integrationId: (string) $integration->id,
                    resource: 'sales',
                    referenceDate: $this->referenceDate,
                    status: 'success',
                ));
                $this->notifyTenantUsers(
                    title: 'Sincronização de vendas concluída',
                    message: sprintf('Integração %s finalizou vendas para %s com sucesso.', $integration->id, $this->referenceDate),
                    type: 'success',
                );
            }
        } catch (Throwable $exception) {
            $shortErrorMessage = BroadcastPayload::shortenErrorMessage($exception->getMessage());

            $syncDay->markFailed($exception->getMessage());
            broadcast(new IntegrationProcessFinished(
                tenantId: (string) $integration->tenant_id,
                integrationId: (string) $integration->id,
                resource: 'sales',
                referenceDate: $this->referenceDate,
                status: 'failed',
                errorMessage: $shortErrorMessage,
            ));
            $this->notifyTenantUsers(
                title: 'Falha na sincronização de vendas',
                message: sprintf('Integração %s falhou em vendas para %s: %s', $integration->id, $this->referenceDate, $shortErrorMessage ?? 'Erro sem detalhe'),
                type: 'error',
            );
            Log::error('Integrations sales sync failed without rethrow.', [
                'integration_id' => $this->integrationId,
                'reference_date' => $this->referenceDate,
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

    /**
     * A Sysmo espera o identificador da loja no campo "empresa".
     *
     * @param  array<string, mixed>  $processing
     */
    private function resolveEmpresaForStore(?string $storeCode, ?string $storeDocument, array $processing): ?string
    {
        $empresaFromDocument = $this->normalizeEmpresaValue($storeDocument);
        if ($empresaFromDocument !== null) {
            return $empresaFromDocument;
        }

        $empresaFromStoreCode = $this->normalizeEmpresaValue($storeCode);
        if ($empresaFromStoreCode !== null) {
            return $empresaFromStoreCode;
        }

        $empresaFromProcessing = $this->normalizeEmpresaValue($processing['empresa'] ?? null);
        if ($empresaFromProcessing !== null) {
            return $empresaFromProcessing;
        }

        return null;
    }

    private function normalizeEmpresaValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', trim((string) $value));
        if ($normalized === '' || ! ctype_digit($normalized)) {
            return null;
        }

        return (int) $normalized > 0 ? $normalized : null;
    }
}
