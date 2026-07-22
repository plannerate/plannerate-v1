<?php

namespace App\Services\Integrations\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Re-vincula vendas a produtos pelo codigo_erp, cobrindo tanto vendas sem
 * product_id quanto vendas apontando para produto errado/removido.
 *
 * Quando há mais de um produto ativo com o mesmo codigo_erp, a escolha é
 * determinística: o mais antigo (created_at, id) vence.
 */
class SyncSalesProductReferencesService
{
    private const ERP_CHUNK_SIZE = 200;

    /**
     * Atualiza product_id e ean das vendas pelo codigo_erp em lote.
     *
     * @param  array<int, string>  $erpCodes
     * @return int Vendas atualizadas
     */
    public function syncByCodigoErp(
        string $tenantConnectionName,
        string $tenantId,
        array $erpCodes,
        CarbonInterface $now,
    ): int {
        if ($erpCodes === []) {
            return 0;
        }

        $connection = DB::connection($tenantConnectionName);

        if ($connection->getDriverName() !== 'sqlite') {
            $updated = 0;

            foreach (array_chunk($erpCodes, self::ERP_CHUNK_SIZE) as $erpChunk) {
                $inPlaceholders = implode(', ', array_fill(0, count($erpChunk), '?'));

                $sql = "
                    UPDATE sales s
                    SET product_id = p.id,
                        ean = p.ean,
                        updated_at = ?
                    FROM ({$this->deterministicProductsSubquery()}) p
                    WHERE s.tenant_id = ?
                      AND p.tenant_id = s.tenant_id
                      AND p.codigo_erp = s.codigo_erp
                      AND s.codigo_erp IN ({$inPlaceholders})
                      AND (
                          s.product_id IS NULL
                          OR s.ean IS NULL
                          OR s.product_id <> p.id
                          OR COALESCE(s.ean, '') <> COALESCE(p.ean, '')
                      )
                ";

                $updated += $connection->update($sql, [
                    $now,
                    $tenantId,
                    ...$erpChunk,
                ]);
            }

            return $updated;
        }

        // Fallback compatível com SQLite para ambiente de testes.
        return $connection->table(IntegrationTables::name('sales'))
            ->where('tenant_id', $tenantId)
            ->whereIn('codigo_erp', $erpCodes)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from(IntegrationTables::name('products'))
                    ->whereColumn('products.tenant_id', 'sales.tenant_id')
                    ->whereColumn('products.codigo_erp', 'sales.codigo_erp')
                    ->whereNull('products.deleted_at');
            })
            ->update([
                'product_id' => DB::raw($this->sqliteProductColumnSubquery('id')),
                'ean' => DB::raw($this->sqliteProductColumnSubquery('ean')),
                'updated_at' => $now,
            ]);
    }

    /**
     * Re-vincula TODAS as vendas do tenant pelo codigo_erp — inclusive as que
     * apontam para produto errado (`product_id <> p.id`), que o vínculo
     * incremental por `product_id IS NULL` não corrige.
     *
     * @return int Vendas atualizadas
     */
    public function syncAllByCodigoErp(
        string $tenantConnectionName,
        string $tenantId,
        CarbonInterface $now,
    ): int {
        $connection = DB::connection($tenantConnectionName);

        if ($connection->getDriverName() !== 'sqlite') {
            $sql = "
                UPDATE sales s
                SET product_id = p.id,
                    ean = p.ean,
                    updated_at = ?
                FROM ({$this->deterministicProductsSubquery()}) p
                WHERE s.tenant_id = ?
                  AND p.tenant_id = s.tenant_id
                  AND p.codigo_erp = s.codigo_erp
                  AND (
                      s.product_id IS NULL
                      OR s.ean IS NULL
                      OR s.product_id <> p.id
                      OR COALESCE(s.ean, '') <> COALESCE(p.ean, '')
                  )
            ";

            return $connection->update($sql, [$now, $tenantId]);
        }

        // Fallback compatível com SQLite para ambiente de testes.
        return $connection->table(IntegrationTables::name('sales'))
            ->where('tenant_id', $tenantId)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from(IntegrationTables::name('products'))
                    ->whereColumn('products.tenant_id', 'sales.tenant_id')
                    ->whereColumn('products.codigo_erp', 'sales.codigo_erp')
                    ->whereNull('products.deleted_at');
            })
            ->update([
                'product_id' => DB::raw($this->sqliteProductColumnSubquery('id')),
                'ean' => DB::raw($this->sqliteProductColumnSubquery('ean')),
                'updated_at' => $now,
            ]);
    }

    /**
     * Um produto ativo por (tenant_id, codigo_erp): com codigo_erp duplicado,
     * o UPDATE ... FROM do Postgres escolheria uma linha arbitrária.
     */
    private function deterministicProductsSubquery(): string
    {
        return '
            SELECT DISTINCT ON (tenant_id, codigo_erp) id, ean, codigo_erp, tenant_id
            FROM products
            WHERE deleted_at IS NULL
            ORDER BY tenant_id, codigo_erp, created_at ASC, id ASC
        ';
    }

    /** Mesma escolha determinística do Postgres, em subselect por linha. */
    private function sqliteProductColumnSubquery(string $column): string
    {
        return "(
            SELECT products.{$column} FROM products
            WHERE products.tenant_id = sales.tenant_id
              AND products.codigo_erp = sales.codigo_erp
              AND products.deleted_at IS NULL
            ORDER BY products.created_at ASC, products.id ASC
            LIMIT 1
        )";
    }
}
