<?php

namespace App\Jobs\Integrations\Imports;

use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\ImportBatchPayloadStore;
use App\Services\Integrations\Support\PersistImportedProductsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ProcessImportedProductsBatchJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    private const PERSIST_CHUNK_SIZE = 500;

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

    public function __construct(
        string $integrationId,
        string $provider,
        ?string $payloadKey = null,
        ?string $storeId = null,
        array $items = [],
    ) {
        $this->integrationId = $integrationId;
        $this->provider = $provider;
        $this->payloadKey = $payloadKey;
        $this->storeId = $storeId;
        $this->items = $items;

        $this->onQueue('imports');
    }

    public function handle(
        PersistImportedProductsService $persistImportedProductsService,
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
            Log::warning('Processamento de lote de produtos ignorado: integração ativa não encontrada.', [
                'integration_id' => $this->integrationId,
                'provider' => $this->provider,
                'store_id' => $this->storeId,
            ]);

            return;
        }

        $store = null;
        if (is_string($this->storeId) && $this->storeId !== '') {
            $store = new Store;
            $store->id = $this->storeId;
        }

        foreach (array_chunk($items, self::PERSIST_CHUNK_SIZE) as $chunk) {
            $persistImportedProductsService->persist(
                integration: $integration,
                provider: $this->provider,
                items: $chunk,
                store: $store,
            );
        }
    }
}
