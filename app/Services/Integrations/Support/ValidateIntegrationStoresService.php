<?php

namespace App\Services\Integrations\Support;

use App\Models\Store;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Integrations\Contracts\ProductsIntegrationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ValidateIntegrationStoresService
{
    public function __construct(
        private readonly IntegrationServiceResolver $integrationServiceResolver,
        private readonly TenantIntegrationConfigNormalizer $configNormalizer,
    ) {}

    /**
     * Returns null on success, or a failure reason string.
     */
    public function validateBeforeDispatch(
        TenantIntegration $integration,
        string $dispatchLabel,
        ?string $resource = null,
    ): ?string {
        $tenant = $integration->tenant;
        if (! $tenant) {
            return 'tenant not found';
        }

        return $tenant->execute(function () use ($integration, $dispatchLabel, $resource): ?string {
            $shouldValidateProductsEndpoint = $this->shouldValidateProductsEndpoint($resource);
            $productsService = null;

            if ($shouldValidateProductsEndpoint) {
                try {
                    $productsService = $this->integrationServiceResolver->resolveProductsService($integration);
                } catch (Throwable $exception) {
                    $reason = 'service resolver: '.$exception->getMessage();
                    $this->notifyTenantUsersAboutInvalidStores($integration, $dispatchLabel, [[
                        'store_id' => 'n/a',
                        'reason' => $reason,
                    ]]);

                    return $reason;
                }
            }

            if (! $this->integrationServiceResolver->isPerStore($integration)) {
                if (! $shouldValidateProductsEndpoint) {
                    return null;
                }

                return $this->validateGlobalApi($integration, $dispatchLabel, $productsService);
            }

            return $this->validatePerStore(
                integration: $integration,
                dispatchLabel: $dispatchLabel,
                productsService: $productsService,
                shouldValidateProductsEndpoint: $shouldValidateProductsEndpoint,
            );
        });
    }

    private function validateGlobalApi(
        TenantIntegration $integration,
        string $dispatchLabel,
        ProductsIntegrationService $productsService,
    ): ?string {
        $processing = $this->configNormalizer->normalize($integration)['processing'];

        try {
            $productsService->discoverProductsTotalPages($integration, [
                'page_size' => (int) ($processing['products_page_size'] ?? 1000),
            ]);

            return null;
        } catch (Throwable $exception) {
            $reason = $exception->getMessage();
            $this->notifyTenantUsersAboutInvalidStores($integration, $dispatchLabel, [[
                'store_id' => 'n/a',
                'reason' => $reason,
            ]]);

            return $reason;
        }
    }

    private function validatePerStore(
        TenantIntegration $integration,
        string $dispatchLabel,
        ?ProductsIntegrationService $productsService,
        bool $shouldValidateProductsEndpoint,
    ): ?string {
        $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
        if (! Schema::connection($tenantConnectionName)->hasTable('stores')) {
            return null;
        }

        $stores = Store::query()
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->get(['id', 'code', 'document']);

        if ($stores->isEmpty()) {
            return null;
        }

        $processing = $this->configNormalizer->normalize($integration)['processing'];
        $failedStores = [];
        $hasAtLeastOneValidStore = false;

        foreach ($stores as $store) {
            $empresa = $this->resolveEmpresaForStore($store->code, $store->document, $processing);
            if ($empresa === null) {
                $failedStores[] = [
                    'store_id' => (string) $store->id,
                    'reason' => 'empresa_invalid',
                ];
                $this->setStoreAsDraft($store);

                continue;
            }

            try {
                Log::info('Integrations store validation request prepared.', [
                    'integration_id' => (string) $integration->id,
                    'tenant_id' => (string) $integration->tenant_id,
                    'store_id' => (string) $store->id,
                    'store_document' => $store->document,
                    'empresa' => $empresa,
                    'partner_key_suffix' => substr((string) ($processing['partner_key'] ?? ''), -6),
                ]);

                if (! $shouldValidateProductsEndpoint) {
                    $hasAtLeastOneValidStore = true;

                    continue;
                }

                if (! $productsService instanceof ProductsIntegrationService) {
                    throw new \RuntimeException('Servico de produtos nao disponivel para validacao.');
                }

                $productsService->discoverProductsTotalPages($integration, [
                    'store_id' => (string) $store->id,
                    'store_document' => $store->document,
                    'empresa' => $empresa,
                    'partner_key' => (string) ($processing['partner_key'] ?? ''),
                    'page_size' => (int) ($processing['products_page_size'] ?? 1000),
                ]);
                $hasAtLeastOneValidStore = true;
            } catch (Throwable $exception) {
                $failedStores[] = [
                    'store_id' => (string) $store->id,
                    'reason' => $exception->getMessage(),
                ];
                $this->setStoreAsDraft($store);
            }
        }

        if ($failedStores !== []) {
            $this->notifyTenantUsersAboutInvalidStores($integration, $dispatchLabel, $failedStores);
        }

        if (! $hasAtLeastOneValidStore) {
            $summary = collect($failedStores)
                ->take(3)
                ->map(fn (array $f): string => sprintf('loja %s: %s', $f['store_id'], $f['reason']))
                ->implode('; ');

            return 'nenhuma loja válida — '.$summary;
        }

        return null;
    }

    private function shouldValidateProductsEndpoint(?string $resource): bool
    {
        return $resource === null || $resource === 'products';
    }

    /**
     * @param  array<int, array{store_id: string, reason: string}>  $failedStores
     */
    private function notifyTenantUsersAboutInvalidStores(
        TenantIntegration $integration,
        string $dispatchLabel,
        array $failedStores,
    ): void {
        $users = User::query()
            ->where('is_active', true)
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $details = collect($failedStores)
            ->take(5)
            ->map(fn (array $failure): string => sprintf('Loja %s (%s)', $failure['store_id'], $failure['reason']))
            ->implode('; ');

        Notification::send($users, new AppNotification(
            title: sprintf('Falha na validação de lojas para sincronização %s', $dispatchLabel),
            message: sprintf(
                'Integração %s não foi disparada. Verifique documento/empresa das lojas na API. Detalhes: %s',
                $integration->id,
                $details === '' ? 'sem detalhes' : $details,
            ),
            type: 'error',
        ));
    }

    /**
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

    private function setStoreAsDraft(Store $store): void
    {
        if ($store->status === 'draft') {
            return;
        }

        $store->forceFill(['status' => 'draft'])->save();
    }
}
