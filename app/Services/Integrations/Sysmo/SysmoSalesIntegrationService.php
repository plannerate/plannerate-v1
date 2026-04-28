<?php

namespace App\Services\Integrations\Sysmo;

use App\Models\TenantIntegration;
use App\Services\Integrations\Contracts\SalesIntegrationService;
use App\Services\Integrations\ExternalApiBaseService;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SysmoSalesIntegrationService implements SalesIntegrationService
{
    public function __construct(
        private readonly ExternalApiBaseService $externalApiBaseService,
        private readonly SysmoEndpoints $sysmoEndpoints,
        private readonly SysmoSalesResponseMapper $responseMapper,
        private readonly DeterministicIdGenerator $deterministicIdGenerator,
    ) {}

    public function fetchSales(TenantIntegration $integration, array $filters = []): array
    {
        $requestBody = [
            'pagina' => (int) ($filters['page'] ?? 1),
            'tamanho_pagina' => (int) ($filters['page_size'] ?? 20000),
            'partner_key' => (string) ($filters['partner_key'] ?? ''),
            'tipo_consulta' => (string) ($filters['tipo_consulta'] ?? 'produto'),
        ];

        if (is_string($filters['date'] ?? null) && $filters['date'] !== '') {
            $requestBody['data_inicial'] = $filters['date'];
            $requestBody['data_final'] = $filters['date'];
        }

        if (is_string($filters['empresa'] ?? null) && $filters['empresa'] !== '') {
            $requestBody['empresa'] = $filters['empresa'];
        }

        $response = $this->externalApiBaseService->request(
            integration: $integration,
            method: strtoupper((string) $integration->http_method),
            endpoint: $this->sysmoEndpoints->get('sales'),
            body: $requestBody,
        );

        $mappedItems = $this->responseMapper->mapMany($this->extractItems($response->json()));

        $this->persistMappedSales(
            tenantId: (string) $integration->tenant_id,
            integrationId: (string) $integration->id,
            mappedItems: $mappedItems,
            storeId: is_string($filters['store_id'] ?? null) ? $filters['store_id'] : null,
            storeDocument: is_string($filters['store_document'] ?? null) ? $filters['store_document'] : null,
        );

        return $mappedItems;
    }

    /**
     * @param  array<int, array<string, mixed>>  $mappedItems
     */
    public function persistMappedSales(
        string $tenantId,
        string $integrationId,
        array $mappedItems,
        ?string $storeId = null,
        ?string $storeDocument = null,
    ): void {
        if ($tenantId === '' || $mappedItems === []) {
            return;
        }

        $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
        $salesTable = DB::connection($tenantConnectionName)->table('sales');
        $now = Carbon::now();
        $rowsToUpsert = [];
        $erpCodes = [];
        $missingStoreDocumentCount = 0;
        $missingStoreDocumentExamples = [];

        foreach ($mappedItems as $item) {
            $codigoErp = $this->normalizeString($item['codigo_erp'] ?? $item['product_code'] ?? null);
            $saleDate = $this->normalizeDate($item['sold_at'] ?? null);

            if ($codigoErp === null || $saleDate === null) {
                continue;
            }

            $promotion = $this->normalizeString($item['promocao'] ?? null);
            $saleStoreDocument = $this->normalizeString($item['store_identifier'] ?? null) ?? $storeDocument;
            if ($saleStoreDocument === null) {
                $missingStoreDocumentCount++;

                if (count($missingStoreDocumentExamples) < 5) {
                    $missingStoreDocumentExamples[] = [
                        'codigo_erp' => $codigoErp,
                        'sale_date' => $saleDate,
                    ];
                }

                continue;
            }
            $erpCodes[] = $codigoErp;

            $rowId = $this->generateSaleId(
                tenantId: $tenantId,
                integrationId: $integrationId,
                storeDocument: $saleStoreDocument,
                codigoErp: $codigoErp,
                saleDate: $saleDate,
                promotion: $promotion,
            );

            $rowsToUpsert[] = [
                'id' => $rowId,
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'product_id' => null,
                'ean' => null,
                'codigo_erp' => $codigoErp,
                'acquisition_cost' => $item['custo_aquisicao'] ?? null,
                'sale_price' => $item['unit_price'] ?? null,
                'sale_date' => $saleDate,
                'promotion' => $promotion,
                'total_sale_quantity' => $item['quantity'] ?? null,
                'total_sale_value' => $item['total_price'] ?? null,
                'total_profit_margin' => $this->convertToFloat(data_get($item, 'custo_comercial')),
                'margem_contribuicao' => $this->calculateMargemContribuicao(
                    $item,
                    $this->convertToFloat(data_get($item, 'total_price')),
                ),
                'extra_data' => json_encode([
                    'empresa' => $item['empresa'] ?? null,
                    'valor_liquido' => $item['valor_liquido'] ?? null,
                    'valor_impostos' => $item['valor_impostos'] ?? null,
                    'custo_medio_loja' => $item['custo_medio_loja'] ?? null,
                    'custo_medio_geral' => $item['custo_medio_geral'] ?? null,
                    'custo_comercial' => $item['custo_comercial'] ?? null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($missingStoreDocumentCount > 0) {
            Log::warning('Integrations sales skipped rows due to missing store document.', [
                'tenant_id' => $tenantId,
                'integration_id' => $integrationId,
                'store_id' => $storeId,
                'missing_store_document_count' => $missingStoreDocumentCount,
                'mapped_items_count' => count($mappedItems),
                'missing_store_document_examples' => $missingStoreDocumentExamples,
            ]);
        }

        if ($rowsToUpsert === []) {
            return;
        }

        $salesTable->upsert(
            $rowsToUpsert,
            ['id'],
            [
                'tenant_id',
                'store_id',
                'codigo_erp',
                'acquisition_cost',
                'sale_price',
                'sale_date',
                'promotion',
                'total_sale_quantity',
                'total_sale_value',
                'total_profit_margin',
                'margem_contribuicao',
                'extra_data',
                'updated_at',
            ]
        );

        $this->syncSaleProductReferences(
            tenantConnectionName: $tenantConnectionName,
            tenantId: $tenantId,
            erpCodes: array_values(array_unique($erpCodes)),
            now: $now,
        );
    }

    /**
     * Atualiza product_id e ean das vendas pelo codigo_erp em lote.
     *
     * @param  array<int, string>  $erpCodes
     */
    private function syncSaleProductReferences(
        string $tenantConnectionName,
        string $tenantId,
        array $erpCodes,
        Carbon $now,
    ): void {
        if ($erpCodes === []) {
            return;
        }

        $connection = DB::connection($tenantConnectionName);
        $driver = $connection->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $salesTable = $connection->getTablePrefix().'sales';
            $productsTable = $connection->getTablePrefix().'products';

            foreach (array_chunk($erpCodes, 500) as $erpChunk) {
                $inPlaceholders = implode(', ', array_fill(0, count($erpChunk), '?'));

                $sql = "
                    UPDATE {$salesTable} s
                    INNER JOIN {$productsTable} p
                        ON p.tenant_id = s.tenant_id
                       AND p.codigo_erp = s.codigo_erp
                       AND p.deleted_at IS NULL
                    SET s.product_id = p.id,
                        s.ean = p.ean,
                        s.updated_at = ?
                    WHERE s.tenant_id = ?
                      AND s.codigo_erp IN ({$inPlaceholders})
                ";

                $connection->update($sql, [
                    $now,
                    $tenantId,
                    ...$erpChunk,
                ]);
            }

            return;
        }

        // Fallback compatível com SQLite para ambiente de testes.
        $connection->table('sales')
            ->where('tenant_id', $tenantId)
            ->whereIn('codigo_erp', $erpCodes)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('products')
                    ->whereColumn('products.tenant_id', 'sales.tenant_id')
                    ->whereColumn('products.codigo_erp', 'sales.codigo_erp')
                    ->whereNull('products.deleted_at');
            })
            ->update([
                'product_id' => DB::raw('(SELECT products.id FROM products WHERE products.tenant_id = sales.tenant_id AND products.codigo_erp = sales.codigo_erp AND products.deleted_at IS NULL LIMIT 1)'),
                'ean' => DB::raw('(SELECT products.ean FROM products WHERE products.tenant_id = sales.tenant_id AND products.codigo_erp = sales.codigo_erp AND products.deleted_at IS NULL LIMIT 1)'),
                'updated_at' => $now,
            ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractItems(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if (is_array($payload['data'] ?? null)) {
            /** @var array<int, array<string, mixed>> $data */
            $data = array_values(array_filter($payload['data'], 'is_array'));

            return $data;
        }

        if (is_array($payload['items'] ?? null)) {
            /** @var array<int, array<string, mixed>> $items */
            $items = array_values(array_filter($payload['items'], 'is_array'));

            return $items;
        }

        if (is_array($payload['dados'] ?? null)) {
            /** @var array<int, array<string, mixed>> $dados */
            $dados = array_values(array_filter($payload['dados'], 'is_array'));

            return $dados;
        }

        if (array_is_list($payload)) {
            /** @var array<int, array<string, mixed>> $list */
            $list = array_values(array_filter($payload, 'is_array'));

            return $list;
        }

        return [];
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $dateValue = $this->normalizeString($value);
        if ($dateValue === null) {
            return null;
        }

        try {
            return Carbon::parse($dateValue)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function generateSaleId(
        string $tenantId,
        string $integrationId,
        ?string $storeDocument,
        string $codigoErp,
        string $saleDate,
        ?string $promotion,
    ): string {
        return $this->deterministicIdGenerator->saleId(
            tenantId: $tenantId,
            integrationId: $integrationId,
            storeDocument: $storeDocument,
            codigoErp: $codigoErp,
            saleDate: $saleDate,
            promotion: $promotion,
        );
    }

    /**
     * Calcula a margem de contribuição da venda.
     *
     * Fórmula: total_sale_value - valor_impostos - custo_medio_loja.
     *
     * @param  array<string, mixed>  $sale
     */
    private function calculateMargemContribuicao(array $sale, ?float $totalSaleValue): ?float
    {
        if ($totalSaleValue === null) {
            return null;
        }

        $valorImpostos = $this->convertToFloat(data_get($sale, 'valor_impostos', 0)) ?? 0.0;
        $custoMedioLoja = $this->convertToFloat(data_get($sale, 'custo_medio_loja', 0)) ?? 0.0;

        return round($totalSaleValue - $valorImpostos - $custoMedioLoja, 2);
    }

    private function convertToFloat(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', trim($value));

            return is_numeric($normalized) ? (float) $normalized : null;
        }

        return null;
    }
}
