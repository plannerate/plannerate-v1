<?php

namespace App\Services\Integrations\Support;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\IntegrationApiConfigResolver;
use App\Services\Integrations\Support\ProductFieldMaps\ProductFieldMapRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PersistImportedProductsService
{
    public function __construct(
        private readonly DeterministicIdGenerator $deterministicIdGenerator,
        private readonly FieldResolver $fieldResolver,
        private readonly ProductFieldMapRegistry $productFieldMapRegistry,
        private readonly IntegrationApiConfigResolver $configResolver,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function persist(
        TenantIntegration $integration,
        string $provider,
        array $items,
        ?Store $store = null,
    ): void {
        if ($items === []) {
            return;
        }

        $tenant = $integration->tenant;
        if (! $tenant instanceof Tenant) {
            Log::warning('Persistência de produtos ignorada: tenant não encontrado.', [
                'integration_id' => (string) $integration->id,
                'provider' => $provider,
            ]);

            return;
        }

        $tenant->execute(function () use ($integration, $provider, $items, $store, $tenant): void {
            $this->persistInTenantContext($integration, $tenant, $provider, $items, $store);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function persistInTenantContext(
        TenantIntegration $integration,
        Tenant $tenant,
        string $provider,
        array $items,
        ?Store $store,
    ): void {
        $connectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
        $tenantId = (string) $tenant->id;
        $now = Carbon::now();

        $productsRows = [];
        $invalidCount = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $mapped = $this->mapItem($provider, $item);
            if (! $this->passesProviderValidation($provider, $mapped, $item)) {
                $invalidCount++;

                continue;
            }

            $normalized = ProductNormalizedData::fromMapped(
                $mapped,
                $item,
                $this->requiredStringFieldsByProvider($provider),
            );
            if (! $normalized instanceof ProductNormalizedData) {
                $invalidCount++;

                continue;
            }

            $productId = $this->deterministicIdGenerator->productId($tenantId, $normalized->ean, $normalized->codigoErp);

            $productsRows[] = [
                'id' => $productId,
                'tenant_id' => $tenantId,
                'name' => $normalized->name,
                'ean' => $normalized->ean,
                'codigo_erp' => $normalized->codigoErp,
                'brand' => $normalized->brand,
                'subbrand' => $normalized->subbrand,
                'description' => $normalized->description,
                'auxiliary_description' => $normalized->auxiliaryDescription,
                'additional_information' => $normalized->additionalInformation,
                'reference' => $normalized->reference,
                'color' => $normalized->color,
                'fragrance' => $normalized->fragrance,
                'flavor' => $normalized->flavor,
                'packaging_type' => $normalized->packagingType,
                'packaging_size' => $normalized->packagingSize,
                'measurement_unit' => $normalized->measurementUnit,
                'unit_measure' => $normalized->unitMeasure,
                'sortiment_attribute' => $normalized->sortimentAttribute,
                'current_stock' => $normalized->currentStock,
                'last_purchase_date' => $normalized->lastPurchaseDate,
                'sales_status' => $normalized->salesStatus,
                'status' => 'synced',
                'sync_source' => (string) ($integration->integration_type ?: $provider),
                'sync_at' => $now,
                'deleted_at' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ];
        }

        if ($productsRows === []) {
            Log::warning('Persistência de produtos não encontrou itens válidos.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'items_count' => count($items),
                'invalid_count' => $invalidCount,
            ]);

            return;
        }

        $productsRows = array_values(collect($productsRows)->keyBy('id')->all());

        DB::connection($connectionName)->table('products')->upsert(
            $productsRows,
            ['id'],
            [
                'tenant_id',
                'name',
                'ean',
                'codigo_erp',
                'brand',
                'subbrand',
                'description',
                'auxiliary_description',
                'additional_information',
                'reference',
                'color',
                'fragrance',
                'flavor',
                'packaging_type',
                'packaging_size',
                'measurement_unit',
                'unit_measure',
                'sortiment_attribute',
                'current_stock',
                'last_purchase_date',
                'sales_status',
                'status',
                'sync_source',
                'sync_at',
                'deleted_at',
                'updated_at',
            ],
        );

        $storeIds = $this->resolveStoreIds($tenantId, $store);
        if ($storeIds !== []) {
            $this->upsertProductStore($connectionName, $tenantId, $storeIds, $productsRows, $now);
        }

        Log::info('Persistência de produtos concluída.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'items_count' => count($items),
            'invalid_count' => $invalidCount,
            'upserted_products' => count($productsRows),
            'linked_stores' => count($storeIds),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function resolveStoreIds(string $tenantId, ?Store $store): array
    {
        if ($store instanceof Store && (string) $store->id !== '') {
            return [(string) $store->id];
        }

        return Store::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $storeIds
     * @param  array<int, array<string, mixed>>  $productsRows
     */
    private function upsertProductStore(
        string $connectionName,
        string $tenantId,
        array $storeIds,
        array $productsRows,
        Carbon $now,
    ): void {
        $pivotRows = [];

        foreach ($productsRows as $productRow) {
            foreach ($storeIds as $storeId) {
                $pivotRows[] = [
                    'id' => (string) str()->ulid(),
                    'tenant_id' => $tenantId,
                    'product_id' => (string) $productRow['id'],
                    'store_id' => $storeId,
                    'last_synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::connection($connectionName)->table('product_store')->upsert(
            $pivotRows,
            ['tenant_id', 'product_id', 'store_id'],
            ['last_synced_at', 'updated_at'],
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapItem(string $provider, array $item): array
    {
        $map = $this->fieldMap($provider, 'products', $this->productFieldMapRegistry->resolve($provider)->fields());
        $mapped = [];
        $expressions = [];

        foreach ($map as $field => $paths) {
            if (is_array($paths) && is_string($paths['expression'] ?? null)) {
                $expressions[$field] = $paths;

                continue;
            }

            $mapped[$field] = $this->fieldResolver->resolve($item, $paths);
        }

        foreach ($expressions as $field => $definition) {
            $mapped[$field] = $this->fieldResolver->resolveExpression(
                $mapped,
                (string) $definition['expression'],
                is_array($definition['transforms'] ?? null) ? $definition['transforms'] : [],
                $item,
            );
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function fieldMap(string $provider, string $resource, array $fallback): array
    {
        $providerConfig = $this->configResolver->provider($provider);
        $requests = is_array($providerConfig['requests'] ?? null) ? $providerConfig['requests'] : [];
        $resourceConfig = is_array($requests[$resource] ?? null) ? $requests[$resource] : [];
        $configuredRows = is_array($resourceConfig['field_map'] ?? null) ? $resourceConfig['field_map'] : [];

        $configured = [];
        foreach ($configuredRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $target = is_string($row['target'] ?? null) ? trim($row['target']) : '';
            $source = is_string($row['source'] ?? null) ? trim($row['source']) : '';

            if ($target === '' || $source === '') {
                continue;
            }

            $configured[$target] = [
                'transforms' => collect($row['transforms'] ?? [])
                    ->filter(fn (mixed $transform): bool => is_string($transform) && trim($transform) !== '')
                    ->values()
                    ->all(),
            ];

            if ($this->isExpression($source)) {
                $configured[$target]['expression'] = $source;
            } else {
                $configured[$target]['paths'] = [$source];
            }
        }

        return $configured === [] ? $fallback : array_replace($fallback, $configured);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $raw
     */
    private function passesProviderValidation(string $provider, array $mapped, array $raw): bool
    {
        return $this->productFieldMapRegistry->resolve($provider)->passesValidation($mapped, $raw);
    }

    /**
     * @return list<string>
     */
    private function requiredStringFieldsByProvider(string $provider): array
    {
        return match ($provider) {
            'sysmo', 'gescooper' => ['name'],
            default => [],
        };
    }

    private function isExpression(string $source): bool
    {
        return preg_match('/\s[+\-*\/]\s|[()]/', $source) === 1;
    }
}
