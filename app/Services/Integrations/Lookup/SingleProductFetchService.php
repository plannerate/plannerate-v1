<?php

namespace App\Services\Integrations\Lookup;

use App\Models\Product;
use App\Models\Store;
use App\Models\TenantIntegration;
use App\Services\Integrations\FieldValueResolver;
use App\Services\Integrations\IntegrationHttpClient;
use App\Services\Integrations\RecordMapper;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use App\Services\Integrations\TenantRecordPersister;
use App\Services\Integrations\TenantUpsertRecordPreparer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Busca sob demanda (síncrona) dos dados de UM produto e suas vendas na API do tenant.
 *
 * Ao contrário do import em massa (integration:run / jobs em fila), roda no
 * ciclo da requisição para um único produto — poucas chamadas HTTP. Reaproveita
 * as mesmas peças do motor genérico (IntegrationHttpClient, RecordMapper,
 * TenantRecordPersister), mas lê a config de um bloco isolado
 * `requests.lookups.{product,sales}` que o motor em massa ignora (ele só lê
 * `requests.paths`). Assim nada do fluxo existente é afetado.
 */
class SingleProductFetchService
{
    public function __construct(
        private readonly RecordMapper $mapper = new RecordMapper(new FieldValueResolver),
        private readonly DeterministicIdGenerator $idGenerator = new DeterministicIdGenerator,
        private readonly LookupPayloadBuilder $payloadBuilder = new LookupPayloadBuilder,
    ) {}

    public function fetch(TenantIntegration $integration, Product $product): SingleProductFetchResult
    {
        $api = $integration->api;

        if ($api === null) {
            return SingleProductFetchResult::notConfigured();
        }

        $requests = $api->requests ?? [];
        $lookups = data_get($requests, 'lookups', []);

        if (! is_array($lookups) || $lookups === []) {
            return SingleProductFetchResult::notConfigured();
        }

        $config = $integration->config ?? [];
        $result = new SingleProductFetchResult;

        /** @var Collection<int, Store> $stores */
        $stores = $product->stores;

        $productLookup = data_get($lookups, 'product');

        if (is_array($productLookup) && $productLookup !== []) {
            $this->fetchProduct($integration, $config, $requests, $productLookup, $product, $stores, $result);
        }

        $salesLookup = data_get($lookups, 'sales');

        if (is_array($salesLookup) && $salesLookup !== []) {
            $this->fetchSales($integration, $config, $requests, $salesLookup, $product, $stores, $result);
        }

        return $result;
    }

    // ─── Produto ─────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $requests
     * @param  array<string, mixed>  $lookup
     * @param  Collection<int, Store>  $stores
     */
    private function fetchProduct(
        TenantIntegration $integration,
        array $config,
        array $requests,
        array $lookup,
        Product $product,
        Collection $stores,
        SingleProductFetchResult $result,
    ): void {
        try {
            $code = $this->productCode($lookup, $product);

            if ($code === '') {
                $result->addError('Produto: sem '.(string) data_get($lookup, 'lookup_key', 'ean'));

                return;
            }

            $store = $stores->first();
            $storeValue = $this->storeValue($lookup, $store);

            $records = $this->request($config, $requests, $lookup, $code, $storeValue, null, null, $integration, $store?->getKey());

            if ($records === []) {
                return;
            }

            TenantRecordPersister::persist(
                $integration,
                (string) data_get($lookup, 'target_table', 'products'),
                $records,
                (array) data_get($lookup, 'pivot_tables', []),
            );

            $result->productsPersisted += count($records);
        } catch (Throwable $e) {
            Log::warning('SingleProductFetchService: falha ao buscar produto', [
                'integration_id' => (string) $integration->id,
                'product_id' => (string) $product->getKey(),
                'error' => $e->getMessage(),
            ]);
            $result->addError('Produto: '.$e->getMessage());
        }
    }

    // ─── Vendas ──────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $requests
     * @param  array<string, mixed>  $lookup
     * @param  Collection<int, Store>  $stores
     */
    private function fetchSales(
        TenantIntegration $integration,
        array $config,
        array $requests,
        array $lookup,
        Product $product,
        Collection $stores,
        SingleProductFetchResult $result,
    ): void {
        $code = $this->productCode($lookup, $product);

        if ($code === '') {
            $result->addError('Vendas: sem '.(string) data_get($lookup, 'lookup_key', 'ean'));

            return;
        }

        [$dateStart, $dateEnd] = $this->salesDateWindow($lookup);

        $storeField = (string) data_get($lookup, 'store_field', '');
        $needsStore = $storeField !== '';

        /** @var array<int, Store|null> $targets */
        $targets = $needsStore && $stores->isNotEmpty() ? $stores->all() : [null];

        foreach ($targets as $store) {
            try {
                $storeValue = $this->storeValue($lookup, $store);

                if ($needsStore && ($storeValue === null || $storeValue === '')) {
                    $result->addError('Vendas: loja sem '.(string) data_get($lookup, 'store_key', 'document'));

                    continue;
                }

                $result->storesQueried++;

                $records = $this->request($config, $requests, $lookup, $code, $storeValue, $dateStart, $dateEnd, $integration, $store?->getKey());

                if ($records === []) {
                    continue;
                }

                TenantRecordPersister::persist(
                    $integration,
                    (string) data_get($lookup, 'target_table', 'sales'),
                    $records,
                    (array) data_get($lookup, 'pivot_tables', []),
                );

                $result->salesPersisted += count($records);
            } catch (Throwable $e) {
                Log::warning('SingleProductFetchService: falha ao buscar vendas', [
                    'integration_id' => (string) $integration->id,
                    'product_id' => (string) $product->getKey(),
                    'store_id' => $store?->getKey(),
                    'error' => $e->getMessage(),
                ]);
                $result->addError('Vendas: '.$e->getMessage());
            }
        }
    }

    // ─── Chamada + mapeamento ────────────────────────────────────────────────

    /**
     * Executa uma chamada de lookup e retorna os registros mapeados/prontos para upsert.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $requests
     * @param  array<string, mixed>  $lookup
     * @return array<int, array<string, mixed>>
     */
    private function request(
        array $config,
        array $requests,
        array $lookup,
        string $code,
        ?string $storeValue,
        ?string $dateStart,
        ?string $dateEnd,
        TenantIntegration $integration,
        ?string $storeId,
    ): array {
        $payload = $this->payloadBuilder->build($config, $requests, $lookup, $code, $storeValue, $dateStart, $dateEnd);
        $url = $this->buildUrl($config, $lookup);
        $method = $this->payloadBuilder->method($requests, $lookup);

        $response = (new IntegrationHttpClient($config))->call($method, $url, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(sprintf('HTTP %d em %s', $response->status(), $url));
        }

        $items = $this->extractItems($response->json(), $lookup);

        return $this->mapRecords(
            $items,
            $lookup,
            (string) $integration->tenant_id,
            (string) $integration->id,
            $storeId,
        );
    }

    /**
     * @param  array<string, mixed>  $lookup
     * @return array<int, array<string, mixed>>
     */
    private function extractItems(mixed $data, array $lookup): array
    {
        if (! is_array($data)) {
            return [];
        }

        $itemsPath = (string) data_get($lookup, 'response.items_path', '');
        $raw = $itemsPath !== '' ? data_get($data, $itemsPath) : $data;

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        // single_item: a resposta (no items_path) é UM registro associativo (ex.: consultar_produto).
        if ((bool) data_get($lookup, 'single_item', false)) {
            return [$raw];
        }

        if (array_keys($raw) !== range(0, count($raw) - 1)) {
            $raw = array_values($raw);
        }

        return array_values(array_filter($raw, static fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $lookup
     * @return array<int, array<string, mixed>>
     */
    private function mapRecords(array $items, array $lookup, string $tenantId, string $integrationId, ?string $storeId): array
    {
        $fieldMap = (array) data_get($lookup, 'field_map', []);
        $validations = (array) data_get($lookup, 'validations', []);
        $now = Carbon::now()->toDateTimeString();

        $mapped = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            [$record] = $this->mapper->mapWithRejectionReason($item, $fieldMap, $storeId, $validations);

            if ($record === null) {
                continue;
            }

            $record['id'] = $this->idGenerator->fromRecord($tenantId, $integrationId, $record, $lookup, $storeId);
            $record['tenant_id'] = $tenantId;
            $record['created_at'] = $now;
            $record['updated_at'] = $now;

            $mapped[] = $record;
        }

        return array_values(TenantUpsertRecordPreparer::deduplicateById($mapped));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $lookup */
    private function productCode(array $lookup, Product $product): string
    {
        $key = (string) data_get($lookup, 'lookup_key', 'ean');

        return trim((string) ($product->{$key} ?? ''));
    }

    /** @param array<string, mixed> $lookup */
    private function storeValue(array $lookup, ?Store $store): ?string
    {
        if ($store === null) {
            return null;
        }

        $key = (string) data_get($lookup, 'store_key', 'document');
        $value = trim((string) ($store->{$key} ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $lookup
     */
    private function buildUrl(array $config, array $lookup): string
    {
        $baseUrl = (string) data_get($config, 'connection.base_url', '');
        $fallbackPath = (string) data_get($lookup, 'fallback_path', '');

        return rtrim($baseUrl, '/').$fallbackPath;
    }

    /**
     * Janela de datas para vendas: [hoje - initial_days, hoje].
     *
     * @param  array<string, mixed>  $lookup
     * @return array{0: string, 1: string}
     */
    private function salesDateWindow(array $lookup): array
    {
        $initialDays = (int) (data_get($lookup, 'initial_days') ?? 200);
        $format = (string) data_get($lookup, 'date_format', 'Y-m-d');

        $end = Carbon::now();
        $start = $initialDays > 0 ? $end->copy()->subDays($initialDays) : $end->copy();

        return [$start->format($format), $end->format($format)];
    }
}
