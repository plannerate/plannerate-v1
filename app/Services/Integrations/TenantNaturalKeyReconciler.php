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
 * Linhas soft-deleted também são reusadas: se o EAN do registro pertence a uma linha apagada
 * (e não há linha ativa com aquele EAN), o id é realinhado para ela e a linha é **restaurada**
 * (`deleted_at = null`) — em vez de inserir uma nova linha ativa e bifurcar o produto em duas.
 * É isso que faz o modelo "controlar via apagar + restaurar" funcionar: um único registro por
 * EAN alternando `deleted_at`, sem duplicatas. Linha ativa sempre tem prioridade sobre a apagada,
 * então a restauração nunca colide com o índice único parcial (`WHERE deleted_at IS NULL`).
 *
 * Custo: uma única query de lookup por lote (traz `id` + chave natural + `deleted_at`). O UPDATE
 * de restauração só é emitido quando há de fato linhas apagadas a reusar. Nada por registro.
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
        $existingRows = self::existingRowsByNaturalKey($connection, $targetTable, $naturalKey, $deduplicated);

        if ($existingRows === []) {
            return $deduplicated;
        }

        [$remapped, $idsToRestore] = self::remapIds($targetTable, $naturalKey['column'], $deduplicated, $existingRows);

        if ($naturalKey['soft_deletes'] && $idsToRestore !== []) {
            self::restoreSoftDeleted($connection, $targetTable, $idsToRestore);
        }

        return $remapped;
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
     * Busca, em uma query por lote, as linhas já existentes para as chaves naturais presentes.
     *
     * Traz também linhas soft-deleted (a linha ativa tem prioridade): quando um EAN só existe
     * numa linha apagada, ela será reusada e restaurada em vez de gerar uma linha nova.
     *
     * @param  array{column: string, soft_deletes: bool}  $naturalKey
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, array{id: string, restore: bool}>
     */
    private static function existingRowsByNaturalKey(
        Connection $connection,
        string $targetTable,
        array $naturalKey,
        array $records,
    ): array {
        $keyColumn = $naturalKey['column'];
        $softDeletes = $naturalKey['soft_deletes'];
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

        $columns = $softDeletes ? ['id', $keyColumn, 'deleted_at'] : ['id', $keyColumn];

        /** @var array<string, array{active: ?string, deleted: ?string}> $byKey */
        $byKey = [];

        foreach (array_chunk(array_keys($keyValues), self::LOOKUP_CHUNK_SIZE) as $chunk) {
            $query = $connection->table($targetTable)
                ->select($columns)
                ->whereIn($keyColumn, $chunk);

            // Prefixo do índice único (tenant_id, ean): mantém a busca como index scan.
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }

            foreach ($query->get() as $row) {
                $value = self::normalizeKeyValue($row->{$keyColumn} ?? null);

                if ($value === null) {
                    continue;
                }

                $isDeleted = $softDeletes && ($row->deleted_at ?? null) !== null;

                if ($isDeleted) {
                    $byKey[$value]['deleted'] ??= (string) $row->id;
                } else {
                    $byKey[$value]['active'] = (string) $row->id;
                }
            }
        }

        $existingRows = [];

        foreach ($byKey as $value => $ids) {
            if (isset($ids['active'])) {
                $existingRows[$value] = ['id' => $ids['active'], 'restore' => false];
            } elseif (isset($ids['deleted'])) {
                $existingRows[$value] = ['id' => $ids['deleted'], 'restore' => true];
            }
        }

        return $existingRows;
    }

    /**
     * Troca o id determinístico pelo id já existente quando a chave natural aponta para outra linha.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<string, array{id: string, restore: bool}>  $existingRows
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private static function remapIds(string $targetTable, string $keyColumn, array $records, array $existingRows): array
    {
        $remapped = 0;
        $idsToRestore = [];

        foreach ($records as $index => $record) {
            $value = self::normalizeKeyValue($record[$keyColumn] ?? null);

            if ($value === null || ! isset($existingRows[$value])) {
                continue;
            }

            $existingId = $existingRows[$value]['id'];

            if ((string) ($record['id'] ?? '') !== $existingId) {
                $records[$index]['id'] = $existingId;
                $remapped++;
            }

            // Restaurar vale mesmo quando o id determinístico já era o da linha apagada
            // (mesmo codigo_erp): sem remap, mas a linha ainda precisa voltar a ativa.
            if ($existingRows[$value]['restore']) {
                $idsToRestore[$existingId] = true;
            }
        }

        if ($remapped > 0) {
            Log::info('TenantNaturalKeyReconciler: ids realinhados com registros existentes', [
                'table' => $targetTable,
                'key_column' => $keyColumn,
                'remapped' => $remapped,
            ]);
        }

        return [$records, array_keys($idsToRestore)];
    }

    /**
     * Restaura (limpa `deleted_at`) as linhas soft-deleted que foram reusadas pelo lote, para
     * que o upsert seguinte as atualize como linhas ativas. Emitido só quando há o que restaurar.
     *
     * @param  array<int, string>  $ids
     */
    private static function restoreSoftDeleted(Connection $connection, string $targetTable, array $ids): void
    {
        $restored = 0;

        foreach (array_chunk($ids, self::LOOKUP_CHUNK_SIZE) as $chunk) {
            $restored += $connection->table($targetTable)
                ->whereIn('id', $chunk)
                ->whereNotNull('deleted_at')
                ->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
        }

        if ($restored > 0) {
            Log::info('TenantNaturalKeyReconciler: linhas soft-deleted restauradas para reuso', [
                'table' => $targetTable,
                'restored' => $restored,
            ]);
        }
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
