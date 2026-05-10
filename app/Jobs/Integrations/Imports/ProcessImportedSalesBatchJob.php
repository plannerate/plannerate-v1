<?php

namespace App\Jobs\Integrations\Imports;

use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\ImportBatchPayloadStore;
use App\Services\Integrations\Support\PersistImportedSalesService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ProcessImportedSalesBatchJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public string $integrationId = '';

    public string $provider = '';

    public ?string $payloadKey = null;

    /**
     * Backward compatibility for older queued payloads.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $items = [];

    public ?string $storeId = null;

    public ?string $storeDocument = null;

    public function __construct(
        string $integrationId,
        string $provider,
        ?string $payloadKey = null,
        ?string $storeId = null,
        ?string $storeDocument = null,
        array $items = [],
    ) {
        $this->integrationId = $integrationId;
        $this->provider = $provider;
        $this->payloadKey = $payloadKey;
        $this->storeId = $storeId;
        $this->storeDocument = $storeDocument;
        $this->items = $items;

        $this->onQueue('imports');
    }

    public function handle(
        PersistImportedSalesService $persistImportedSalesService,
        ImportBatchPayloadStore $importBatchPayloadStore,
    ): void {
        $items = is_string($this->payloadKey) && $this->payloadKey !== ''
            ? $importBatchPayloadStore->pull($this->payloadKey)
            : $this->items;

        if ($items === []) {
            return;
        }

        $integration = TenantIntegration::query()
            ->with('tenant')
            ->whereKey($this->integrationId)
            ->where('is_active', true)
            ->first();

        if (! $integration instanceof TenantIntegration) {
            Log::warning('Processamento de lote de vendas ignorado: integração ativa não encontrada.', [
                'integration_id' => $this->integrationId,
                'provider' => $this->provider,
                'store_id' => $this->storeId,
            ]);

            return;
        }

        $store = null;
        if ((is_string($this->storeId) && $this->storeId !== '') || (is_string($this->storeDocument) && $this->storeDocument !== '')) {
            $store = new Store;
            $store->id = $this->storeId;
            $store->document = $this->storeDocument;
        }

        $persistImportedSalesService->persist(
            integration: $integration,
            provider: $this->provider,
            items: $items,
            store: $store,
        );
    }
}
