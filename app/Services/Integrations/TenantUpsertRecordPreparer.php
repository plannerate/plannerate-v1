<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Log;

/**
 * Prepara registros da tabela principal para upsert.
 *
 * Esta etapa filtra colunas inexistentes, normaliza ids, remove duplicidades
 * dentro do lote e expõe as colunas que podem ser atualizadas no upsert.
 */
class TenantUpsertRecordPreparer
{
    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, string>  $tableColumns
     * @param  array<int, string>  $pivotOnlyTargets  Alvos mapeados que só alimentam as pivots
     * @return array<int, array<string, mixed>>
     */
    public static function prepare(array $records, array $tableColumns, string $targetTable, array $pivotOnlyTargets = []): array
    {
        // Métrica por loja (estoque, última compra) existe como coluna na tabela
        // principal por herança, mas o valor vem da unidade consultada. Mantê-la
        // no upsert faria a última cadeia de importação a terminar sobrescrever
        // as demais. Fica só na pivot, que tem uma linha por loja.
        $allowedColumns = array_values(array_diff($tableColumns, $pivotOnlyTargets));

        $filteredRecords = array_values(array_map(
            fn (array $record): array => array_intersect_key($record, array_flip($allowedColumns)),
            $records,
        ));

        return self::logDeduplicatedRows(
            self::deduplicateById($filteredRecords),
            count($filteredRecords),
            'TenantUpsertRecordPreparer: registros duplicados removidos antes do upsert',
            ['table' => $targetTable],
        );
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<int, string>
     */
    public static function resolveUpdateColumns(array $record): array
    {
        return array_values(array_diff(array_keys($record), ['id', 'created_at']));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function deduplicateById(array $rows): array
    {
        $indexed = [];
        $duplicates = [];

        foreach ($rows as $row) {
            $normalizedId = self::normalizeId($row['id'] ?? null);

            if ($normalizedId === null) {
                continue;
            }

            if (isset($indexed[$normalizedId])) {
                $duplicates[$normalizedId] = true;
            }

            $row['id'] = $normalizedId;

            $indexed[$normalizedId] = $row;
        }

        if ($duplicates !== []) {
            Log::info('TenantUpsertRecordPreparer: ids duplicados detectados no lote', [
                'count' => count($duplicates),
                'sample' => array_slice(array_keys($duplicates), 0, 10),
            ]);
        }

        return array_values($indexed);
    }

    private static function normalizeId(mixed $id): ?string
    {
        if (! is_scalar($id) || (string) $id === '') {
            return null;
        }

        $normalizedId = trim((string) $id);

        return $normalizedId !== '' ? $normalizedId : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    private static function logDeduplicatedRows(array $rows, int $originalCount, string $message, array $context = []): array
    {
        $removedDuplicates = $originalCount - count($rows);

        if ($removedDuplicates > 0) {
            Log::warning($message, [
                ...$context,
                'removed' => $removedDuplicates,
            ]);
        }

        return $rows;
    }
}
