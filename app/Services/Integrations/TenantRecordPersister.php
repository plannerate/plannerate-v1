<?php

namespace App\Services\Integrations;

use App\Models\TenantIntegration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Persiste registros pré-mapeados na tabela alvo do tenant via upsert.
 *
 * Suporta tabelas pivot via `pivot_tables` no pathConfig:
 *   [
 *     'table'      => 'product_store',
 *     'local_key'  => 'id',          // campo do registro principal → foreign_key na pivot
 *     'foreign_key'=> 'product_id',  // coluna na pivot que referencia a tabela principal
 *     'related_key'=> 'store_id',    // coluna presente no registro e na pivot
 *     'unique_by'  => ['product_id', 'store_id'],  // padrão: [foreign_key, related_key]
 *   ]
 */
class TenantRecordPersister
{
    private const CHUNK_SIZE = 500;

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, array<string, mixed>>  $pivotConfigs
     */
    public static function persist(
        TenantIntegration $integration,
        string $targetTable,
        array $records,
        array $pivotConfigs = [],
    ): void {
        if ($targetTable === '' || $records === []) {
            return;
        }

        $integrationId = (string) $integration->id;
        $upserted = 0;

        $integration->tenant->execute(function () use ($targetTable, $records, $pivotConfigs, &$upserted): void {
            if (! Schema::connection('tenant')->hasTable($targetTable)) {
                Log::warning('TenantRecordPersister: tabela não encontrada', ['table' => $targetTable]);

                return;
            }

            $tableColumns = Schema::connection('tenant')->getColumnListing($targetTable);

            $filtered = array_values(array_map(
                fn (array $record): array => array_intersect_key($record, array_flip($tableColumns)),
                $records,
            ));

            $beforeDedupCount = count($filtered);
            $filtered = self::deduplicateById($filtered);
            $removedDuplicates = $beforeDedupCount - count($filtered);

            if ($removedDuplicates > 0) {
                Log::warning('TenantRecordPersister: registros duplicados removidos antes do upsert', [
                    'table' => $targetTable,
                    'removed' => $removedDuplicates,
                ]);
            }

            if ($filtered !== []) {
                $updateColumns = array_values(array_diff(array_keys($filtered[0]), ['id', 'created_at']));

                foreach (array_chunk($filtered, self::CHUNK_SIZE) as $chunk) {
                    $chunk = self::deduplicateById($chunk);

                    DB::connection('tenant')->table($targetTable)->upsert(
                        $chunk,
                        ['id'],
                        $updateColumns,
                    );
                    $upserted += count($chunk);
                }
            }

            foreach ($pivotConfigs as $pivotConfig) {
                self::upsertPivot($records, $pivotConfig);
            }
        });

        Log::info('TenantRecordPersister: registros persistidos', [
            'integration_id' => $integrationId,
            'target_table' => $targetTable,
            'upserted' => $upserted,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array{table: string, local_key: string, foreign_key: string, related_key: string, unique_by?: array<string>}  $pivotConfig
     */
    private static function upsertPivot(array $records, array $pivotConfig): void
    {
        $table = (string) ($pivotConfig['table'] ?? '');
        $localKey = (string) ($pivotConfig['local_key'] ?? 'id');
        $foreignKey = (string) ($pivotConfig['foreign_key'] ?? '');
        $relatedKey = (string) ($pivotConfig['related_key'] ?? '');
        $uniqueBy = (array) ($pivotConfig['unique_by'] ?? [$foreignKey, $relatedKey]);

        if ($table === '' || $foreignKey === '' || $relatedKey === '') {
            return;
        }

        if (! Schema::connection('tenant')->hasTable($table)) {
            Log::warning('TenantRecordPersister: tabela pivot não encontrada', ['table' => $table]);

            return;
        }

        $pivotColumns = Schema::connection('tenant')->getColumnListing($table);
        $now = now()->toDateTimeString();

        $rows = [];
        foreach ($records as $record) {
            $localValue = $record[$localKey] ?? null;
            $relatedValue = $record[$relatedKey] ?? null;

            if ($localValue === null || $relatedValue === null) {
                continue;
            }

            $row = [$foreignKey => $localValue, $relatedKey => $relatedValue];

            foreach ($pivotColumns as $col) {
                if (isset($record[$col]) && ! isset($row[$col])) {
                    $row[$col] = $record[$col];
                }
            }

            $row['id'] = (string) Str::ulid();
            $row['created_at'] = $now;
            $row['updated_at'] = $now;

            $rows[] = array_intersect_key($row, array_flip($pivotColumns));
        }

        if ($rows === []) {
            return;
        }

        $beforeDedupCount = count($rows);
        $rows = self::deduplicatePivotRows($rows, $uniqueBy);
        $removedDuplicates = $beforeDedupCount - count($rows);

        if ($removedDuplicates > 0) {
            Log::warning('TenantRecordPersister: registros duplicados removidos antes do upsert de pivot', [
                'table' => $table,
                'removed' => $removedDuplicates,
            ]);
        }

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            self::upsertPivotChunk($table, $chunk, $uniqueBy, $pivotColumns);
        }

        Log::info('TenantRecordPersister: pivot persistida', [
            'table' => $table,
            'rows' => count($rows),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $chunk
     * @param  array<int, string>  $uniqueBy
     * @param  array<int, string>  $pivotColumns
     */
    private static function upsertPivotChunk(string $table, array $chunk, array $uniqueBy, array $pivotColumns): void
    {
        try {
            DB::connection('tenant')->table($table)->upsert(
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

            Log::warning('TenantRecordPersister: fallback do upsert de pivot com tenant_id no conflict target', [
                'table' => $table,
                'original_unique_by' => $uniqueBy,
                'fallback_unique_by' => $fallbackUniqueBy,
                'chunk_size' => count($chunk),
                'chunk_size_after_dedup' => count($dedupedChunk),
            ]);

            DB::connection('tenant')->table($table)->upsert(
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
     * @return array<int, array<string, mixed>>
     */
    private static function deduplicateById(array $rows): array
    {
        $indexed = [];
        $duplicates = [];

        foreach ($rows as $row) {
            $id = $row['id'] ?? null;

            if (! is_scalar($id) || (string) $id === '') {
                continue;
            }

            $normalizedId = trim((string) $id);

            if ($normalizedId === '') {
                continue;
            }

            if (isset($indexed[$normalizedId])) {
                $duplicates[$normalizedId] = true;
            }

            $row['id'] = $normalizedId;

            // Keep the last occurrence for the same id within the batch.
            $indexed[$normalizedId] = $row;
        }

        if ($duplicates !== []) {
            Log::warning('TenantRecordPersister: ids duplicados detectados no lote', [
                'count' => count($duplicates),
                'sample' => array_slice(array_keys($duplicates), 0, 10),
            ]);
        }

        return array_values($indexed);
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

            // Keep the last occurrence for the same unique key in this batch.
            $indexed[$compositeKey] = $row;
        }

        return array_values($indexed);
    }
}
