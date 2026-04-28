<?php

namespace App\Jobs\Integrations\Products;

use App\Models\TenantIntegration;
use App\Services\Integrations\Sysmo\SysmoProductsIntegrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\Multitenancy\Jobs\TenantAware;

class FinalizeTenantStoreProductsSyncJob implements ShouldQueue, TenantAware
{
    use Queueable;

    /**
     * Finalizacao pode executar reconciliacao em lote grande (sales x products).
     */
    public int $timeout = 900;

    public function __construct(
        public string $integrationId,
    ) {}

    public function handle(
        SysmoProductsIntegrationService $productsIntegrationService,
    ): void {
        $integration = TenantIntegration::query()
            ->whereKey($this->integrationId)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return;
        }

        $productsIntegrationService->finalizePersistedProductsSync(
            tenantId: (string) $integration->tenant_id,
        );
    }
}
