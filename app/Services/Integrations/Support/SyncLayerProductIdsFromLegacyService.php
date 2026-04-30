<?php

namespace App\Services\Integrations\Support;

use App\Models\TenantIntegration;
use App\Services\Integrations\Sysmo\SysmoSingleProductIntegrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncLayerProductIdsFromLegacyService
{
    public function __construct(
        private readonly SysmoSingleProductIntegrationService $singleProductIntegrationService,
        private readonly DeterministicIdGenerator $deterministicIdGenerator,
    ) {}

    public function countInvalidLayers(string $tenantConnectionName, string $tenantId): int
    {
        return (int) DB::connection($tenantConnectionName)
            ->table('layers as l')
            ->leftJoin('products as p', function ($join) use ($tenantId): void {
                $join->on('p.id', '=', 'l.product_id')
                    ->where('p.tenant_id', '=', $tenantId)
                    ->whereNull('p.deleted_at');
            })
            ->where('l.tenant_id', $tenantId)
            ->whereNotNull('l.product_id')
            ->whereNull('l.deleted_at')
            ->whereNull('p.id')
            ->count('l.id');
    }

    /**
     * @return array{invalid_layers: int, restored_products: int, legacy_matched: int, tenant_matched: int, updated: int, unresolved_legacy: int, unresolved_tenant: int}
     */
    public function sync(string $tenantConnectionName, string $legacyConnectionName, string $tenantId, bool $preview = false): array
    {
        $tenantConnection = DB::connection($tenantConnectionName);
        $legacyConnection = DB::connection($legacyConnectionName);
        $integration = TenantIntegration::query()
            ->where('tenant_id', $tenantId)
            ->where('integration_type', 'sysmo')
            ->where('is_active', true)
            ->first();

        $restoredProducts = $preview
            ? 0
            : $this->restoreSoftDeletedProductsReferencedByLayers($tenantConnectionName, $tenantId);

        $invalidLayers = $this->countInvalidLayers($tenantConnectionName, $tenantId);

        if ($invalidLayers === 0) {
            return [
                'invalid_layers' => 0,
                'restored_products' => $restoredProducts,
                'legacy_matched' => 0,
                'tenant_matched' => 0,
                'updated' => 0,
                'unresolved_legacy' => 0,
                'unresolved_tenant' => 0,
            ];
        }

        $legacyMatched = 0;
        $tenantMatched = 0;
        $updated = 0;
        $unresolvedLegacy = 0;
        $unresolvedTenant = 0;

        $tenantConnection
            ->table('layers as l')
            ->leftJoin('products as p', function ($join) use ($tenantId): void {
                $join->on('p.id', '=', 'l.product_id')
                    ->where('p.tenant_id', '=', $tenantId)
                    ->whereNull('p.deleted_at');
            })
            ->where('l.tenant_id', $tenantId)
            ->whereNotNull('l.product_id')
            ->whereNull('l.deleted_at')
            ->whereNull('p.id')
            ->orderBy('l.id')
            ->leftJoin('segments as sg', 'sg.id', '=', 'l.segment_id')
            ->leftJoin('shelves as sh', 'sh.id', '=', 'sg.shelf_id')
            ->leftJoin('sections as sc', 'sc.id', '=', 'sh.section_id')
            ->select([
                'l.id',
                'l.product_id',
                DB::raw('COALESCE(l.gondola_id, sc.gondola_id) as resolved_gondola_id'),
            ])
            ->chunk(500, function ($rows) use (
                $tenantConnection,
                $legacyConnection,
                $legacyConnectionName,
                $tenantId,
                $preview,
                $integration,
                &$legacyMatched,
                &$tenantMatched,
                &$updated,
                &$unresolvedLegacy,
                &$unresolvedTenant
            ): void {
                $storeDocumentByGondolaId = $this->resolveStoreDocumentByGondolaId(
                    tenantConnectionName: $tenantConnection->getName(),
                    gondolaIds: $rows
                        ->pluck('resolved_gondola_id')
                        ->filter(static fn ($id): bool => is_string($id) && $id !== '')
                        ->unique()
                        ->values()
                        ->all(),
                );

                $legacyIds = $rows
                    ->pluck('product_id')
                    ->filter(static fn ($id) => is_string($id) && $id !== '')
                    ->unique()
                    ->values()
                    ->all();

                if ($legacyIds === []) {
                    return;
                }

                $legacyProductsById = $legacyConnection
                    ->table('products')
                    ->whereIn('id', $legacyIds)
                    ->when(
                        Schema::connection($legacyConnectionName)->hasColumn('products', 'deleted_at'),
                        static fn ($query) => $query->whereNull('deleted_at')
                    )
                    ->whereNotNull('ean')
                    ->select(['id', 'ean', 'description'])
                    ->get()
                    ->keyBy('id');

                $legacyMatched += $legacyProductsById->count();

                $candidateEans = $legacyProductsById
                    ->pluck('ean')
                    ->filter(static fn ($ean) => is_string($ean) && $ean !== '')
                    ->unique()
                    ->values()
                    ->all();

                $tenantProductsByEan = $candidateEans === []
                    ? collect()
                    : $tenantConnection
                        ->table('products')
                        ->where('tenant_id', $tenantId)
                        ->whereIn('ean', $candidateEans)
                        ->whereNull('deleted_at')
                        ->select(['id', 'ean'])
                        ->get()
                        ->keyBy('ean');

                $tenantMatched += $tenantProductsByEan->count();

                $updatesByLayer = [];

                foreach ($rows as $row) {
                    $legacyProduct = $legacyProductsById->get($row->product_id);

                    if (! $legacyProduct || ! is_string($legacyProduct->ean) || $legacyProduct->ean === '') {
                        $unresolvedLegacy++;

                        continue;
                    }

                    $tenantProduct = $tenantProductsByEan->get($legacyProduct->ean);
                    if ((! $tenantProduct || ! is_string($tenantProduct->id) || $tenantProduct->id === '') && ! $preview) {
                        $tenantProduct = $this->resolveProductForLayer(
                            tenantConnectionName: $tenantConnection->getName(),
                            tenantId: $tenantId,
                            integration: $integration,
                            legacyProduct: $legacyProduct,
                            layerProductId: (string) $row->product_id,
                            storeDocument: $storeDocumentByGondolaId[(string) ($row->resolved_gondola_id ?? '')] ?? null,
                        );
                    }

                    if (! $tenantProduct || ! is_string($tenantProduct->id) || $tenantProduct->id === '') {
                        $unresolvedTenant++;

                        continue;
                    }

                    if ($tenantProduct->id === $row->product_id) {
                        continue;
                    }

                    $updatesByLayer[(string) $row->id] = $tenantProduct->id;
                }

                if ($preview || $updatesByLayer === []) {
                    $updated += count($updatesByLayer);

                    return;
                }

                foreach ($updatesByLayer as $layerId => $newProductId) {
                    $tenantConnection
                        ->table('layers')
                        ->where('id', $layerId)
                        ->where('tenant_id', $tenantId)
                        ->update([
                            'product_id' => $newProductId,
                            'updated_at' => now(),
                        ]);
                }

                $updated += count($updatesByLayer);
            });

        return [
            'invalid_layers' => $invalidLayers,
            'restored_products' => $restoredProducts,
            'legacy_matched' => $legacyMatched,
            'tenant_matched' => $tenantMatched,
            'updated' => $updated,
            'unresolved_legacy' => $unresolvedLegacy,
            'unresolved_tenant' => $unresolvedTenant,
        ];
    }

    /**
     * @return array{updated: bool, unresolved_legacy: bool, unresolved_tenant: bool}
     */
    public function syncSingleInvalidLayer(
        string $tenantConnectionName,
        string $legacyConnectionName,
        string $tenantId,
        string $layerId,
        string $legacyProductId,
        ?string $resolvedGondolaId = null,
    ): array {
        $tenantConnection = DB::connection($tenantConnectionName);
        $legacyConnection = DB::connection($legacyConnectionName);

        $integration = TenantIntegration::query()
            ->where('tenant_id', $tenantId)
            ->where('integration_type', 'sysmo')
            ->where('is_active', true)
            ->first();

        $legacyProduct = $legacyConnection
            ->table('products')
            ->where('id', $legacyProductId)
            ->when(
                Schema::connection($legacyConnectionName)->hasColumn('products', 'deleted_at'),
                static fn ($query) => $query->whereNull('deleted_at')
            )
            ->whereNotNull('ean')
            ->select(['id', 'ean', 'description'])
            ->first();

        if (! $legacyProduct || ! is_string($legacyProduct->ean) || $legacyProduct->ean === '') {
            return [
                'updated' => false,
                'unresolved_legacy' => true,
                'unresolved_tenant' => false,
            ];
        }

        $tenantProduct = $tenantConnection
            ->table('products')
            ->where('tenant_id', $tenantId)
            ->where('ean', $legacyProduct->ean)
            ->whereNull('deleted_at')
            ->select(['id', 'ean'])
            ->first();

        $storeDocumentByGondolaId = $this->resolveStoreDocumentByGondolaId(
            tenantConnectionName: $tenantConnectionName,
            gondolaIds: $resolvedGondolaId ? [$resolvedGondolaId] : [],
        );

        if (! $tenantProduct || ! is_string($tenantProduct->id) || $tenantProduct->id === '') {
            $tenantProduct = $this->resolveProductForLayer(
                tenantConnectionName: $tenantConnectionName,
                tenantId: $tenantId,
                integration: $integration,
                legacyProduct: $legacyProduct,
                layerProductId: $legacyProductId,
                storeDocument: $storeDocumentByGondolaId[$resolvedGondolaId ?? ''] ?? null,
            );
        }

        if (! $tenantProduct || ! is_string($tenantProduct->id) || $tenantProduct->id === '') {
            return [
                'updated' => false,
                'unresolved_legacy' => false,
                'unresolved_tenant' => true,
            ];
        }

        if ($tenantProduct->id === $legacyProductId) {
            return [
                'updated' => false,
                'unresolved_legacy' => false,
                'unresolved_tenant' => false,
            ];
        }

        $updated = $tenantConnection
            ->table('layers')
            ->where('id', $layerId)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('product_id', $legacyProductId)
            ->update([
                'product_id' => $tenantProduct->id,
                'updated_at' => now(),
            ]);

        return [
            'updated' => $updated > 0,
            'unresolved_legacy' => false,
            'unresolved_tenant' => false,
        ];
    }

    /**
     * @param  array<int, string>  $gondolaIds
     * @return array<string, string>
     */
    private function resolveStoreDocumentByGondolaId(string $tenantConnectionName, array $gondolaIds): array
    {
        if ($gondolaIds === []) {
            return [];
        }

        return DB::connection($tenantConnectionName)
            ->table('gondolas as g')
            ->join('planograms as p', 'p.id', '=', 'g.planogram_id')
            ->join('stores as s', 's.id', '=', 'p.store_id')
            ->whereIn('g.id', $gondolaIds)
            ->select(['g.id', 's.document'])
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (string) $row->id => $this->normalizeEmpresaValue($row->document) ?? '',
            ])
            ->filter(static fn (string $document): bool => $document !== '')
            ->all();
    }

    /**
     * @param  object{id: string, ean: string, description: ?string}  $legacyProduct
     */
    private function resolveProductForLayer(
        string $tenantConnectionName,
        string $tenantId,
        ?TenantIntegration $integration,
        object $legacyProduct,
        string $layerProductId,
        ?string $storeDocument,
    ): ?object {
        $ean = trim((string) $legacyProduct->ean);
        if ($ean === '') {
            return null;
        }

        if ($integration instanceof TenantIntegration) {
            try {
                $result = $this->singleProductIntegrationService->fetchAndPersist(
                    integration: $integration,
                    produto: $ean,
                    filters: [
                        'empresa' => $storeDocument,
                    ],
                );

                if (($result['found'] ?? false) === true) {
                    $apiProduct = DB::connection($tenantConnectionName)
                        ->table('products')
                        ->where('tenant_id', $tenantId)
                        ->where('ean', $ean)
                        ->whereNull('deleted_at')
                        ->select(['id', 'ean'])
                        ->first();

                    if ($apiProduct) {
                        return $apiProduct;
                    }
                }
            } catch (\Throwable) {
                // Mantém fallback via base legada se API falhar.
            }
        }

        $now = now();
        $codigoErp = $layerProductId;
        $productId = $this->deterministicIdGenerator->productId($tenantId, $ean, $codigoErp);
        $productRow = $this->filterProductAttributesByExistingColumns($tenantConnectionName, [
            'id' => $productId,
            'tenant_id' => $tenantId,
            'name' => is_string($legacyProduct->description ?? null) && trim((string) $legacyProduct->description) !== ''
                ? trim((string) $legacyProduct->description)
                : sprintf('Produto recuperado via base (%s)', $ean),
            'slug' => null,
            'ean' => $ean,
            'codigo_erp' => $codigoErp,
            'status' => 'synced',
            'sync_source' => 'legacy-fallback',
            'resolution_status' => 'legacy_fallback',
            'resolution_details' => json_encode([
                'strategy' => 'legacy_fallback_when_api_not_found',
                'legacy_product_id' => $layerProductId,
                'ean' => $ean,
                'store_document' => $storeDocument,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'additional_information' => 'Produto criado por fallback da base legada após tentativa na API.',
            'sync_at' => $now,
            'deleted_at' => null,
            'updated_at' => $now,
            'created_at' => $now,
        ]);

        if ($productRow === []) {
            return null;
        }

        $updateColumns = array_values(array_diff(array_keys($productRow), ['id']));

        DB::connection($tenantConnectionName)->table('products')->upsert(
            [$productRow],
            ['id'],
            $updateColumns
        );

        return DB::connection($tenantConnectionName)
            ->table('products')
            ->where('tenant_id', $tenantId)
            ->where('id', $productId)
            ->whereNull('deleted_at')
            ->select(['id', 'ean'])
            ->first();
    }

    private function normalizeEmpresaValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return $digits !== '' ? $digits : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function filterProductAttributesByExistingColumns(string $tenantConnectionName, array $attributes): array
    {
        static $columnsByConnection = [];

        if (! isset($columnsByConnection[$tenantConnectionName])) {
            $columnsByConnection[$tenantConnectionName] = Schema::connection($tenantConnectionName)->getColumnListing('products');
        }

        /** @var array<int, string> $columns */
        $columns = $columnsByConnection[$tenantConnectionName];
        $allowedColumns = array_flip($columns);

        return array_intersect_key($attributes, $allowedColumns);
    }

    public function restoreSoftDeletedProductsReferencedByLayers(string $tenantConnectionName, string $tenantId): int
    {
        $tenantConnection = DB::connection($tenantConnectionName);

        $productIdsToRestore = $tenantConnection
            ->table('layers as l')
            ->join('products as p', function ($join) use ($tenantId): void {
                $join->on('p.id', '=', 'l.product_id')
                    ->where('p.tenant_id', '=', $tenantId);
            })
            ->where('l.tenant_id', $tenantId)
            ->whereNotNull('l.product_id')
            ->whereNull('l.deleted_at')
            ->whereNotNull('p.deleted_at')
            ->distinct()
            ->pluck('p.id')
            ->all();

        if ($productIdsToRestore === []) {
            return 0;
        }

        return $tenantConnection
            ->table('products')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $productIdsToRestore)
            ->whereNotNull('deleted_at')
            ->update([
                'deleted_at' => null,
                'updated_at' => now(),
            ]);
    }
}
