<?php

namespace App\Services\Integrations\Importers;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IntegrationImporter
{
    public function __construct(
        private readonly GenericIntegrationImporter $genericImporter,
    ) {}

    public function importSales(TenantIntegration $integration, ?Store $store = null): void
    {
        if ($store instanceof Store) {
            $this->genericImporter->importSales($integration, $store);

            return;
        }

        $this->forEachStoreScope($integration, function (?Store $store) use ($integration): void {
            $this->genericImporter->importSales($integration, $store);
        });
    }

    public function importProducts(TenantIntegration $integration, ?Store $store = null): void
    {
        if ($store instanceof Store) {
            $this->genericImporter->importProducts($integration, $store);

            return;
        }

        $this->forEachStoreScope($integration, function (?Store $store) use ($integration): void {
            $this->genericImporter->importProducts($integration, $store);
        });
    }

    private function forEachStoreScope(TenantIntegration $integration, callable $callback): void
    {
        if (! $this->separateByStore($integration)) {
            $callback(null);

            return;
        }

        $tenant = $integration->tenant;
        if (! $tenant instanceof Tenant) {
            Log::warning('Importação por loja ignorada: tenant da integração não encontrado.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
            ]);

            return;
        }

        $stores = $this->storesWithDocument($tenant);
        if ($stores->isEmpty()) {
            Log::warning('Importação por loja ignorada: nenhuma loja com documento encontrada.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
            ]);

            return;
        }

        $stores->each(fn (Store $store): mixed => $callback($store));
    }

    private function separateByStore(TenantIntegration $integration): bool
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : [];

        return (bool) ($processing['separate_by_store'] ?? false);
    }

    private function storesWithDocument(Tenant $tenant): Collection
    {
        return $tenant->execute(fn (): Collection => Store::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('document')
            ->where('document', '<>', '')
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'document']));
    }
}
