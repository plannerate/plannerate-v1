<?php

namespace App\Services\Integrations\Importers;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class IntegrationImporter
{
    public function __construct(
        private readonly SysmoImporter $sysmoImporter,
        private readonly GescooperImporter $gescooperImporter,
    ) {}

    public function importSales(TenantIntegration $integration): void
    {
        $importer = $this->resolve($integration);

        $this->forEachStoreScope($integration, function (?Store $store) use ($importer, $integration): void {
            $importer->importSales($integration, $store);
        });
    }

    public function importProducts(TenantIntegration $integration): void
    {
        $importer = $this->resolve($integration);

        $this->forEachStoreScope($integration, function (?Store $store) use ($importer, $integration): void {
            $importer->importProducts($integration, $store);
        });
    }

    private function resolve(TenantIntegration $integration): ClientApiImporter
    {
        return match ((string) $integration->integration_type) {
            'sysmo' => $this->sysmoImporter,
            'gescooper' => $this->gescooperImporter,
            default => throw new InvalidArgumentException(sprintf(
                'Importador não configurado para integração [%s].',
                (string) $integration->integration_type,
            )),
        };
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

    /**
     * @return Collection<int, Store>
     */
    private function storesWithDocument(Tenant $tenant): Collection
    {
        return $tenant->execute(fn (): Collection => Store::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->whereNotNull('document')
            ->where('document', '<>', '')
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'document']));
    }
}
