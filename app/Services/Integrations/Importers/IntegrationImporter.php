<?php

namespace App\Services\Integrations\Importers;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Collection; 

class IntegrationImporter
{
    public function __construct(
        private readonly GenericIntegrationImporter $genericImporter,
    ) {}

    public function importResource(TenantIntegration $integration, string $resource, string $targetTable): void
    {
        $this->forEachStoreScope($integration, function (?Store $store) use ($integration, $resource, $targetTable): void {
            $this->genericImporter->importResource($integration, $resource, $targetTable, $store);
        });
    }

    private function forEachStoreScope(TenantIntegration $integration, callable $callback): void
    {
      
        $tenant = $integration->tenant;
        if (! $tenant instanceof Tenant) {
            return;
        }

        $stores = $this->storesWithDocument($tenant);
        if ($stores->isEmpty()) {
            return;
        }

        $stores->each(fn(Store $store): mixed => $callback($store));
    }

    private function separateByStore(TenantIntegration $integration): bool
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : [];

        return (bool) ($processing['separate_by_store'] ?? false);
    }

    private function storesWithDocument(Tenant $tenant): Collection
    {
        return $tenant->execute(fn(): Collection => Store::query()
            ->where('tenant_id', $tenant->id)
            ->published()
            ->whereNotNull('document')
            ->where('document', '<>', '')
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'document']));
    }
}
