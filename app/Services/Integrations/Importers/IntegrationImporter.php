<?php

namespace App\Services\Integrations\Importers;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use App\Services\Integrations\Support\ResolvedIntegrationConfig;
use Illuminate\Support\Collection;

class IntegrationImporter
{
    public function __construct(
        private readonly GenericIntegrationImporter $genericImporter,
        private readonly ?ResolvedIntegrationConfigResolver $configResolver = null,
    ) {}

    public function importResource(ResolvedIntegrationConfig|TenantIntegration $config, string $resource, string $targetTable): void
    {
        $config = $this->resolveConfig($config);

        $this->forEachStoreScope($config, function (Store $store) use ($config, $resource, $targetTable): void {
            $this->genericImporter->importResource($config, $resource, $targetTable, $store);
        });
    }

    private function forEachStoreScope(ResolvedIntegrationConfig $config, callable $callback): void
    {
        $tenant = $config->integration->tenant;
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->storesWithDocument($tenant)->each(fn (Store $store): mixed => $callback($store));
    }

    private function storesWithDocument(Tenant $tenant): Collection
    {
        return $tenant->execute(fn (): Collection => Store::query()
            ->where('tenant_id', $tenant->id)
            ->published()
            ->whereNotNull('document')
            ->where('document', '<>', '')
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'document']));
    }

    private function resolveConfig(ResolvedIntegrationConfig|TenantIntegration $config): ResolvedIntegrationConfig
    {
        if ($config instanceof ResolvedIntegrationConfig) {
            return $config;
        }

        return ($this->configResolver ?? app(ResolvedIntegrationConfigResolver::class))->resolve($config);
    }
}
