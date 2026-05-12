<?php

namespace App\Services\Integrations;

use App\Models\TenantIntegration;
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

            if ($filtered !== []) {
                $updateColumns = array_values(array_diff(array_keys($filtered[0]), ['id', 'created_at']));

                foreach (array_chunk($filtered, self::CHUNK_SIZE) as $chunk) {
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

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            DB::connection('tenant')->table($table)->upsert(
                $chunk,
                $uniqueBy,
                ['updated_at'],
            );
        }

        Log::info('TenantRecordPersister: pivot persistida', [
            'table' => $table,
            'rows' => count($rows),
        ]);
    }
}
