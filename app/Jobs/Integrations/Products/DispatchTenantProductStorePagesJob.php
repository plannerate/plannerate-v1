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

class DispatchTenantProductStorePagesJob implements ShouldQueue, TenantAware
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
            Log::warning('Products pages dispatch skipped: integration not found or inactive.', [
                'integration_id' => $this->integrationId,
                'store_id' => $this->storeId,
                'reference_date' => $this->referenceDate,
            ]);

            return;
        }

        $store = Store::query()
            ->whereKey($this->storeId)
            ->where('tenant_id', $integration->tenant_id)
            ->whereNull('deleted_at')
            ->first();

        if (! $store) {
            Log::warning('Products pages dispatch skipped: store not found.', [
                'integration_id' => $integration->id,
                'tenant_id' => $integration->tenant_id,
                'store_id' => $this->storeId,
                'reference_date' => $this->referenceDate,
            ]);

            return;
        }

        $processing = $configNormalizer->normalize($integration)['processing'];
        $empresa = $this->resolveEmpresaForStore($store->code, $store->document, $processing);

        if ($empresa === null) {
            Log::warning('Products pages dispatch skipped: empresa not resolved.', [
                'integration_id' => $integration->id,
                'tenant_id' => $integration->tenant_id,
                'store_id' => $store->id,
                'store_code' => $store->code,
                'store_document' => $store->document,
                'reference_date' => $this->referenceDate,
            ]);

            return;
        }

        $pageSize = (int) ($processing['products_page_size'] ?? 1000);
        $date = Carbon::parse($this->referenceDate)->toDateString();
        Log::info('Products pages dispatch started.', [
            'integration_id' => $integration->id,
            'tenant_id' => $integration->tenant_id,
            'store_id' => $store->id,
            'store_code' => $store->code,
            'store_document' => $store->document,
            'empresa' => $empresa,
            'reference_date' => $date,
            'page_size' => $pageSize,
        ]);

        SyncTenantProductStorePageJob::dispatch(
            integrationId: $integration->id,
            referenceDate: $date,
            storeId: $store->id,
            empresa: $empresa,
            page: 1,
        );

        Log::info('Products pages dispatch finished.', [
            'integration_id' => $integration->id,
            'tenant_id' => $integration->tenant_id,
            'store_id' => $store->id,
            'reference_date' => $date,
            'dispatched_pages' => 1,
            'strategy' => 'progressive-pagination',
            'mode' => 'skip-total-pages-discovery',
        ]);
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
}
