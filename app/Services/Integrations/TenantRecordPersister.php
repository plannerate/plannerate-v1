<?php

namespace App\Services\Integrations;

use App\Models\TenantIntegration;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use Illuminate\Support\Facades\Log;

/**
 * Mapeia e persiste registros importados na tabela alvo do tenant.
 */
class TenantRecordPersister
{
    public function __construct(
        private readonly RecordMapper $mapper,
        private readonly DeterministicIdGenerator $idGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $pathConfig
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(
        TenantIntegration $integration,
        array $pathConfig,
        ?string $storeId,
        array $items,
    ): void {
        $fieldMap = (array) data_get($pathConfig, 'field_map', []);
        $targetTable = (string) data_get($pathConfig, 'target_table', '');
        $tenantId = (string) $integration->tenant_id;
        $integrationId = (string) $integration->id;

        $records = array_map(function (array $item) use ($fieldMap, $pathConfig, $tenantId, $integrationId, $storeId): array {
            $record = $this->mapper->map($item, $fieldMap, $storeId);
            $record['id'] = $this->idGenerator->fromRecord($tenantId, $integrationId, $record, $pathConfig, $storeId);

            return $record;
        }, $items);

        Log::info('TenantRecordPersister: registros mapeados', [
            'integration_id' => $integrationId,
            'target_table' => $targetTable,
            'store_id' => $storeId,
            'count' => count($records),
            'sample' => array_slice($records, 0, 2),
        ]);

        // TODO: persist to tenant DB
        // $integration->tenant->execute(function () use ($targetTable, $records, $pathConfig) {
        //     $uniqueBy = (array) data_get($pathConfig, 'unique_by', []);
        //     DB::table($targetTable)->upsert($records, ['id'], array_keys($records[0]));
        // });
    }
}
