<?php

namespace App\Services\Integrations;

use App\Models\TenantIntegration;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Persiste registros pré-mapeados na tabela principal do tenant via upsert.
 *
 * A persistência de tabelas pivot é delegada ao TenantPivotRecordPersister para
 * manter o fluxo principal enxuto e escalável.
 */
class TenantRecordPersister
{
    private const CHUNK_SIZE = 500;

    private const TENANT_CONNECTION = 'tenant';

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
        if (self::shouldSkipPersist($targetTable, $records)) {
            return;
        }

        $integrationId = (string) $integration->id;

        self::logPersistStart($integrationId, $targetTable, $records, $pivotConfigs);

        $upserted = self::persistWithinTenant($integration, $targetTable, $records, $pivotConfigs);

        self::logPersistFinished($integrationId, $targetTable, $upserted);
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private static function shouldSkipPersist(string $targetTable, array $records): bool
    {
        return $targetTable === '' || $records === [];
    }

    /**
     * Registra o contexto inicial da operação para facilitar rastreabilidade.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, array<string, mixed>>  $pivotConfigs
     */
    private static function logPersistStart(string $integrationId, string $targetTable, array $records, array $pivotConfigs): void
    {
        Log::debug('TenantRecordPersister: iniciando persist', [
            'integration_id' => $integrationId,
            'target_table' => $targetTable,
            'records' => count($records),
            'pivot_configs' => count($pivotConfigs),
            'pivot_tables' => array_column($pivotConfigs, 'table'),
            'first_record_keys' => array_keys($records[0] ?? []),
        ]);
    }

    private static function logPersistFinished(string $integrationId, string $targetTable, int $upserted): void
    {
        Log::info('TenantRecordPersister: registros persistidos', [
            'integration_id' => $integrationId,
            'target_table' => $targetTable,
            'upserted' => $upserted,
        ]);
    }

    /**
     * Executa toda a persistência em contexto de tenant e em uma única transação.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, array<string, mixed>>  $pivotConfigs
     */
    private static function persistWithinTenant(
        TenantIntegration $integration,
        string $targetTable,
        array $records,
        array $pivotConfigs,
    ): int {
        $upserted = 0;

        $integration->tenant->execute(function () use ($targetTable, $records, $pivotConfigs, &$upserted): void {
            if (! self::tenantTableExists($targetTable)) {
                Log::warning('TenantRecordPersister: tabela não encontrada', ['table' => $targetTable]);

                return;
            }

            $upserted = DB::connection(self::TENANT_CONNECTION)->transaction(
                function () use ($targetTable, $records, $pivotConfigs): int {
                    $tableColumns = self::tenantTableColumns($targetTable);
                    $preparedRecords = TenantUpsertRecordPreparer::prepare($records, $tableColumns, $targetTable);

                    $upserted = self::upsertTargetRecords($targetTable, $preparedRecords);

                    TenantPivotRecordPersister::persist(self::tenantConnection(), $records, $pivotConfigs);

                    return $upserted;
                },
            );
        });

        return $upserted;
    }

    private static function tenantTableExists(string $table): bool
    {
        return self::tenantConnection()->getSchemaBuilder()->hasTable($table);
    }

    /**
     * @return array<int, string>
     */
    private static function tenantTableColumns(string $table): array
    {
        return self::tenantConnection()->getSchemaBuilder()->getColumnListing($table);
    }

    private static function tenantConnection(): Connection
    {
        return DB::connection(self::TENANT_CONNECTION);
    }

    /**
     * Persiste o conjunto principal em lotes para manter previsibilidade de memória e lock.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    private static function upsertTargetRecords(string $targetTable, array $records): int
    {
        if ($records === []) {
            return 0;
        }

        $updateColumns = TenantUpsertRecordPreparer::resolveUpdateColumns($records[0]);
        $upserted = 0;

        foreach (array_chunk($records, self::CHUNK_SIZE) as $chunk) {
            $deduplicatedChunk = TenantUpsertRecordPreparer::deduplicateById($chunk);

            if ($deduplicatedChunk === []) {
                continue;
            }

            self::tenantConnection()->table($targetTable)->upsert(
                $deduplicatedChunk,
                ['id'],
                $updateColumns,
            );

            $upserted += count($deduplicatedChunk);
        }

        return $upserted;
    }
}
