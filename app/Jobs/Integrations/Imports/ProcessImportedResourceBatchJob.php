<?php

namespace App\Jobs\Integrations\Imports;

use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\ImportBatchPayloadStore;
use App\Services\Integrations\Support\PersistImportedResourceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ProcessImportedResourceBatchJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    private const PERSIST_CHUNK_SIZE = 500;

    public int $timeout = 1800;

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        public string $integrationId,
        public string $provider,
        public string $resource,
        public string $targetTable,
        public ?string $payloadKey = null,
        public ?string $storeId = null,
        public ?string $storeDocument = null,
        public array $items = [],
    ) {
        $this->onQueue('imports');
    }

    public function handle(
        PersistImportedResourceService $persistImportedResourceService,
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
            Log::warning('Processamento de lote de recurso ignorado: integração ativa não encontrada.', [
                'integration_id' => $this->integrationId,
                'provider' => $this->provider,
                'resource' => $this->resource,
                'target_table' => $this->targetTable,
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

        foreach (array_chunk($items, self::PERSIST_CHUNK_SIZE) as $chunk) {
            $persistImportedResourceService->persist(
                integration: $integration,
                provider: $this->provider,
                resource: $this->resource,
                targetTable: $this->targetTable,
                items: $chunk,
                store: $store,
            );
        }
    }
}
