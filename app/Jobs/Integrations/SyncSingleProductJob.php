<?php

namespace App\Jobs\Integrations;

use App\Events\Tenant\ProductSalesSynced;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Lookup\SingleProductFetchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\TenantAware;
use Throwable;

/**
 * Busca sob demanda das vendas de UM produto em UMA loja (e, opcionalmente, dos
 * dados cadastrais do produto) na API do tenant, gravando via upsert.
 *
 * Roda em fila para não bloquear a requisição. Ao terminar, dispara
 * ProductSalesSynced no canal do tenant → o frontend recarrega a página do
 * produto. TenantAware garante que o Spatie restaura o tenant antes do handle(),
 * então os models tenant resolvem a conexão correta.
 */
class SyncSingleProductJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public string $tenantId,
        public string $productId,
        public string $storeId,
        public bool $updateProduct = false,
        public ?string $userId = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
    ) {
        $this->onQueue('imports-fetch');
    }

    public function handle(SingleProductFetchService $service): void
    {
        $integration = TenantIntegration::query()
            ->with('api')
            ->where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->first();

        if ($integration === null || $integration->api === null) {
            Log::warning('SyncSingleProductJob: sem integração', [
                'tenant_id' => $this->tenantId,
                'integration_found' => $integration !== null,
                'api_found' => $integration !== null && $integration->api !== null,
                'total_active' => TenantIntegration::query()->where('tenant_id', $this->tenantId)->where('is_active', true)->count(),
            ]);

            $this->broadcast('failed', message: __('app.tenant.products.sync.no_integration'));

            return;
        }

        $product = Product::query()->find($this->productId);
        $store = Store::query()->find($this->storeId);

        if ($product === null || $store === null) {
            $this->broadcast('failed', message: __('app.tenant.products.sync.not_found'));

            return;
        }

        $result = $service->fetch($integration, $product, $store, $this->updateProduct, $this->dateFrom, $this->dateTo);

        if (! $result->configured) {
            $this->broadcast('failed', message: __('app.tenant.products.sync.no_integration'));

            return;
        }

        if ($result->hasErrors() && ! $result->persistedAnything()) {
            $this->broadcast('failed', $result->productsPersisted, $result->salesPersisted, $result->errors[0]);

            return;
        }

        $this->broadcast('success', $result->productsPersisted, $result->salesPersisted);
    }

    public function failed(Throwable $e): void
    {
        // No failed() o tenant pode não estar restaurado — re-seleciona antes de transmitir.
        Tenant::query()->find($this->tenantId)?->makeCurrent();

        ProductSalesSynced::dispatch($this->tenantId, $this->productId, 'failed', 0, 0, $e->getMessage());
    }

    private function broadcast(string $status, int $products = 0, int $sales = 0, ?string $message = null): void
    {
        ProductSalesSynced::dispatch($this->tenantId, $this->productId, $status, $products, $sales, $message);
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return [
            'integration',
            'sync-single',
            "tenant:{$this->tenantId}",
            "product:{$this->productId}",
        ];
    }
}
