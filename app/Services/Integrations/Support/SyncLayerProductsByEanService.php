<?php

namespace App\Services\Integrations\Support;

use Illuminate\Support\Facades\DB;

class SyncLayerProductsByEanService
{
    /**
     * @return array{matched_layers: int, layers_updated: int, products_restored: int}
     */
    public function sync(string $tenantConnectionName, string $tenantId, bool $preview = false): array
    {
        $summary = [
            'matched_layers' => 0,
            'layers_updated' => 0,
            'products_restored' => 0,
        ];

        $restoredProductIds = [];

        DB::connection($tenantConnectionName)
            ->table('layers as l')
            ->join('products as p', function ($join): void {
                $join->on('p.tenant_id', '=', 'l.tenant_id')
                    ->on('p.ean', '=', 'l.ean');
            })
            ->where('l.tenant_id', $tenantId)
            ->whereNotNull('l.ean')
            ->whereRaw("TRIM(l.ean) <> ''")
            ->orderBy('l.id')
            ->select([
                'l.id as layer_id',
                'l.product_id as layer_product_id',
                'l.deleted_at as layer_deleted_at',
                'p.id as product_id',
                'p.deleted_at as product_deleted_at',
            ])
            ->chunk(500, function ($rows) use ($preview, &$summary, &$restoredProductIds, $tenantConnectionName): void {
                $layerUpdates = [];
                $productIdsToRestore = [];

                foreach ($rows as $row) {
                    $summary['matched_layers']++;

                    $layerId = is_string($row->layer_id) ? $row->layer_id : '';
                    $currentLayerProductId = is_string($row->layer_product_id) ? $row->layer_product_id : null;
                    $matchedProductId = is_string($row->product_id) ? $row->product_id : null;

                    if ($layerId !== '' && $matchedProductId !== null && $currentLayerProductId !== $matchedProductId) {
                        $layerUpdates[] = [
                            'id' => $layerId,
                            'product_id' => $matchedProductId,
                            'updated_at' => now(),
                        ];
                    }

                    $layerIsDeleted = $row->layer_deleted_at !== null;
                    $productIsDeleted = $row->product_deleted_at !== null;

                    if (! $layerIsDeleted && $productIsDeleted && $matchedProductId !== null && ! isset($restoredProductIds[$matchedProductId])) {
                        $restoredProductIds[$matchedProductId] = true;
                        $productIdsToRestore[] = $matchedProductId;
                    }
                }

                if ($layerUpdates !== []) {
                    $summary['layers_updated'] += count($layerUpdates);

                    if (! $preview) {
                        DB::connection($tenantConnectionName)
                            ->table('layers')
                            ->upsert($layerUpdates, ['id'], ['product_id', 'updated_at']);
                    }
                }

                if ($productIdsToRestore !== []) {
                    if ($preview) {
                        $summary['products_restored'] += count($productIdsToRestore);

                        return;
                    }

                    $summary['products_restored'] += DB::connection($tenantConnectionName)
                        ->table('products')
                        ->whereIn('id', $productIdsToRestore)
                        ->whereNotNull('deleted_at')
                        ->update([
                            'deleted_at' => null,
                            'updated_at' => now(),
                        ]);
                }
            });

        return $summary;
    }
}
