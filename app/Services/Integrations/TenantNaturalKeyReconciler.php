<?php

namespace App\Services\Integrations;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Log;

/**
 * Reconcilia os ids determinísticos do lote com as chaves naturais já existentes no tenant.
 *
 * O upsert principal declara conflito apenas em `id`, mas tabelas como `products` têm um
 * segundo índice único — `(tenant_id, ean)`. Quando o id determinístico calculado agora difere
 * do id da linha que já é dona daquele EAN (produto vindo do import legado, que usa
 * `productIdFromEan()` sem o integration_id; produto criado pela UI; ou dois `codigo_erp`
 * apontando para o mesmo EAN), o `ON CONFLICT (id)` não casa, o Postgres tenta um INSERT puro
 * e estoura `duplicate key value violates unique constraint "products_tenant_id_ean_unique"`.
 *
 * A reconciliação reusa o id da linha existente, de modo que o upsert vire um UPDATE — o que
 * também preserva as FKs que já apontam para aquele produto (layers, product_store, sales).
 *
 * Custo: uma única query por lote, servida pelo próprio índice único e trazendo apenas
 * `id` + chave natural. Nada é consultado por registro.
 */
class TenantNaturalKeyReconciler
{
    /**
     * Máximo de valores por cláusula IN, para não estourar o limite de bind params do driver.
     */
    private const LOOKUP_CHUNK_SIZE = 1000;

    /**
     * Chaves naturais protegidas por índice único além da PK.
     *
     * @var array<string, array{column: string, soft_deletes: bool}>
     */
    private const NATURAL_KEYS = [
        'products' => ['column' => 'ean', 'soft_deletes' => true],
    ];

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    public static function reconcile(Connection $connection, string $targetTable, array $records): array
    {
        $naturalKey = self::NATURAL_KEYS[$targetTable] ?? null;

        if ($naturalKey === null || $records === []) {
            return $records;
        }

        $deduplicated = self::deduplicateByNaturalKey($targetTable, $naturalKey['column'], $records);
        $existingIds = self::existingIdsByNaturalKey($connection, $targetTable, $naturalKey, $deduplicated);

        if ($existingIds === []) {
            return $deduplicated;
        }

        return self::remapIds($targetTable, $naturalKey['column'], $deduplicated, $existingIds);
    }

    /**
     * Mantém apenas o último registro de cada chave natural: dois registros com a mesma chave
     * e ids diferentes quebrariam o índice único dentro do próprio INSERT.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private static function deduplicateByNaturalKey(string $targetTable, string $keyColumn, array $records): array
    {
        $byKey = [];
        $withoutKey = [];
        $duplicates = 0;

        foreach ($records as $record) {
            $key = self::normalizeKeyValue($record[$keyColumn] ?? null);

            if ($key === null) {
                $withoutKey[] = $record;

                continue;
            }

            if (isset($byKey[$key])) {
                $duplicates++;
            }

            $byKey[$key] = $record;
        }

        if ($duplicates > 0) {
            Log::warning('TenantNaturalKeyReconciler: registros duplicados por chave natural removidos', [
                'table' => $targetTable,
                'key_column' => $keyColumn,
                'removed' => $duplicates,
            ]);
        }

        return [...array_values($byKey), ...$withoutKey];
    }

    /**
     * Busca, em uma query por lote, os ids já existentes para as chaves naturais presentes.
     *
     * Considera apenas linhas ativas: o índice único de products é parcial
     * (`WHERE deleted_at IS NULL`), então linhas soft-deleted não disputam a chave.
     *
     * @param  array{column: string, soft_deletes: bool}  $naturalKey
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, string>
     */
    private static function existingIdsByNaturalKey(
        Connection $connection,
        string $targetTable,
        array $naturalKey,
        array $records,
    ): array {
        $keyColumn = $naturalKey['column'];
        $keyValues = [];
        $tenantId = null;

        foreach ($records as $record) {
            $value = self::normalizeKeyValue($record[$keyColumn] ?? null);

            if ($value !== null) {
                $keyValues[$value] = true;
            }

            $tenantId ??= self::normalizeKeyValue($record['tenant_id'] ?? null);
        }

        if ($keyValues === []) {
            return [];
        }

        $existingIds = [];

        foreach (array_chunk(array_keys($keyValues), self::LOOKUP_CHUNK_SIZE) as $chunk) {
            $query = $connection->table($targetTable)
                ->select(['id', $keyColumn])
                ->whereIn($keyColumn, $chunk);

            // Prefixo do índice único (tenant_id, ean): mantém a busca como index scan.
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }

            if ($naturalKey['soft_deletes']) {
                $query->whereNull('deleted_at');
            }

            foreach ($query->get() as $row) {
                $value = self::normalizeKeyValue($row->{$keyColumn} ?? null);

                if ($value !== null) {
                    $existingIds[$value] = (string) $row->id;
                }
            }
        }

        return $existingIds;
    }

    /**
     * Troca o id determinístico pelo id já existente quando a chave natural aponta para outra linha.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<string, string>  $existingIds
     * @return array<int, array<string, mixed>>
     */
    private static function remapIds(string $targetTable, string $keyColumn, array $records, array $existingIds): array
    {
        $remapped = 0;

        foreach ($records as $index => $record) {
            $value = self::normalizeKeyValue($record[$keyColumn] ?? null);

            if ($value === null || ! isset($existingIds[$value])) {
                continue;
            }

            if ((string) ($record['id'] ?? '') === $existingIds[$value]) {
                continue;
            }

            $records[$index]['id'] = $existingIds[$value];
            $remapped++;
        }

        if ($remapped > 0) {
            Log::info('TenantNaturalKeyReconciler: ids realinhados com registros existentes', [
                'table' => $targetTable,
                'key_column' => $keyColumn,
                'remapped' => $remapped,
            ]);
        }

        return $records;
    }

    private static function normalizeKeyValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
