<?php

namespace App\Jobs\Integrations\Imports;

use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\PersistImportedProductsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ProcessImportedProductsBatchJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        public string $integrationId,
        public string $provider,
        public array $items,
        public ?string $storeId = null,
    ) {
        $this->onQueue('imports');
    }

    public function handle(PersistImportedProductsService $persistImportedProductsService): void
    {
        if ($this->items === []) {
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

        $persistImportedProductsService->persist(
            integration: $integration,
            provider: $this->provider,
            items: $this->items,
            store: $store,
        );
    }
}
