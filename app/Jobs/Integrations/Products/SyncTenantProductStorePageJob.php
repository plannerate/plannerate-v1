<?php

namespace App\Jobs\Integrations\Products;

use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\IntegrationServiceResolver;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class SyncTenantProductStorePageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $integrationId,
        public string $referenceDate,
        public string $storeId,
        public string $empresa,
        public int $page,
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

        $store = Store::query()
            ->whereKey($this->storeId)
            ->where('tenant_id', $integration->tenant_id)
            ->whereNull('deleted_at')
            ->first();

        if (! $store) {
            return;
        }

        $processing = $configNormalizer->normalize($integration)['processing'];
        $productsService = $integrationServiceResolver->resolveProductsService($integration);

        $productsService->fetchProducts($integration, [
            'date' => Carbon::parse($this->referenceDate)->toDateString(),
            'store_id' => (string) $store->id,
            'empresa' => $this->empresa,
            'page' => max(1, $this->page),
            'page_size' => (int) ($processing['products_page_size'] ?? 1000),
            'partner_key' => (string) ($processing['partner_key'] ?? ''),
        ]);
    }
}
