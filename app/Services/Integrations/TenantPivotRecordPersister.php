<?php

namespace App\Services\Integrations;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Persiste tabelas pivot do tenant a partir de registros já mapeados.
 *
 * Cada configuração informa como derivar a linha da pivot a partir do registro
 * principal, mantendo validação de schema, deduplicação e fallback de conflito
 * encapsulados em um único serviço.
 */
class TenantPivotRecordPersister
{
    private const CHUNK_SIZE = 500;

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, array<string, mixed>>  $pivotConfigs
     */
    public static function persist(Connection $connection, array $records, array $pivotConfigs): void
    {
        foreach ($pivotConfigs as $pivotConfig) {
            self::persistConfiguredPivot($connection, $records, $pivotConfig);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array{table: string, local_key: string, foreign_key: string, related_key: string, unique_by?: array<string>}  $pivotConfig
     */
    private static function persistConfiguredPivot(Connection $connection, array $records, array $pivotConfig): void
    {
        $normalizedConfig = self::normalizePivotConfig($pivotConfig);
        $table = $normalizedConfig['table'];
        $localKey = $normalizedConfig['local_key'];
        $foreignKey = $normalizedConfig['foreign_key'];
        $relatedKey = $normalizedConfig['related_key'];
        $uniqueBy = $normalizedConfig['unique_by'];

        Log::debug('TenantPivotRecordPersister: persistindo pivot', [
            'table' => $table,
            'local_key' => $localKey,
            'foreign_key' => $foreignKey,
            'related_key' => $relatedKey,
            'unique_by' => $uniqueBy,
            'records' => count($records),
            'sample_related_values' => array_slice(array_column($records, $relatedKey), 0, 3),
        ]);

        if (! self::isValidPivotConfig($normalizedConfig)) {
            Log::warning('TenantPivotRecordPersister: pivot config incompleta', [
                'table' => $table,
                'foreign_key' => $foreignKey,
                'related_key' => $relatedKey,
            ]);

            return;
        }

        if (! self::tableExists($connection, $table)) {
            Log::warning('TenantPivotRecordPersister: tabela pivot não encontrada', ['table' => $table]);

            return;
        }

        $pivotColumns = self::tableColumns($connection, $table);
        $rows = self::buildPivotRows($records, $pivotColumns, $normalizedConfig);

        if ($rows === []) {
            Log::warning('TenantPivotRecordPersister: nenhuma pivot row gerada', [
                'table' => $table,
                'total_records' => count($records),
            ]);

            return;
        }

        $rows = self::logDeduplicatedRows(
            self::deduplicatePivotRows($rows, $uniqueBy),
            count($rows),
            'TenantPivotRecordPersister: registros duplicados removidos antes do upsert',
            ['table' => $table],
        );

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            self::upsertPivotChunk($connection, $table, $chunk, $uniqueBy, $pivotColumns);
        }

        Log::info('TenantPivotRecordPersister: pivot persistida', [
            'table' => $table,
            'rows' => count($rows),
        ]);
    }

    private static function tableExists(Connection $connection, string $table): bool
    {
        return $connection->getSchemaBuilder()->hasTable($table);
    }

    /**
     * @return array<int, string>
     */
    private static function tableColumns(Connection $connection, string $table): array
    {
        return $connection->getSchemaBuilder()->getColumnListing($table);
    }

    /**
     * @param  array{table?: mixed, local_key?: mixed, foreign_key?: mixed, related_key?: mixed, unique_by?: mixed}  $pivotConfig
     * @return array{table: string, local_key: string, foreign_key: string, related_key: string, unique_by: array<int, string>}
     */
    private static function normalizePivotConfig(array $pivotConfig): array
    {
        $foreignKey = (string) ($pivotConfig['foreign_key'] ?? '');
        $relatedKey = (string) ($pivotConfig['related_key'] ?? '');

        return [
            'table' => (string) ($pivotConfig['table'] ?? ''),
            'local_key' => (string) ($pivotConfig['local_key'] ?? 'id'),
            'foreign_key' => $foreignKey,
            'related_key' => $relatedKey,
            'unique_by' => array_values((array) ($pivotConfig['unique_by'] ?? [$foreignKey, $relatedKey])),
        ];
    }

    /**
     * @param  array{table: string, local_key: string, foreign_key: string, related_key: string, unique_by: array<int, string>}  $pivotConfig
     */
    private static function isValidPivotConfig(array $pivotConfig): bool
    {
        return $pivotConfig['table'] !== ''
            && $pivotConfig['foreign_key'] !== ''
            && $pivotConfig['related_key'] !== '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, string>  $pivotColumns
     * @param  array{table: string, local_key: string, foreign_key: string, related_key: string, unique_by: array<int, string>}  $pivotConfig
     * @return array<int, array<string, mixed>>
     */
    private static function buildPivotRows(array $records, array $pivotColumns, array $pivotConfig): array
    {
        $now = now()->toDateTimeString();
        $rows = [];
        $skipped = 0;

        foreach ($records as $record) {
            $row = self::buildPivotRow($record, $pivotColumns, $pivotConfig, $now);

            if ($row === null) {
                $skipped++;

                continue;
            }

            $rows[] = $row;
        }

        Log::debug('TenantPivotRecordPersister: pivot rows construídos', [
            'table' => $pivotConfig['table'],
            'rows' => count($rows),
            'skipped_null' => $skipped,
            'sample' => array_slice($rows, 0, 2),
        ]);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $pivotColumns
     * @param  array{table: string, local_key: string, foreign_key: string, related_key: string, unique_by: array<int, string>}  $pivotConfig
     * @return array<string, mixed>|null
     */
    private static function buildPivotRow(array $record, array $pivotColumns, array $pivotConfig, string $now): ?array
    {
        $localValue = $record[$pivotConfig['local_key']] ?? null;
        $relatedValue = $record[$pivotConfig['related_key']] ?? null;

        if ($localValue === null || $relatedValue === null) {
            return null;
        }

        $row = [
            $pivotConfig['foreign_key'] => $localValue,
            $pivotConfig['related_key'] => $relatedValue,
        ];

        foreach ($pivotColumns as $column) {
            if (isset($record[$column]) && ! isset($row[$column])) {
                $row[$column] = $record[$column];
            }
        }

        $row['id'] = (string) Str::ulid();
        $row['created_at'] = $now;
        $row['updated_at'] = $now;

        return array_intersect_key($row, array_flip($pivotColumns));
    }

    /**
     * @param  array<int, array<string, mixed>>  $chunk
     * @param  array<int, string>  $uniqueBy
     * @param  array<int, string>  $pivotColumns
     */
    private static function upsertPivotChunk(Connection $connection, string $table, array $chunk, array $uniqueBy, array $pivotColumns): void
    {
        try {
            $connection->table($table)->upsert(
                $chunk,
                $uniqueBy,
                ['updated_at'],
            );

            return;
        } catch (QueryException $exception) {
            $canFallbackToTenantKey = self::isInvalidConflictTarget($exception)
                && in_array('tenant_id', $pivotColumns, true)
                && ! in_array('tenant_id', $uniqueBy, true);

            if (! $canFallbackToTenantKey) {
                throw $exception;
            }

            $fallbackUniqueBy = array_values(array_unique(['tenant_id', ...$uniqueBy]));
            $dedupedChunk = self::deduplicatePivotRows($chunk, $fallbackUniqueBy);

            Log::warning('TenantPivotRecordPersister: fallback do upsert com tenant_id no conflict target', [
                'table' => $table,
                'original_unique_by' => $uniqueBy,
                'fallback_unique_by' => $fallbackUniqueBy,
                'chunk_size' => count($chunk),
                'chunk_size_after_dedup' => count($dedupedChunk),
            ]);

            $connection->table($table)->upsert(
                $dedupedChunk,
                $fallbackUniqueBy,
                ['updated_at'],
            );
        }
    }

    private static function isInvalidConflictTarget(QueryException $exception): bool
    {
        if ($exception->getCode() === '42P10') {
            return true;
        }

        return str_contains($exception->getMessage(), 'no unique or exclusion constraint matching the ON CONFLICT specification');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $uniqueBy
     * @return array<int, array<string, mixed>>
     */
    private static function deduplicatePivotRows(array $rows, array $uniqueBy): array
    {
        if ($uniqueBy === []) {
            return $rows;
        }

        $indexed = [];

        foreach ($rows as $row) {
            $keyParts = [];

            foreach ($uniqueBy as $column) {
                $value = $row[$column] ?? null;
                $keyParts[] = is_scalar($value) ? (string) $value : '';
            }

            $compositeKey = implode('|', $keyParts);

            if ($compositeKey === '') {
                continue;
            }

            $indexed[$compositeKey] = $row;
        }

        return array_values($indexed);
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
