<?php

namespace App\Services\Integrations\Support;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PersistImportedSalesService
{
    public function __construct(
        private readonly DeterministicIdGenerator $deterministicIdGenerator,
        private readonly FieldResolver $fieldResolver,
        private readonly ResolvedIntegrationConfigResolver $configResolver,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function persist(
        TenantIntegration $integration,
        string $provider,
        array $items,
        ?Store $store = null,
    ): void {
        if ($items === []) {
            return;
        }

        $tenant = $integration->tenant;
        if (! $tenant instanceof Tenant) {
            Log::warning('Persistência de vendas ignorada: tenant não encontrado.', [
                'integration_id' => (string) $integration->id,
                'provider' => $provider,
            ]);

            return;
        }

        $tenant->execute(function () use ($integration, $provider, $items, $store, $tenant): void {
            $this->persistInTenantContext($integration, $tenant, $provider, $items, $store);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function persistInTenantContext(
        TenantIntegration $integration,
        Tenant $tenant,
        string $provider,
        array $items,
        ?Store $store,
    ): void {
        $connectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
        $tenantId = (string) $tenant->id;
        $now = Carbon::now();
        $rows = [];
        $invalidCount = 0;

        $fields = $this->fieldMap($integration, 'sales');

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $mapped = [];
            $expressions = [];
            foreach ($fields as $field => $definition) {
                if (is_array($definition) && is_string($definition['expression'] ?? null)) {
                    $expressions[$field] = $definition;

                    continue;
                }

                $mapped[$field] = $this->fieldResolver->resolve($item, $definition);
            }

            foreach ($expressions as $field => $definition) {
                $mapped[$field] = $this->fieldResolver->resolveExpression(
                    $mapped,
                    (string) $definition['expression'],
                    is_array($definition['transforms'] ?? null) ? $definition['transforms'] : [],
                    $item,
                );
            }

            $normalized = SalesNormalizedData::fromMapped($mapped, $item, $store?->document);
            if (! $normalized instanceof SalesNormalizedData) {
                $invalidCount++;

                continue;
            }

            $saleId = $this->deterministicIdGenerator->saleId(
                tenantId: $tenantId,
                integrationId: (string) $integration->id,
                storeDocument: $normalized->storeDocument,
                codigoErp: $normalized->codigoErp,
                saleDate: $normalized->saleDate,
                promotion: $normalized->promotion,
            );

            $rows[] = [
                'id' => $saleId,
                'tenant_id' => $tenantId,
                'store_id' => $store?->id,
                'product_id' => null,
                'ean' => $normalized->ean,
                'codigo_erp' => $normalized->codigoErp,
                'acquisition_cost' => $normalized->acquisitionCost,
                'sale_price' => $normalized->salePrice,
                'total_profit_margin' => $normalized->totalProfitMargin,
                'sale_date' => $normalized->saleDate,
                'promotion' => $normalized->promotion,
                'total_sale_quantity' => $normalized->totalSaleQuantity,
                'total_sale_value' => $normalized->totalSaleValue,
                'margem_contribuicao' => $normalized->margemContribuicao ?? $this->margemContribuicao(
                    $normalized->totalSaleValue,
                    $normalized->valorImpostos,
                    $normalized->custoMedioLoja
                ),
                'extra_data' => json_encode([
                    'provider' => $provider,
                    'store_document' => $normalized->storeDocument,
                    'raw' => $normalized->raw,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
        }

        if ($rows === []) {
            Log::warning('Persistência de vendas não encontrou itens válidos.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'items_count' => count($items),
                'invalid_count' => $invalidCount,
            ]);

            return;
        }

        $rows = $this->deduplicateRowsById($rows);

        DB::connection($connectionName)->table('sales')->upsert(
            $rows,
            ['id'],
            [
                'tenant_id',
                'store_id',
                'product_id',
                'ean',
                'codigo_erp',
                'acquisition_cost',
                'sale_price',
                'total_profit_margin',
                'sale_date',
                'promotion',
                'total_sale_quantity',
                'total_sale_value',
                'margem_contribuicao',
                'extra_data',
                'updated_at',
                'deleted_at',
            ],
        );

        Log::info('Persistência de vendas concluída.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'items_count' => count($items),
            'invalid_count' => $invalidCount,
            'upserted_sales' => count($rows),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function deduplicateRowsById(array $rows): array
    {
        return collect($rows)
            ->keyBy(fn (array $row): string => (string) $row['id'])
            ->values()
            ->all();
    }

    private function margemContribuicao(?float $totalSaleValue, ?float $valorImpostos, ?float $custoMedioLoja): ?float
    {
        if ($totalSaleValue === null) {
            return null;
        }

        return round($totalSaleValue - ($valorImpostos ?? 0.0) - ($custoMedioLoja ?? 0.0), 2);
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function fieldMap(TenantIntegration $integration, string $resource, array $fallback = []): array
    {
        return $this->configResolver->resolve($integration)->fieldMap($resource, $fallback);
    }
}
