<?php

namespace App\Services\Integrations\Sysmo;

use App\Models\EanReference;
use App\Models\TenantIntegration;
use App\Services\Integrations\Contracts\ProductsIntegrationService;
use App\Services\Integrations\ExternalApiBaseService;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use App\Services\Integrations\Support\SyncSalesProductReferencesService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SysmoProductsIntegrationService implements ProductsIntegrationService
{
    /**
     * EANs monitorados temporariamente para diagnosticar recusas no fluxo de busca geral.
     *
     * @var array<int, string>
     */
    private const DEBUG_REJECTED_EANS = [
        '7896032501010',
        '7896084700157',
        '7896272200261',
        '7891150072466',
        '7891150086456',
        '7891150086500',
        '7891150086432',
        '7891150086449',
        '7500435244619',
        '7891150092716',
        '7891150054561',
        '7891150061965',
        '7891150048485',
        '7896040704120',
        '7891150028883',
        '7891150025288',
        '7896075910022',
        '7891022101003',
        '7891242000025',
        '7891022101478',
        '7891035502231',
        '7896115700187',
        '7896063243026',
        '7891000304808',
        '7891000300602',
        '7896348300895',
        '7896348300918',
        '7896089089905',
        '7896089089912',
        '7896045102990',
        '7896005800706',
        '7896045103003',
        '7891000184004',
        '7896045111398',
        '7891000721834',
        '7891000284230',
        '7891000284155',
        '7891000379691',
        '7896004007649',
        '7896004007632',
        '7896202800318',
        '7896104804414',
        '7896104802496',
        '7896653708928',
        '7898915414776',
        '7896180710043',
        '7896016600104',
        '7896004400136',
        '7896411800062',
        '7896411800017',
    ];

    public function __construct(
        private readonly ExternalApiBaseService $externalApiBaseService,
        private readonly SysmoEndpoints $sysmoEndpoints,
        private readonly SysmoProductsResponseMapper $responseMapper,
        private readonly DeterministicIdGenerator $deterministicIdGenerator,
        private readonly SyncSalesProductReferencesService $syncSalesProductReferencesService,
    ) {}

    public function fetchProducts(TenantIntegration $integration, array $filters = []): array
    {
        $payload = $this->requestProducts($integration, $filters);
        $mappedItems = $this->responseMapper->mapMany($this->extractItems($payload));

        $this->persistMappedProducts(
            tenantId: (string) $integration->tenant_id,
            source: (string) ($integration->integration_type ?: 'sysmo'),
            mappedItems: $mappedItems,
            storeId: is_string($filters['store_id'] ?? null) ? $filters['store_id'] : null,
        );

        return $mappedItems;
    }

    public function discoverProductsTotalPages(TenantIntegration $integration, array $filters = []): int
    {
        $payload = $this->requestProducts($integration, array_merge($filters, ['page' => 1]));
        $totalPages = $payload['total_paginas'] ?? $payload['total_pages'] ?? 1;

        return max(1, (int) $totalPages);
    }

    /**
     * @param  array<int, array<string, mixed>>  $mappedItems
     */
    public function persistMappedProducts(
        string $tenantId,
        string $source,
        array $mappedItems,
        ?string $storeId = null,
    ): void {
        if ($tenantId === '' || $mappedItems === []) {
            return;
        }

        $eanValues = [];
        foreach ($mappedItems as $item) {
            $ean = $this->normalizeEan($item['ean'] ?? null);
            if ($ean !== null) {
                $eanValues[] = $ean;
            }
        }

        $references = EanReference::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('ean', array_values(array_unique($eanValues)))
            ->get()
            ->keyBy('ean');
        $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
        $now = Carbon::now();
        $productsRows = [];
        $invalidItemsCount = 0;
        $invalidItemsExamples = [];

        foreach ($mappedItems as $item) {
            $validationFailureReason = $this->getImportValidationFailureReason($item);
            if ($validationFailureReason !== null) {
                $invalidItemsCount++;
                $this->logTrackedRejectedEan(
                    tenantId: $tenantId,
                    storeId: $storeId,
                    item: $item,
                    reason: $validationFailureReason,
                    stage: 'validate_import_data',
                );

                if (count($invalidItemsExamples) < 5) {
                    $invalidItemsExamples[] = [
                        'codigo_erp' => $item['external_id'] ?? null,
                        'ean' => $item['ean'] ?? null,
                        'motivo' => $validationFailureReason,
                    ];
                }

                continue;
            }

            $normalizedEan = $this->normalizeEan($item['ean'] ?? null);
            $reference = $normalizedEan !== null ? $references->get($normalizedEan) : null;
            $externalId = $this->validateCodigoErp($this->normalizeString($item['external_id'] ?? null));

            if ($normalizedEan === null || $externalId === null) {
                $invalidItemsCount++;
                $this->logTrackedRejectedEan(
                    tenantId: $tenantId,
                    storeId: $storeId,
                    item: $item,
                    reason: $normalizedEan === null
                        ? 'EAN normalizado ausente/invalidado apos mapeamento'
                        : 'codigo_erp ausente ou invalido',
                    stage: 'validate_identity',
                );

                if (count($invalidItemsExamples) < 5) {
                    $invalidItemsExamples[] = [
                        'codigo_erp' => $item['external_id'] ?? null,
                        'ean' => $item['ean'] ?? null,
                    ];
                }

                continue;
            }

            $productId = $this->generateProductId(
                ean: $normalizedEan,
                tenantId: $tenantId,
                codigoErp: $externalId,
            );

            $productsRows[] = [
                'id' => $productId,
                'tenant_id' => $tenantId,
                'name' => $this->normalizeString($item['name'] ?? null) ?? $reference?->reference_description,
                'ean' => $normalizedEan,
                'codigo_erp' => $externalId,
                'current_stock' => $this->normalizeFloat(
                    $item['current_stock'] ?? data_get($item, 'raw.estoque.disponivel')
                ),
                'last_purchase_date' => $this->normalizeDate($item['last_purchase_date'] ?? null),
                'description' => $reference?->reference_description,
                'brand' => $this->normalizeString($item['brand'] ?? null),
                'unit_measure' => $this->normalizeString($item['unit'] ?? null),
                'sales_status' => $this->normalizeString($item['status'] ?? null),
                'status' => 'synced',
                'sync_source' => $source,
                'sync_at' => $now,
                'deleted_at' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ];

        }

        if ($invalidItemsCount > 0) {
            Log::warning('Sincronização de produtos ignorou itens com identidade inválida.', [
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'invalid_items_count' => $invalidItemsCount,
                'mapped_items_count' => count($mappedItems),
                'invalid_items_examples' => $invalidItemsExamples,
            ]);
        }

        if ($productsRows === []) {
            Log::warning('Sincronização de produtos não persistiu registros: nenhuma identidade válida encontrada.', [
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'items_count' => count($mappedItems),
            ]);

            return;
        }

        DB::connection($tenantConnectionName)->table('products')->upsert(
            $productsRows,
            ['id'],
            [
                'tenant_id',
                'name',
                'ean',
                'codigo_erp',
                'current_stock',
                'last_purchase_date',
                'description',
                'brand',
                'unit_measure',
                'sales_status',
                'status',
                'sync_source',
                'sync_at',
                'deleted_at',
                'updated_at',
            ]
        );

        if ($storeId !== null && $storeId !== '') {
            $pivotRows = [];

            foreach ($productsRows as $productRow) {
                $pivotRows[] = [
                    'id' => (string) str()->ulid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $productRow['id'],
                    'store_id' => $storeId,
                    'last_synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::connection($tenantConnectionName)->table('product_store')->upsert(
                $pivotRows,
                ['tenant_id', 'product_id', 'store_id'],
                ['last_synced_at', 'updated_at']
            );
        }
    }

    public function finalizePersistedProductsSync(string $tenantId): void
    {
        if ($tenantId === '') {
            return;
        }

        $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
        $now = Carbon::now();

        $connection = DB::connection($tenantConnectionName);
        $driver = $connection->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $productsTable = $connection->getTablePrefix().'products';
            $referencesTable = $connection->getTablePrefix().'ean_references';

            $connection->update(
                "
                UPDATE {$productsTable} p
                INNER JOIN {$referencesTable} r
                    ON r.tenant_id = p.tenant_id
                   AND r.ean = p.ean
                   AND r.deleted_at IS NULL
                SET p.category_id = COALESCE(r.category_id, p.category_id),
                    p.description = COALESCE(r.reference_description, p.description),
                    p.brand = COALESCE(r.brand, p.brand),
                    p.subbrand = COALESCE(r.subbrand, p.subbrand),
                    p.packaging_type = COALESCE(r.packaging_type, p.packaging_type),
                    p.packaging_size = COALESCE(r.packaging_size, p.packaging_size),
                    p.measurement_unit = COALESCE(r.measurement_unit, p.measurement_unit),
                    p.updated_at = ?
                WHERE p.tenant_id = ?
                ",
                [$now, $tenantId],
            );
        } else {
            $products = $connection->table('products')
                ->where('tenant_id', $tenantId)
                ->orderBy('id')
                ->get(['id', 'ean']);

            $eanValues = $products
                ->pluck('ean')
                ->filter(fn (mixed $ean): bool => is_string($ean) && trim($ean) !== '')
                ->map(fn (string $ean): string => trim($ean))
                ->unique()
                ->values()
                ->all();

            if ($eanValues !== []) {
                $references = EanReference::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('ean', $eanValues)
                    ->get()
                    ->keyBy('ean');

                foreach ($products as $product) {
                    $ean = is_string($product->ean ?? null) ? trim($product->ean) : null;
                    if ($ean === null || $ean === '') {
                        continue;
                    }

                    $reference = $references->get($ean);
                    if (! $reference instanceof EanReference) {
                        continue;
                    }

                    $updates = [];
                    foreach ([
                        'category_id' => $reference->category_id,
                        'description' => $reference->reference_description,
                        'brand' => $reference->brand,
                        'subbrand' => $reference->subbrand,
                        'packaging_type' => $reference->packaging_type,
                        'packaging_size' => $reference->packaging_size,
                        'measurement_unit' => $reference->measurement_unit,
                    ] as $column => $value) {
                        if ($value !== null) {
                            $updates[$column] = $value;
                        }
                    }

                    if ($updates === []) {
                        continue;
                    }

                    $updates['updated_at'] = $now;

                    $connection->table('products')
                        ->where('id', (string) $product->id)
                        ->update($updates);
                }
            }
        }

        $this->syncSalesProductReferencesService->syncAllByCodigoErp(
            tenantConnectionName: $tenantConnectionName,
            tenantId: $tenantId,
            now: $now,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function requestProducts(TenantIntegration $integration, array $filters): array
    {
        $requestBody = [
            'pagina' => (int) ($filters['page'] ?? 1),
            'tamanho_pagina' => (int) ($filters['page_size'] ?? 1000),
            'partner_key' => (string) ($filters['partner_key'] ?? ''),
        ];

        if (is_string($filters['date'] ?? null) && $filters['date'] !== '') {
            $requestBody['data_ultima_alteracao'] = $filters['date'];
        }

        if (is_string($filters['empresa'] ?? null) && $filters['empresa'] !== '') {
            $requestBody['empresa'] = $filters['empresa'];
        }

        $response = $this->externalApiBaseService->request(
            integration: $integration,
            method: strtoupper((string) $integration->http_method),
            endpoint: $this->sysmoEndpoints->get('products'),
            body: $requestBody,
        );

        return is_array($response->json()) ? $response->json() : [];
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

    private function normalizeFloat(mixed $value): ?float
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

    private function normalizeEan(mixed $value): ?string
    {
        $ean = $this->normalizeString($value);

        if ($ean === null) {
            return null;
        }

        $normalized = EanReference::normalizeEan($ean);

        if ($normalized === '' || strlen($normalized) > 13) {
            return null;
        }

        return $normalized;
    }

    /**
     * Valida e limpa codigo_erp.
     * Retorna null se for inválido.
     */
    private function validateCodigoErp(?string $codigoErp): ?string
    {
        if ($codigoErp === null) {
            return null;
        }

        $codigoErp = trim((string) $codigoErp);

        $invalidValues = ['N/A', 'n/a', 'NA', 'na', 'NULL', 'null', 'NONE', 'none', '-', ''];

        if (in_array($codigoErp, $invalidValues, true)) {
            return null;
        }

        return $codigoErp;
    }

    private function generateProductId(?string $ean, string $tenantId, ?string $codigoErp): string
    {
        return $this->deterministicIdGenerator->productId($tenantId, $ean, $codigoErp);
    }

    /**
     * Validação consolidada dos dados de importação.
     *
     * @param  array<string, mixed>  $item
     */
    private function validateImportData(array $item): bool
    {
        return $this->getImportValidationFailureReason($item) === null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function getImportValidationFailureReason(array $item): ?string
    {
        /** @var array<string, mixed> $rawItem */
        $rawItem = is_array($item['raw'] ?? null) ? $item['raw'] : [];

        $ean = $this->getProcessedGtin($rawItem, $item);
        if ($ean === null) {
            return 'GTIN/EAN ausente ou inválido';
        }

        $requiredFlags = ['cadastro_ativo', 'ativo_na_empresa', 'pertence_ao_mix'];

        foreach ($requiredFlags as $flag) {
            $value = $this->getProcessedValue($flag, $rawItem, $item);
            if ($value === 'N') {
                return sprintf('Flag %s marcada como N', $flag);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rawItem
     * @param  array<string, mixed>  $mappedItem
     */
    private function getProcessedValue(string $key, array $rawItem, array $mappedItem, ?string $default = null): ?string
    {
        $value = $rawItem[$key] ?? $mappedItem[$key] ?? $default;

        return $this->normalizeString($value);
    }

    /**
     * @param  array<string, mixed>  $rawItem
     * @param  array<string, mixed>  $mappedItem
     */
    private function getProcessedGtin(array $rawItem, array $mappedItem): ?string
    {
        if (array_key_exists('gtins', $rawItem)) {
            if (empty($rawItem['gtins'])) {
                return null;
            }

            $fromRaw = $this->extractGtinFromRaw($rawItem['gtins']);

            return $this->normalizeEan($fromRaw);
        }

        return $this->normalizeEan($mappedItem['ean'] ?? null);
    }

    private function extractGtinFromRaw(mixed $gtins): ?string
    {
        if (! is_array($gtins)) {
            return null;
        }

        $complete = $gtins['completo'] ?? null;
        if (is_array($complete)) {
            foreach ($complete as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                if (($entry['principal'] ?? null) === 'S') {
                    return $this->normalizeString($entry['gtin'] ?? null);
                }
            }

            foreach ($complete as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $candidate = $this->normalizeString($entry['gtin'] ?? null);
                if ($candidate !== null) {
                    return $candidate;
                }
            }
        }

        return $this->normalizeString($gtins['gtin'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function logTrackedRejectedEan(
        string $tenantId,
        ?string $storeId,
        array $item,
        string $reason,
        string $stage,
    ): void {
        $rawItem = is_array($item['raw'] ?? null) ? $item['raw'] : [];

        $processedEan = $this->getProcessedGtin($rawItem, $item);
        $fallbackEan = $this->normalizeEan($item['ean'] ?? null);
        $eanForTracking = $processedEan ?? $fallbackEan;

        if ($eanForTracking === null || ! in_array($eanForTracking, self::DEBUG_REJECTED_EANS, true)) {
            return;
        }

        Log::warning('Produto monitorado recusado na busca geral da API.', [
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'stage' => $stage,
            'ean' => $eanForTracking,
            'codigo_erp' => $this->normalizeString($item['external_id'] ?? null),
            'reason' => $reason,
            'flags' => [
                'cadastro_ativo' => $this->getProcessedValue('cadastro_ativo', $rawItem, $item),
                'ativo_na_empresa' => $this->getProcessedValue('ativo_na_empresa', $rawItem, $item),
                'pertence_ao_mix' => $this->getProcessedValue('pertence_ao_mix', $rawItem, $item),
            ],
            'raw_gtins' => $rawItem['gtins'] ?? null,
        ]);
    }
}
