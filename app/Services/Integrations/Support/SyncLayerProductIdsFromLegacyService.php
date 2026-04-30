<?php

namespace App\Services\Integrations\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncLayerProductIdsFromLegacyService
{
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
            ->select(['l.id', 'l.product_id'])
            ->chunk(500, function ($rows) use (
                $tenantConnection,
                $legacyConnection,
                $legacyConnectionName,
                $tenantId,
                $preview,
                &$legacyMatched,
                &$tenantMatched,
                &$updated,
                &$unresolvedLegacy,
                &$unresolvedTenant
            ): void {
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
                    ->select(['id', 'ean'])
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

    private function restoreSoftDeletedProductsReferencedByLayers(string $tenantConnectionName, string $tenantId): int
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
