<?php

namespace App\Services\Integrations;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Log;

/**
 * Reconcilia os ids determinísticos do lote com as chaves naturais já existentes no tenant.
 *
 * O upsert principal declara conflito apenas em `id`, mas as tabelas têm um segundo índice
 * único — `products (tenant_id, ean)` e `sales (tenant_id, store_id, codigo_erp, sale_date,
 * promotion)`. Quando o id determinístico calculado agora difere do id da linha que já é dona
 * daquela chave natural (produto do import legado sem integration_id no id; produto criado pela
 * UI; `unique_by` do path desalinhado do índice do banco; venda com partes de id diferentes),
 * o `ON CONFLICT (id)` não casa, o Postgres tenta um INSERT puro e estoura `duplicate key`.
 *
 * A reconciliação reusa o id da linha existente, de modo que o upsert vire um UPDATE — o que
 * também preserva as FKs que já apontam para aquela linha (layers, product_store, sales).
 *
 * Linhas soft-deleted também são reusadas: se a chave natural do registro pertence a uma linha
 * apagada (e não há linha ativa com aquela chave), o id é realinhado para ela e a linha é
 * **restaurada** (`deleted_at = null`) — em vez de inserir uma nova linha ativa e bifurcar o
 * registro em dois. É isso que faz o modelo "controlar via apagar + restaurar" funcionar: um
 * único registro por chave alternando `deleted_at`, sem duplicatas. Linha ativa sempre tem
 * prioridade sobre a apagada, então a restauração nunca colide com o índice único parcial
 * (`WHERE deleted_at IS NULL`).
 *
 * Custo: uma query de lookup por lote (traz `id` + colunas da chave + `deleted_at`), usando
 * os índices `(tenant_id, ean)` / `(tenant_id, codigo_erp)`. O UPDATE de restauração só é
 * emitido quando há de fato linhas apagadas a reusar. Nada por registro.
 *
 * Decisão de negócio (2026-07-15): o soft-delete de produto é 100% feed-driven POR DESIGN —
 * produto que ainda vem no feed da API é restaurado no próximo import, e não existe flag de
 * "manter escondido mesmo no feed". Esconder permanentemente = remover do feed no ERP.
 */
class TenantNaturalKeyReconciler
{
    /**
     * Máximo de registros por chunk de lookup, para não estourar o limite de bind params.
     */
    private const LOOKUP_CHUNK_SIZE = 1000;

    /**
     * Separador das partes da chave composta (nunca aparece nos dados).
     */
    private const KEY_SEPARATOR = "\x1F";

    /**
     * Chaves naturais protegidas por índice único além da PK. As colunas espelham
     * o índice único do banco (sem o tenant_id, aplicado como filtro à parte).
     *
     * @var array<string, array{columns: array<int, string>, soft_deletes: bool}>
     */
    private const NATURAL_KEYS = [
        'products' => ['columns' => ['ean'], 'soft_deletes' => true],
        'sales' => ['columns' => ['store_id', 'codigo_erp', 'sale_date', 'promotion'], 'soft_deletes' => true],
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

        $keyColumns = $naturalKey['columns'];

        $deduplicated = self::deduplicateByNaturalKey($targetTable, $keyColumns, $records);
        $existingRows = self::existingRowsByNaturalKey($connection, $targetTable, $naturalKey, $deduplicated);

        if ($existingRows === []) {
            return $deduplicated;
        }

        [$remapped, $idsToRestore] = self::remapIds($targetTable, $keyColumns, $deduplicated, $existingRows);

        if ($naturalKey['soft_deletes'] && $idsToRestore !== []) {
            self::restoreSoftDeleted($connection, $targetTable, $idsToRestore);
        }

        return $remapped;
    }

    /**
     * Mantém apenas o último registro de cada chave natural: dois registros com a mesma chave
     * e ids diferentes quebrariam o índice único dentro do próprio INSERT.
     *
     * @param  array<int, string>  $keyColumns
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private static function deduplicateByNaturalKey(string $targetTable, array $keyColumns, array $records): array
    {
        $byKey = [];
        $withoutKey = [];
        $duplicates = 0;

        foreach ($records as $record) {
            $key = self::buildKey($record, $keyColumns);

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
                'key_columns' => $keyColumns,
                'removed' => $duplicates,
            ]);
        }

        return [...array_values($byKey), ...$withoutKey];
    }

    /**
     * Busca, em uma query por chunk, as linhas já existentes para as chaves naturais presentes.
     *
     * Para chave composta, a query restringe cada coluna aos valores distintos do chunk
     * (superconjunto cartesiano — pequeno, pois uma página tem 1 loja e 1-2 datas) e o
     * casamento exato da tupla é feito em PHP.
     *
     * Traz também linhas soft-deleted (a linha ativa tem prioridade): quando uma chave só
     * existe numa linha apagada, ela será reusada e restaurada em vez de gerar linha nova.
     *
     * @param  array{columns: array<int, string>, soft_deletes: bool}  $naturalKey
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, array{id: string, restore: bool}>
     */
    private static function existingRowsByNaturalKey(
        Connection $connection,
        string $targetTable,
        array $naturalKey,
        array $records,
    ): array {
        $keyColumns = $naturalKey['columns'];
        $softDeletes = $naturalKey['soft_deletes'];

        $keyed = array_values(array_filter(
            $records,
            fn (array $record): bool => self::buildKey($record, $keyColumns) !== null,
        ));

        if ($keyed === []) {
            return [];
        }

        $tenantId = null;

        foreach ($keyed as $record) {
            $tenantId ??= self::normalizeKeyValue($record['tenant_id'] ?? null);
        }

        $columns = $softDeletes ? ['id', ...$keyColumns, 'deleted_at'] : ['id', ...$keyColumns];

        /** @var array<string, array{active: ?string, deleted: ?string}> $byKey */
        $byKey = [];

        foreach (array_chunk($keyed, self::LOOKUP_CHUNK_SIZE) as $chunk) {
            $query = $connection->table($targetTable)->select($columns);

            // Prefixo dos índices únicos (tenant_id, ...): mantém a busca como index scan.
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }

            foreach ($keyColumns as $keyColumn) {
                $values = [];
                $hasEmpty = false;

                foreach ($chunk as $record) {
                    $value = self::normalizeKeyValue($record[$keyColumn] ?? null);

                    if ($value === null) {
                        $hasEmpty = true;
                    } else {
                        $values[$value] = true;
                    }
                }

                $distinct = array_keys($values);

                if ($distinct === []) {
                    $query->whereNull($keyColumn);
                } elseif ($hasEmpty) {
                    $query->where(function ($q) use ($keyColumn, $distinct): void {
                        $q->whereIn($keyColumn, $distinct)->orWhereNull($keyColumn);
                    });
                } else {
                    $query->whereIn($keyColumn, $distinct);
                }
            }

            foreach ($query->get() as $row) {
                $key = self::buildKey((array) $row, $keyColumns);

                if ($key === null) {
                    continue;
                }

                $isDeleted = $softDeletes && ($row->deleted_at ?? null) !== null;

                if ($isDeleted) {
                    $byKey[$key]['deleted'] ??= (string) $row->id;
                } else {
                    $byKey[$key]['active'] = (string) $row->id;
                }
            }
        }

        $existingRows = [];

        foreach ($byKey as $key => $ids) {
            if (isset($ids['active'])) {
                $existingRows[$key] = ['id' => $ids['active'], 'restore' => false];
            } elseif (isset($ids['deleted'])) {
                $existingRows[$key] = ['id' => $ids['deleted'], 'restore' => true];
            }
        }

        return $existingRows;
    }

    /**
     * Troca o id determinístico pelo id já existente quando a chave natural aponta para outra linha.
     *
     * @param  array<int, string>  $keyColumns
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<string, array{id: string, restore: bool}>  $existingRows
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private static function remapIds(string $targetTable, array $keyColumns, array $records, array $existingRows): array
    {
        $remapped = 0;
        $idsToRestore = [];

        foreach ($records as $index => $record) {
            $key = self::buildKey($record, $keyColumns);

            if ($key === null || ! isset($existingRows[$key])) {
                continue;
            }

            $existingId = $existingRows[$key]['id'];

            if ((string) ($record['id'] ?? '') !== $existingId) {
                $records[$index]['id'] = $existingId;
                $remapped++;
            }

            // Restaurar vale mesmo quando o id determinístico já era o da linha apagada
            // (mesma chave): sem remap, mas a linha ainda precisa voltar a ativa.
            if ($existingRows[$key]['restore']) {
                $idsToRestore[$existingId] = true;
            }
        }

        if ($remapped > 0) {
            Log::info('TenantNaturalKeyReconciler: ids realinhados com registros existentes', [
                'table' => $targetTable,
                'key_columns' => $keyColumns,
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

    /**
     * Chave composta do registro: partes normalizadas unidas por separador de controle.
     * Partes nulas viram '' (promotion NULL é legítimo); a chave só é descartada quando
     * TODAS as partes estão vazias.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $keyColumns
     */
    private static function buildKey(array $record, array $keyColumns): ?string
    {
        $parts = [];
        $allEmpty = true;

        foreach ($keyColumns as $column) {
            $value = self::normalizeKeyValue($record[$column] ?? null);
            $parts[] = $value ?? '';

            if ($value !== null) {
                $allEmpty = false;
            }
        }

        return $allEmpty ? null : implode(self::KEY_SEPARATOR, $parts);
    }

    private static function normalizeKeyValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        // Datas: 'Y-m-d H:i:s' (registro mapeado) e 'Y-m-d' (coluna date do banco)
        // precisam produzir a mesma parte de chave.
        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]/', $normalized) === 1) {
            return substr($normalized, 0, 10);
        }

        return $normalized;
    }
}
