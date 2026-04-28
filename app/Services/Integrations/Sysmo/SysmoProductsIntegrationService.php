<?php

namespace App\Services\Integrations\Sysmo;

use App\Models\EanReference;
use App\Models\Product;
use App\Models\TenantIntegration;
use App\Services\Integrations\Contracts\ProductsIntegrationService;
use App\Services\Integrations\ExternalApiBaseService;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use Illuminate\Support\Carbon;

class SysmoProductsIntegrationService implements ProductsIntegrationService
{
    public function __construct(
        private readonly ExternalApiBaseService $externalApiBaseService,
        private readonly SysmoEndpoints $sysmoEndpoints,
        private readonly SysmoProductsResponseMapper $responseMapper,
        private readonly DeterministicIdGenerator $deterministicIdGenerator,
    ) {}

    public function fetchProducts(TenantIntegration $integration, array $filters = []): array
    {
        $requestBody = [
            'pagina' => (int) ($filters['page'] ?? 1),
            'tamanho_pagina' => (int) ($filters['page_size'] ?? 1000),
            'partner_key' => (string) ($filters['partner_key'] ?? ''),
        ];

        if (is_string($filters['date'] ?? null) && $filters['date'] !== '') {
            $requestBody['data_ultima_alteracao'] = $filters['date'];
        }

        $response = $this->externalApiBaseService->request(
            integration: $integration,
            method: strtoupper((string) $integration->http_method),
            endpoint: $this->sysmoEndpoints->get('products'),
            body: $requestBody,
        );

        $mappedItems = $this->responseMapper->mapMany($this->extractItems($response->json()));

        $this->persistMappedProducts(
            tenantId: (string) $integration->tenant_id,
            source: (string) ($integration->integration_type ?: 'sysmo'),
            mappedItems: $mappedItems,
        );

        return $mappedItems;
    }

    /**
     * @param  array<int, array<string, mixed>>  $mappedItems
     */
    public function persistMappedProducts(string $tenantId, string $source, array $mappedItems): void
    {
        if ($tenantId === '' || $mappedItems === []) {
            return;
        }

        $eanValues = [];
        foreach ($mappedItems as $item) {
            $ean = $this->normalizeEan($item['ean'] ?? null);
            if ($ean !== null) {
                $eanValues[] = $ean;
            }
        }

        $references = EanReference::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('ean', array_values(array_unique($eanValues)))
            ->get()
            ->keyBy('ean');

        foreach ($mappedItems as $item) {
            $normalizedEan = $this->normalizeEan($item['ean'] ?? null);
            $reference = $normalizedEan !== null ? $references->get($normalizedEan) : null;
            $externalId = $this->normalizeString($item['external_id'] ?? null);

            $unique = $normalizedEan !== null
                ? ['tenant_id' => $tenantId, 'ean' => $normalizedEan]
                : ($externalId !== null
                    ? ['tenant_id' => $tenantId, 'codigo_erp' => $externalId]
                    : null);

            if ($unique === null) {
                continue;
            }

            $product = Product::withTrashed()->firstOrNew($unique);

            if (! $product->exists) {
                $product->id = $this->generateProductId(
                    ean: $normalizedEan,
                    tenantId: $tenantId,
                    codigoErp: $externalId,
                );
            }

            $product->fill([
                'tenant_id' => $tenantId,
                'name' => $this->normalizeString($item['name'] ?? null) ?? $reference?->reference_description,
                'ean' => $normalizedEan,
                'codigo_erp' => $externalId,
                'category_id' => $reference?->category_id,
                'description' => $reference?->reference_description,
                'brand' => $reference?->brand ?? $this->normalizeString($item['brand'] ?? null),
                'subbrand' => $reference?->subbrand,
                'packaging_type' => $reference?->packaging_type,
                'packaging_size' => $reference?->packaging_size,
                'measurement_unit' => $reference?->measurement_unit,
                'unit_measure' => $this->normalizeString($item['unit'] ?? null),
                'sales_status' => $this->normalizeString($item['status'] ?? null),
                'status' => 'synced',
                'sync_source' => $source,
                'sync_at' => Carbon::now(),
                'deleted_at' => null,
            ]);

            $product->save();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractItems(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if (is_array($payload['data'] ?? null)) {
            /** @var array<int, array<string, mixed>> $data */
            $data = array_values(array_filter($payload['data'], 'is_array'));

            return $data;
        }

        if (is_array($payload['items'] ?? null)) {
            /** @var array<int, array<string, mixed>> $items */
            $items = array_values(array_filter($payload['items'], 'is_array'));

            return $items;
        }

        if (is_array($payload['dados'] ?? null)) {
            /** @var array<int, array<string, mixed>> $dados */
            $dados = array_values(array_filter($payload['dados'], 'is_array'));

            return $dados;
        }

        if (array_is_list($payload)) {
            /** @var array<int, array<string, mixed>> $list */
            $list = array_values(array_filter($payload, 'is_array'));

            return $list;
        }

        return [];
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeEan(mixed $value): ?string
    {
        $ean = $this->normalizeString($value);

        if ($ean === null) {
            return null;
        }

        $normalized = EanReference::normalizeEan($ean);

        return $normalized !== '' ? $normalized : null;
    }

    private function generateProductId(?string $ean, string $tenantId, ?string $codigoErp): string
    {
        return $this->deterministicIdGenerator->productId($tenantId, $ean, $codigoErp);
    }
}
