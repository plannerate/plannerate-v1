<?php

namespace App\Services\Integrations\Support;

use App\Models\EanReference;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PersistImportedProductsService
{
    public function __construct(
        private readonly DeterministicIdGenerator $deterministicIdGenerator,
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
            Log::warning('Persistência de produtos ignorada: tenant não encontrado.', [
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

        $productsRows = [];
        $invalidCount = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $mapped = $this->mapItem($provider, $item);
            if (! $this->passesProviderValidation($provider, $mapped, $item)) {
                $invalidCount++;

                continue;
            }

            $ean = $this->normalizeEan($mapped['ean'] ?? null);
            $codigoErp = $this->normalizeCodigoErp($mapped['codigo_erp'] ?? null);

            if ($ean === null || $codigoErp === null) {
                $invalidCount++;

                continue;
            }

            $productId = $this->deterministicIdGenerator->productId($tenantId, $ean, $codigoErp);

            $productsRows[] = [
                'id' => $productId,
                'tenant_id' => $tenantId,
                'name' => $this->normalizeString($mapped['name'] ?? null),
                'ean' => $ean,
                'codigo_erp' => $codigoErp,
                'brand' => $this->normalizeString($mapped['brand'] ?? null),
                'subbrand' => $this->normalizeString($mapped['subbrand'] ?? null),
                'description' => $this->normalizeString($mapped['description'] ?? null),
                'auxiliary_description' => $this->normalizeString($mapped['auxiliary_description'] ?? null),
                'additional_information' => $this->normalizeString($mapped['additional_information'] ?? null),
                'reference' => $this->normalizeString($mapped['reference'] ?? null),
                'color' => $this->normalizeString($mapped['color'] ?? null),
                'fragrance' => $this->normalizeString($mapped['fragrance'] ?? null),
                'flavor' => $this->normalizeString($mapped['flavor'] ?? null),
                'packaging_type' => $this->normalizeString($mapped['packaging_type'] ?? null),
                'packaging_size' => $this->normalizeString($mapped['packaging_size'] ?? null),
                'measurement_unit' => $this->normalizeString($mapped['measurement_unit'] ?? null),
                'unit_measure' => $this->normalizeString($mapped['unit_measure'] ?? null),
                'sortiment_attribute' => $this->normalizeString($mapped['sortiment_attribute'] ?? null),
                'current_stock' => $this->normalizeFloat($mapped['current_stock'] ?? null),
                'last_purchase_date' => $this->normalizeDate($mapped['last_purchase_date'] ?? null),
                'sales_status' => $this->normalizeString($mapped['sales_status'] ?? null),
                'status' => 'synced',
                'sync_source' => (string) ($integration->integration_type ?: $provider),
                'sync_at' => $now,
                'deleted_at' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ];
        }

        if ($productsRows === []) {
            Log::warning('Persistência de produtos não encontrou itens válidos.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'items_count' => count($items),
                'invalid_count' => $invalidCount,
            ]);

            return;
        }

        $productsRows = array_values(collect($productsRows)->keyBy('id')->all());

        DB::connection($connectionName)->table('products')->upsert(
            $productsRows,
            ['id'],
            [
                'tenant_id',
                'name',
                'ean',
                'codigo_erp',
                'brand',
                'subbrand',
                'description',
                'auxiliary_description',
                'additional_information',
                'reference',
                'color',
                'fragrance',
                'flavor',
                'packaging_type',
                'packaging_size',
                'measurement_unit',
                'unit_measure',
                'sortiment_attribute',
                'current_stock',
                'last_purchase_date',
                'sales_status',
                'status',
                'sync_source',
                'sync_at',
                'deleted_at',
                'updated_at',
            ],
        );

        $storeIds = $this->resolveStoreIds($tenantId, $store);
        if ($storeIds !== []) {
            $this->upsertProductStore($connectionName, $tenantId, $storeIds, $productsRows, $now);
        }

        Log::info('Persistência de produtos concluída.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'items_count' => count($items),
            'invalid_count' => $invalidCount,
            'upserted_products' => count($productsRows),
            'linked_stores' => count($storeIds),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function resolveStoreIds(string $tenantId, ?Store $store): array
    {
        if ($store instanceof Store && (string) $store->id !== '') {
            return [(string) $store->id];
        }

        return Store::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $storeIds
     * @param  array<int, array<string, mixed>>  $productsRows
     */
    private function upsertProductStore(
        string $connectionName,
        string $tenantId,
        array $storeIds,
        array $productsRows,
        Carbon $now,
    ): void {
        $pivotRows = [];

        foreach ($productsRows as $productRow) {
            foreach ($storeIds as $storeId) {
                $pivotRows[] = [
                    'id' => (string) str()->ulid(),
                    'tenant_id' => $tenantId,
                    'product_id' => (string) $productRow['id'],
                    'store_id' => $storeId,
                    'last_synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::connection($connectionName)->table('product_store')->upsert(
            $pivotRows,
            ['tenant_id', 'product_id', 'store_id'],
            ['last_synced_at', 'updated_at'],
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapItem(string $provider, array $item): array
    {
        return match ($provider) {
            'sysmo' => $this->mapSysmoItem($item),
            'gescooper' => $this->mapGescooperItem($item),
            default => [
                'codigo_erp' => $item['codigo_erp'] ?? null,
                'ean' => $item['ean'] ?? null,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapSysmoItem(array $item): array
    {
        return [
            'codigo_erp' => $item['produto'] ?? $item['id'] ?? $item['codigo'] ?? null,
            'ean' => $this->sysmoPrimaryGtin($item) ?? $item['ean'] ?? null,
            'name' => $item['descricao'] ?? $item['nome'] ?? null,
            'brand' => is_array($item['marca'] ?? null) ? ($item['marca']['descricao'] ?? null) : ($item['marca'] ?? null),
            'description' => $item['descricao_comercial'] ?? $item['descricao'] ?? null,
            'unit_measure' => is_array($item['unidade_venda'] ?? null) ? ($item['unidade_venda']['codigo'] ?? null) : null,
            'measurement_unit' => is_array($item['unidade_venda'] ?? null) ? ($item['unidade_venda']['descricao'] ?? null) : null,
            'packaging_type' => null,
            'packaging_size' => null,
            'current_stock' => is_array($item['estoque'] ?? null) ? ($item['estoque']['disponivel'] ?? null) : null,
            'last_purchase_date' => $this->sysmoLastPurchaseDate($item),
            'sales_status' => $item['cadastro_ativo'] ?? $item['status'] ?? null,
            'reference' => null,
            'fragrance' => null,
            'flavor' => null,
            'color' => null,
            'subbrand' => null,
            'auxiliary_description' => null,
            'additional_information' => null,
            'sortiment_attribute' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapGescooperItem(array $item): array
    {
        return [
            'codigo_erp' => $item['id_produto'] ?? null,
            'ean' => $item['ean'] ?? null,
            'name' => $item['descricao_completa'] ?? null,
            'brand' => $item['marca'] ?? null,
            'subbrand' => $item['submarca'] ?? null,
            'description' => $item['descricao_completa'] ?? null,
            'auxiliary_description' => $item['descricao_auxiliar'] ?? null,
            'additional_information' => $item['informacao_adicional'] ?? null,
            'reference' => $item['referencia'] ?? null,
            'color' => $item['cor'] ?? null,
            'fragrance' => $item['fragrancia'] ?? null,
            'flavor' => $item['sabor'] ?? null,
            'packaging_type' => $item['tipo_embalagem'] ?? null,
            'packaging_size' => $item['tamanho_embalagem'] ?? null,
            'measurement_unit' => $item['unidade_medida'] ?? null,
            'unit_measure' => $item['unidade_medida'] ?? null,
            'sortiment_attribute' => $item['segmento_varejista'] ?? null,
            'current_stock' => $item['estoque_atual'] ?? null,
            'last_purchase_date' => $item['data_ultima_compra'] ?? null,
            'sales_status' => $item['status_produto'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $raw
     */
    private function passesProviderValidation(string $provider, array $mapped, array $raw): bool
    {
        return match ($provider) {
            'sysmo' => $this->passesSysmoValidation($mapped, $raw),
            'gescooper' => $this->passesGescooperValidation($mapped),
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $raw
     */
    private function passesSysmoValidation(array $mapped, array $raw): bool
    {
        $requiredFlags = ['cadastro_ativo', 'ativo_na_empresa', 'pertence_ao_mix'];
        foreach ($requiredFlags as $flag) {
            if (! array_key_exists($flag, $raw)) {
                continue;
            }

            $value = strtoupper((string) ($raw[$flag] ?? ''));
            if ($value === 'N') {
                return false;
            }
        }

        return $this->normalizeString($mapped['name'] ?? null) !== null;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function passesGescooperValidation(array $mapped): bool
    {
        return $this->normalizeString($mapped['name'] ?? null) !== null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function sysmoPrimaryGtin(array $item): ?string
    {
        $gtins = $item['gtins']['completo'] ?? null;
        if (! is_array($gtins)) {
            return null;
        }

        foreach ($gtins as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (($entry['principal'] ?? null) === 'S') {
                return is_string($entry['gtin'] ?? null) ? $entry['gtin'] : null;
            }
        }

        foreach ($gtins as $entry) {
            if (is_array($entry) && is_string($entry['gtin'] ?? null)) {
                return $entry['gtin'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function sysmoLastPurchaseDate(array $item): ?string
    {
        $fornecedores = $item['fornecedores'] ?? null;
        if (! is_array($fornecedores)) {
            return null;
        }

        $lastDate = null;
        foreach ($fornecedores as $supplier) {
            if (! is_array($supplier)) {
                continue;
            }

            $date = $this->normalizeDate($supplier['data_ultima_compra'] ?? null);
            if ($date !== null && ($lastDate === null || $date > $lastDate)) {
                $lastDate = $date;
            }
        }

        return $lastDate;
    }

    private function normalizeEan(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = EanReference::normalizeEan((string) $value);
        if ($normalized === '' || strlen($normalized) > 13) {
            return null;
        }

        return $normalized;
    }

    private function normalizeCodigoErp(mixed $value): ?string
    {
        $normalized = $this->normalizeString($value);
        if ($normalized === null) {
            return null;
        }

        $clean = preg_replace('/[^A-Za-z0-9]/', '', $normalized) ?? '';

        return $clean !== '' ? $clean : null;
    }

    private function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            $normalized = trim((string) $value);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
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
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
