<?php

namespace App\Jobs\Integrations\Products;

use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\IntegrationServiceResolver;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\TenantAware;

class SyncTenantProductStorePageJob implements ShouldQueue, TenantAware
{
    use Queueable;

    private const MAX_PROGRESSIVE_PAGE = 500;

    public int $timeout = 300;

    public function __construct(
        public string $integrationId,
        public string $referenceDate,
        public string $storeId,
        public string $empresa,
        public int $page,
        public int $pageSize = 1000,
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

        $store = $this->storeId !== ''
            ? Store::query()
                ->whereKey($this->storeId)
                ->where('tenant_id', $integration->tenant_id)
                ->whereNull('deleted_at')
                ->first()
            : null;

        if ($this->storeId !== '' && ! $store) {
            return;
        }

        $processing = $configNormalizer->normalize($integration)['processing'];
        $productsService = $integrationServiceResolver->resolveProductsService($integration);
        $page = max(1, $this->page);
        $pageSize = $this->pageSize > 0 ? $this->pageSize : (int) ($processing['products_page_size'] ?? 1000);
        $referenceDate = Carbon::parse($this->referenceDate)->toDateString();

        $items = $productsService->fetchProducts($integration, [
            'date' => $this->fullSync ? null : $referenceDate,
            'store_id' => $store !== null ? (string) $store->id : null,
            'store_document' => $store?->document,
            'empresa' => $this->empresa !== '' ? $this->empresa : null,
            'page' => $page,
            'page_size' => $pageSize,
            'partner_key' => (string) ($processing['partner_key'] ?? ''),
        ]);

        $itemsCount = count($items);

        if ($itemsCount >= $pageSize && $page < self::MAX_PROGRESSIVE_PAGE) {
            SyncTenantProductStorePageJob::dispatch(
                integrationId: (string) $integration->id,
                referenceDate: $referenceDate,
                storeId: $this->storeId,
                empresa: $this->empresa,
                page: $page + 1,
                pageSize: $pageSize,
                fullSync: $this->fullSync,
            );
        }

        if ($page >= self::MAX_PROGRESSIVE_PAGE && $itemsCount >= $pageSize) {
            Log::warning('Products page sync reached progressive page safety limit.', [
                'integration_id' => $integration->id,
                'tenant_id' => $integration->tenant_id,
                'store_id' => $this->storeId,
                'empresa' => $this->empresa,
                'reference_date' => $referenceDate,
                'page' => $page,
                'items_count' => $itemsCount,
                'page_size' => $pageSize,
            ]);
        }
    }
}
