<?php

namespace App\Jobs\Integrations\Products;

use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\IntegrationServiceResolver;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class DispatchTenantProductStorePagesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $integrationId,
        public string $referenceDate,
        public string $storeId,
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
        $empresa = $this->resolveEmpresaForStore($store->code, $store->document, $processing);

        if ($empresa === null) {
            return;
        }

        $productsService = $integrationServiceResolver->resolveProductsService($integration);
        $pageSize = (int) ($processing['products_page_size'] ?? 1000);
        $partnerKey = (string) ($processing['partner_key'] ?? '');
        $date = Carbon::parse($this->referenceDate)->toDateString();
        $totalPages = $productsService->discoverProductsTotalPages($integration, [
            'date' => $date,
            'empresa' => $empresa,
            'page_size' => $pageSize,
            'partner_key' => $partnerKey,
        ]);

        for ($page = 1; $page <= $totalPages; $page++) {
            SyncTenantProductStorePageJob::dispatch(
                integrationId: $integration->id,
                referenceDate: $date,
                storeId: $store->id,
                empresa: $empresa,
                page: $page,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $processing
     */
    private function resolveEmpresaForStore(?string $storeCode, ?string $storeDocument, array $processing): ?string
    {
        if (is_string($storeCode) && trim($storeCode) !== '') {
            return trim($storeCode);
        }

        if (is_string($storeDocument) && trim($storeDocument) !== '') {
            return trim($storeDocument);
        }

        $fallbackEmpresa = $processing['empresa'] ?? null;

        if (is_string($fallbackEmpresa) && trim($fallbackEmpresa) !== '') {
            return trim($fallbackEmpresa);
        }

        return null;
    }
}
