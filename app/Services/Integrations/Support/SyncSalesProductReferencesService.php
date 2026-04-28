<?php

namespace App\Services\Integrations\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncSalesProductReferencesService
{
    private const ERP_CHUNK_SIZE = 200;

    /**
     * Atualiza product_id e ean das vendas pelo codigo_erp em lote.
     *
     * @param  array<int, string>  $erpCodes
     */
    public function syncByCodigoErp(
        string $tenantConnectionName,
        string $tenantId,
        array $erpCodes,
        Carbon $now,
    ): void {
        if ($erpCodes === []) {
            return;
        }

        $connection = DB::connection($tenantConnectionName);
        $driver = $connection->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $salesTable = $connection->getTablePrefix().'sales';
            $productsTable = $connection->getTablePrefix().'products';

            foreach (array_chunk($erpCodes, self::ERP_CHUNK_SIZE) as $erpChunk) {
                $inPlaceholders = implode(', ', array_fill(0, count($erpChunk), '?'));

                $sql = "
                    UPDATE {$salesTable} s
                    INNER JOIN {$productsTable} p
                        ON p.tenant_id = s.tenant_id
                       AND p.codigo_erp = s.codigo_erp
                       AND p.deleted_at IS NULL
                    SET s.product_id = p.id,
                        s.ean = p.ean,
                        s.updated_at = ?
                    WHERE s.tenant_id = ?
                      AND s.codigo_erp IN ({$inPlaceholders})
                      AND (
                          s.product_id IS NULL
                          OR s.ean IS NULL
                          OR s.product_id <> p.id
                          OR COALESCE(s.ean, '') <> COALESCE(p.ean, '')
                      )
                ";

                $connection->update($sql, [
                    $now,
                    $tenantId,
                    ...$erpChunk,
                ]);
            }

            return;
        }

        // Fallback compatível com SQLite para ambiente de testes.
        $connection->table('sales')
            ->where('tenant_id', $tenantId)
            ->whereIn('codigo_erp', $erpCodes)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('products')
                    ->whereColumn('products.tenant_id', 'sales.tenant_id')
                    ->whereColumn('products.codigo_erp', 'sales.codigo_erp')
                    ->whereNull('products.deleted_at');
            })
            ->update([
                'product_id' => DB::raw('(SELECT products.id FROM products WHERE products.tenant_id = sales.tenant_id AND products.codigo_erp = sales.codigo_erp AND products.deleted_at IS NULL LIMIT 1)'),
                'ean' => DB::raw('(SELECT products.ean FROM products WHERE products.tenant_id = sales.tenant_id AND products.codigo_erp = sales.codigo_erp AND products.deleted_at IS NULL LIMIT 1)'),
                'updated_at' => $now,
            ]);
    }
}
