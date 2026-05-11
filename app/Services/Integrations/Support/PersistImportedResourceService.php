<?php

namespace App\Services\Integrations\Support;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PersistImportedResourceService
{
    public function __construct(
        private readonly FieldResolver $fieldResolver,
        private readonly ResolvedIntegrationConfigResolver $configResolver,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function persist(
        TenantIntegration $integration,
        string $provider,
        string $resource,
        string $targetTable,
        array $items,
        ?Store $store = null,
    ): void {
        if ($items === []) {
            return;
        }

        $tenant = $integration->tenant;
        if (! $tenant instanceof Tenant) {
            Log::warning('Persistência de recurso ignorada: tenant não encontrado.', [
                'integration_id' => (string) $integration->id,
                'provider' => $provider,
                'resource' => $resource,
                'target_table' => $targetTable,
            ]);

            return;
        }

        $tenant->execute(function () use ($integration, $provider, $resource, $targetTable, $items, $store, $tenant): void {
            $this->persistInTenantContext($integration, $tenant, $provider, $resource, $targetTable, $items, $store);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function persistInTenantContext(
        TenantIntegration $integration,
        Tenant $tenant,
        string $provider,
        string $resource,
        string $targetTable,
        array $items,
        ?Store $store,
    ): void {
        $connectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
        $tenantId = (string) $tenant->id;

        if (! $this->validTableName($targetTable) || ! Schema::connection($connectionName)->hasTable($targetTable)) {
            Log::warning('Persistência de recurso ignorada: tabela de destino inválida ou inexistente.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'resource' => $resource,
                'target_table' => $targetTable,
            ]);

            return;
        }

        $columns = Schema::connection($connectionName)->getColumnListing($targetTable);
        $columnSet = array_fill_keys($columns, true);
        $uniqueBy = $this->effectiveUniqueBy($integration, $resource, $columnSet);

        if ($uniqueBy === []) {
            Log::warning('Persistência de recurso ignorada: unique_by não configurado.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'resource' => $resource,
                'target_table' => $targetTable,
            ]);

            return;
        }

        $fieldMap = $this->configResolver->resolve($integration)->fieldMap($resource);
        if ($fieldMap === []) {
            Log::warning('Persistência de recurso ignorada: field_map não configurado.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'resource' => $resource,
                'target_table' => $targetTable,
            ]);

            return;
        }

        $now = Carbon::now();
        $rows = [];
        $invalidCount = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $row = $this->rowFromItem($fieldMap, $item);
            $row = array_intersect_key($row, $columnSet);

            if (isset($columnSet['tenant_id'])) {
                $row['tenant_id'] = $tenantId;
            }

            if ($store instanceof Store && is_string($store->id) && $store->id !== '' && isset($columnSet['store_id'])) {
                $row['store_id'] = (string) $store->id;
            }

            if (isset($columnSet['created_at'])) {
                $row['created_at'] ??= $now;
            }

            if (isset($columnSet['updated_at'])) {
                $row['updated_at'] = $now;
            }

            if (isset($columnSet['deleted_at'])) {
                $row['deleted_at'] = null;
            }

            if (! $this->hasUniqueValues($row, $uniqueBy)) {
                $invalidCount++;

                continue;
            }

            if (isset($columnSet['id']) && ! $this->hasValue($row['id'] ?? null)) {
                $row['id'] = $this->deterministicId($tenantId, $targetTable, $row, $uniqueBy);
            }

            $rows[] = $row;
        }

        if ($rows === []) {
            Log::warning('Persistência de recurso não encontrou itens válidos.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'resource' => $resource,
                'target_table' => $targetTable,
                'items_count' => count($items),
                'invalid_count' => $invalidCount,
            ]);

            return;
        }

        $rows = collect($rows)
            ->keyBy(fn (array $row): string => $this->uniqueRowKey($row, $uniqueBy))
            ->values()
            ->all();

        $updateColumns = collect(array_keys($rows[0] ?? []))
            ->reject(fn (string $column): bool => in_array($column, $uniqueBy, true) || in_array($column, ['id', 'created_at'], true))
            ->values()
            ->all();

        if ($updateColumns === []) {
            DB::connection($connectionName)->table($targetTable)->insertOrIgnore($rows);
        } else {
            DB::connection($connectionName)->table($targetTable)->upsert($rows, $uniqueBy, $updateColumns);
        }

        Log::info('Persistência de recurso concluída.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'resource' => $resource,
            'target_table' => $targetTable,
            'items_count' => count($items),
            'invalid_count' => $invalidCount,
            'upserted_rows' => count($rows),
        ]);
    }

    /**
     * @param  array<string, mixed>  $fieldMap
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function rowFromItem(array $fieldMap, array $item): array
    {
        $row = [];
        $expressions = [];

        foreach ($fieldMap as $field => $definition) {
            if (is_array($definition) && is_string($definition['expression'] ?? null)) {
                $expressions[$field] = $definition;

                continue;
            }

            $row[$field] = $this->fieldResolver->resolve($item, $definition);
        }

        foreach ($expressions as $field => $definition) {
            $row[$field] = $this->fieldResolver->resolveExpression(
                $row,
                (string) $definition['expression'],
                is_array($definition['transforms'] ?? null) ? $definition['transforms'] : [],
                $item,
            );
        }

        return $row;
    }

    /**
     * @param  array<string, bool>  $columnSet
     * @return list<string>
     */
    private function effectiveUniqueBy(TenantIntegration $integration, string $resource, array $columnSet): array
    {
        $uniqueBy = $this->configResolver->resolve($integration)->uniqueBy($resource);

        if ($uniqueBy === []) {
            return [];
        }

        $uniqueBy = collect($uniqueBy)
            ->filter(fn (string $column): bool => isset($columnSet[$column]))
            ->values()
            ->all();

        if (isset($columnSet['tenant_id']) && ! in_array('tenant_id', $uniqueBy, true)) {
            array_unshift($uniqueBy, 'tenant_id');
        }

        return array_values(array_unique($uniqueBy));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $uniqueBy
     */
    private function hasUniqueValues(array $row, array $uniqueBy): bool
    {
        foreach ($uniqueBy as $column) {
            if (! $this->hasValue($row[$column] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $uniqueBy
     */
    private function deterministicId(string $tenantId, string $targetTable, array $row, array $uniqueBy): string
    {
        $identity = collect($uniqueBy)
            ->map(fn (string $column): string => (string) ($row[$column] ?? ''))
            ->implode('|');
        $hash = hash('sha256', $tenantId.'|'.$targetTable.'|'.$identity);

        return 'G1'.strtoupper(substr($hash, 0, 24));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $uniqueBy
     */
    private function uniqueRowKey(array $row, array $uniqueBy): string
    {
        return collect($uniqueBy)
            ->map(fn (string $column): string => (string) ($row[$column] ?? ''))
            ->implode('|');
    }

    private function hasValue(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    private function validTableName(string $table): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $table) === 1;
    }
}
