<?php

namespace App\Services\Integrations\GesCooper;

use App\Models\EanReference;
use App\Models\TenantIntegration;
use App\Services\Integrations\Contracts\ProductsIntegrationService;
use App\Services\Integrations\GesCooper\Concerns\ExtractsGesCooperPayloadItems;
use App\Services\Integrations\GesCooper\Concerns\NormalizesGesCooperValues;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use App\Services\Integrations\Support\SyncSalesProductReferencesService;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GesCooperProductsIntegrationService implements ProductsIntegrationService
{
    use ExtractsGesCooperPayloadItems;
    use NormalizesGesCooperValues;

    public function __construct(
        private readonly GesCooperAuthService $authService,
        private readonly GesCooperEndpoints $endpoints,
        private readonly GesCooperProductsResponseMapper $responseMapper,
        private readonly DeterministicIdGenerator $deterministicIdGenerator,
        private readonly SyncSalesProductReferencesService $syncSalesProductReferencesService,
        private readonly TenantIntegrationConfigNormalizer $configNormalizer,
    ) {}

    public function fetchProducts(TenantIntegration $integration, array $filters = []): array
    {
        $payload = $this->requestProducts($integration, $filters);
        $mappedItems = $this->responseMapper->mapMany($this->extractItemsFromPayload($payload));

        $this->persistMappedProducts(
            tenantId: (string) $integration->tenant_id,
            source: (string) ($integration->integration_type ?: 'gescooper'),
            mappedItems: $mappedItems,
            storeId: is_string($filters['store_id'] ?? null) ? $filters['store_id'] : null,
        );

        return $mappedItems;
    }

    public function discoverProductsTotalPages(TenantIntegration $integration, array $filters = []): int
    {
        $payload = $this->requestProducts($integration, array_merge($filters, ['page' => 1]));
        $lastPage = $payload['pagination']['last_page'] ?? 1;

        return max(1, (int) $lastPage);
    }

    /**
     * @param  array<int, array<string, mixed>>  $mappedItems
     */
    public function persistMappedProducts(
        string $tenantId,
        string $source,
        array $mappedItems,
        ?string $storeId = null,
    ): void {
        if ($tenantId === '' || $mappedItems === []) {
            return;
        }

        $eanValues = [];
        foreach ($mappedItems as $item) {
            $ean = $this->normalizeAndValidateEan($item['ean'] ?? null);
            if ($ean !== null) {
                $eanValues[] = $ean;
            }
        }

        $references = EanReference::query()
            ->whereIn('ean', array_values(array_unique($eanValues)))
            ->get()
            ->keyBy('ean');

        $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
        $now = Carbon::now();
        $productsRows = [];
        $invalidItemsCount = 0;
        $invalidItemsExamples = [];

        foreach ($mappedItems as $item) {
            $normalizedEan = $this->normalizeAndValidateEan($item['ean'] ?? null);
            $externalId = $this->normalizeCodigoErp($this->normalizeString($item['external_id'] ?? null));

            if ($normalizedEan === null || $externalId === null) {
                $invalidItemsCount++;
                if (count($invalidItemsExamples) < 5) {
                    $invalidItemsExamples[] = [
                        'codigo_erp' => $item['external_id'] ?? null,
                        'ean' => $item['ean'] ?? null,
                    ];
                }

                continue;
            }

            $reference = $references->get($normalizedEan);

            $productId = $this->deterministicIdGenerator->productId($tenantId, $normalizedEan, $externalId);

            $productsRows[] = [
                'id' => $productId,
                'tenant_id' => $tenantId,
                'name' => $this->normalizeString($item['name'] ?? null) ?? $reference?->reference_description,
                'ean' => $normalizedEan,
                'codigo_erp' => $externalId,
                'brand' => $this->normalizeString($item['brand'] ?? null) ?? $reference?->brand,
                'subbrand' => $this->normalizeString($item['subbrand'] ?? null) ?? $reference?->subbrand,
                'description' => $reference?->reference_description,
                'auxiliary_description' => $this->normalizeString($item['auxiliary_description'] ?? null),
                'additional_information' => $this->normalizeString($item['additional_information'] ?? null),
                'reference' => $this->normalizeString($item['reference'] ?? null),
                'color' => $this->normalizeString($item['color'] ?? null),
                'fragrance' => $this->normalizeString($item['fragrance'] ?? null),
                'flavor' => $this->normalizeString($item['flavor'] ?? null),
                'height' => $this->normalizeFloat($item['height'] ?? null),
                'width' => $this->normalizeFloat($item['width'] ?? null),
                'depth' => $this->normalizeFloat($item['depth'] ?? null),
                'packaging_type' => $this->normalizeString($item['packaging_type'] ?? null) ?? $reference?->packaging_type,
                'packaging_size' => $this->normalizeString($item['packaging_size'] ?? null) ?? $reference?->packaging_size,
                'measurement_unit' => $this->normalizeString($item['unit'] ?? null) ?? $reference?->measurement_unit,
                'sortiment_attribute' => $this->normalizeString($item['sortiment_attribute'] ?? null),
                'current_stock' => $this->normalizeFloat($item['current_stock'] ?? null),
                'last_purchase_date' => $this->normalizeDate($item['last_purchase_date'] ?? null),
                'sales_status' => $this->normalizeString($item['status'] ?? null),
                'status' => 'synced',
                'sync_source' => $source,
                'sync_at' => $now,
                'deleted_at' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ];
        }

        if ($invalidItemsCount > 0) {
            Log::warning('GesCooper: sincronizacao de produtos ignorou itens sem EAN ou codigo_erp valido.', [
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'invalid_items_count' => $invalidItemsCount,
                'mapped_items_count' => count($mappedItems),
                'invalid_items_examples' => $invalidItemsExamples,
            ]);
        }

        if ($productsRows === []) {
            Log::warning('GesCooper: sincronizacao de produtos nao persistiu registros.', [
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'items_count' => count($mappedItems),
            ]);

            return;
        }

        DB::connection($tenantConnectionName)->table('products')->upsert(
            $productsRows,
            ['id'],
            [
                'tenant_id', 'name', 'ean', 'codigo_erp', 'brand', 'subbrand',
                'description', 'auxiliary_description', 'additional_information',
                'reference', 'color', 'fragrance', 'flavor',
                'height', 'width', 'depth',
                'packaging_type', 'packaging_size', 'measurement_unit',
                'sortiment_attribute', 'current_stock', 'last_purchase_date',
                'sales_status', 'status', 'sync_source', 'sync_at', 'deleted_at', 'updated_at',
            ]
        );

        if ($storeId !== null && $storeId !== '') {
            $pivotRows = [];

            foreach ($productsRows as $productRow) {
                $pivotRows[] = [
                    'id' => (string) str()->ulid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $productRow['id'],
                    'store_id' => $storeId,
                    'last_synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::connection($tenantConnectionName)->table('product_store')->upsert(
                $pivotRows,
                ['tenant_id', 'product_id', 'store_id'],
                ['last_synced_at', 'updated_at']
            );
        }
    }

    public function finalizePersistedProductsSync(string $tenantId): void
    {
        if ($tenantId === '') {
            return;
        }

        $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
        $now = Carbon::now();
        $connection = DB::connection($tenantConnectionName);

        $products = $connection->table('products')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->get(['id', 'ean']);

        $eanValues = $products
            ->pluck('ean')
            ->filter(fn (mixed $ean): bool => is_string($ean) && trim($ean) !== '')
            ->map(fn (string $ean): string => trim($ean))
            ->unique()
            ->values()
            ->all();

        if ($eanValues !== []) {
            $references = EanReference::query()
                ->whereIn('ean', $eanValues)
                ->get()
                ->keyBy('ean');

            foreach ($products as $product) {
                $ean = is_string($product->ean ?? null) ? trim($product->ean) : null;
                if ($ean === null || $ean === '') {
                    continue;
                }

                $reference = $references->get($ean);
                if (! $reference instanceof EanReference) {
                    continue;
                }

                $updates = [];
                foreach ([
                    'description' => $reference->reference_description,
                    'brand' => $reference->brand,
                    'subbrand' => $reference->subbrand,
                    'packaging_type' => $reference->packaging_type,
                    'packaging_size' => $reference->packaging_size,
                    'measurement_unit' => $reference->measurement_unit,
                ] as $column => $value) {
                    if ($value !== null) {
                        $updates[$column] = $value;
                    }
                }

                if ($updates === []) {
                    continue;
                }

                $updates['updated_at'] = $now;

                $connection->table('products')
                    ->where('id', (string) $product->id)
                    ->update($updates);
            }
        }

        $this->syncSalesProductReferencesService->syncAllByCodigoErp(
            tenantConnectionName: $tenantConnectionName,
            tenantId: $tenantId,
            now: $now,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function requestProducts(TenantIntegration $integration, array $filters): array
    {
        $normalized = $this->configNormalizer->normalize($integration);
        $connection = $normalized['connection'];
        $processing = $normalized['processing'];
        $baseUrl = rtrim($connection['base_url'], '/');

        $token = $this->authService->getToken($integration);

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout($connection['timeout'])
            ->connectTimeout($connection['connect_timeout'])
            ->withOptions(['verify' => $connection['verify_ssl']])
            ->get($baseUrl.'/'.$this->endpoints->get('products'), [
                'pagina' => (int) ($filters['page'] ?? 1),
                'registros_por_pagina' => (int) ($filters['page_size'] ?? $processing['products_page_size']),
                'api-version' => '1.0',
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'GesCooper: falha ao buscar produtos. HTTP %s: %s',
                $response->status(),
                mb_substr($response->body(), 0, 500),
            ));
        }

        return is_array($response->json()) ? $response->json() : [];
    }

    private function normalizeAndValidateEan(mixed $value): ?string
    {
        $ean = $this->normalizeString($value);
        if ($ean === null) {
            return null;
        }

        $normalized = EanReference::normalizeEan($ean);

        if ($normalized === '' || strlen($normalized) > 13) {
            return null;
        }

        return $normalized;
    }
}
