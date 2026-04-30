<?php

namespace App\Services\Integrations\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LayerOrphanProductsReportService
{
    public function countOrphans(string $tenantConnectionName, string $tenantId): int
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
     * @return Collection<int, object>
     */
    public function listOrphans(string $tenantConnectionName, string $tenantId, int $limit = 100): Collection
    {
        return DB::connection($tenantConnectionName)
            ->table('layers as l')
            ->leftJoin('products as p', function ($join) use ($tenantId): void {
                $join->on('p.id', '=', 'l.product_id')
                    ->where('p.tenant_id', '=', $tenantId)
                    ->whereNull('p.deleted_at');
            })
            ->leftJoin('products as p_all', function ($join) use ($tenantId): void {
                $join->on('p_all.id', '=', 'l.product_id')
                    ->where('p_all.tenant_id', '=', $tenantId);
            })
            ->where('l.tenant_id', $tenantId)
            ->whereNotNull('l.product_id')
            ->whereNull('l.deleted_at')
            ->whereNull('p.id')
            ->orderBy('l.id')
            ->limit(max($limit, 1))
            ->get(['l.id as layer_id', 'l.segment_id', 'l.product_id', 'p_all.ean', 'l.updated_at']);
    }

    /**
     * @param  Collection<int, object>  $orphans
     * @return Collection<int, object>
     */
    public function enrichWithLegacy(string $tenantConnectionName, string $legacyConnectionName, string $tenantId, Collection $orphans): Collection
    {
        if ($orphans->isEmpty()) {
            return collect();
        }

        $legacyIds = $orphans
            ->pluck('product_id')
            ->filter(static fn ($id) => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();

        $legacyProducts = DB::connection($legacyConnectionName)
            ->table('products')
            ->whereIn('id', $legacyIds)
            ->when(
                Schema::connection($legacyConnectionName)->hasColumn('products', 'deleted_at'),
                static fn ($query) => $query->whereNull('deleted_at')
            )
            ->select(['id', 'ean'])
            ->get()
            ->keyBy('id');

        $candidateEans = $legacyProducts
            ->pluck('ean')
            ->filter(static fn ($ean) => is_string($ean) && $ean !== '')
            ->unique()
            ->values()
            ->all();

        $tenantProductsByEan = $candidateEans === []
            ? collect()
            : DB::connection($tenantConnectionName)
                ->table('products')
                ->where('tenant_id', $tenantId)
                ->whereIn('ean', $candidateEans)
                ->whereNull('deleted_at')
                ->select(['id', 'ean'])
                ->get()
                ->keyBy('ean');

        return $orphans->map(function ($row) use ($legacyProducts, $tenantProductsByEan) {
            $legacy = $legacyProducts->get($row->product_id);
            $legacyEan = is_object($legacy) ? $legacy->ean : null;
            $tenantProduct = is_string($legacyEan) ? $tenantProductsByEan->get($legacyEan) : null;

            return (object) [
                'layer_id' => $row->layer_id,
                'segment_id' => $row->segment_id,
                'product_id' => $row->product_id,
                'ean' => $row->ean ?? null,
                'updated_at' => $row->updated_at,
                'legacy_ean' => $legacyEan,
                'tenant_product_id_by_ean' => is_object($tenantProduct) ? $tenantProduct->id : null,
            ];
        });
    }
}
