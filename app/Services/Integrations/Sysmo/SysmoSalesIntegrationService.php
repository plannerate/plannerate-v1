<?php

namespace App\Services\Integrations\Sysmo;

use App\Models\Product;
use App\Models\TenantIntegration;
use App\Services\Integrations\Contracts\SalesIntegrationService;
use App\Services\Integrations\ExternalApiBaseService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SysmoSalesIntegrationService implements SalesIntegrationService
{
    public function __construct(
        private readonly ExternalApiBaseService $externalApiBaseService,
        private readonly SysmoEndpoints $sysmoEndpoints,
        private readonly SysmoSalesResponseMapper $responseMapper,
    ) {}

    public function fetchSales(TenantIntegration $integration, array $filters = []): array
    {
        $requestBody = [
            'pagina' => (int) ($filters['page'] ?? 1),
            'tamanho_pagina' => (int) ($filters['page_size'] ?? 1000),
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
        );

        return $mappedItems;
    }

    /**
     * @param  array<int, array<string, mixed>>  $mappedItems
     */
    public function persistMappedSales(string $tenantId, string $integrationId, array $mappedItems): void
    {
        if ($tenantId === '' || $mappedItems === []) {
            return;
        }

        $erpCodes = [];
        foreach ($mappedItems as $item) {
            $erpCode = $this->normalizeString($item['codigo_erp'] ?? $item['product_code'] ?? null);
            if ($erpCode !== null) {
                $erpCodes[] = $erpCode;
            }
        }

        $productsByErp = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('codigo_erp', array_values(array_unique($erpCodes)))
            ->get(['id', 'codigo_erp', 'ean'])
            ->keyBy('codigo_erp');

        foreach ($mappedItems as $item) {
            $codigoErp = $this->normalizeString($item['codigo_erp'] ?? $item['product_code'] ?? null);
            $saleDate = $this->normalizeDate($item['sold_at'] ?? null);

            if ($codigoErp === null || $saleDate === null) {
                continue;
            }

            $product = $productsByErp->get($codigoErp);
            $promotion = $this->normalizeString($item['promocao'] ?? null);
            $lookup = [
                'tenant_id' => $tenantId,
                'store_id' => null,
                'codigo_erp' => $codigoErp,
                'sale_date' => $saleDate,
                'promotion' => $promotion,
            ];
            $payload = [
                'product_id' => $product?->id,
                'ean' => $product?->ean,
                'acquisition_cost' => $item['custo_aquisicao'] ?? null,
                'sale_price' => $item['unit_price'] ?? null,
                'total_sale_quantity' => $item['quantity'] ?? null,
                'total_sale_value' => $item['total_price'] ?? null,
                'extra_data' => json_encode([
                    'empresa' => $item['empresa'] ?? null,
                    'valor_liquido' => $item['valor_liquido'] ?? null,
                    'valor_impostos' => $item['valor_impostos'] ?? null,
                    'custo_medio_loja' => $item['custo_medio_loja'] ?? null,
                    'custo_medio_geral' => $item['custo_medio_geral'] ?? null,
                    'custo_comercial' => $item['custo_comercial'] ?? null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => Carbon::now(),
            ];
            $existingId = DB::table('sales')->where($lookup)->value('id');

            if ($existingId !== null) {
                DB::table('sales')->where('id', $existingId)->update($payload);

                continue;
            }

            DB::table('sales')->insert(array_merge($lookup, $payload, [
                'id' => $this->generateSaleId(
                    tenantId: $tenantId,
                    integrationId: $integrationId,
                    codigoErp: $codigoErp,
                    saleDate: $saleDate,
                    promotion: $promotion,
                ),
                'created_at' => Carbon::now(),
            ]));
        }
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
        string $codigoErp,
        string $saleDate,
        ?string $promotion,
    ): string {
        $uniqueKey = implode('|', [
            $tenantId,
            $integrationId,
            preg_replace('/[^A-Za-z0-9]/', '', $codigoErp) ?? $codigoErp,
            preg_replace('/[^0-9]/', '', $saleDate) ?? $saleDate,
            strtoupper($promotion ?? 'N'),
        ]);

        $hash = hash('sha256', $uniqueKey);

        return 'S1'.strtoupper(substr($hash, 0, 24));
    }
}
