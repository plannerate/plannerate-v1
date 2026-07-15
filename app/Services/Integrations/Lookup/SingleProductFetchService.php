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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Busca sob demanda (síncrona) das vendas de UM produto em UMA loja — e,
 * opcionalmente, dos dados cadastrais do produto — na API do tenant.
 *
 * Roda dentro de um Job em fila (SyncSingleProductJob). Reaproveita as mesmas
 * peças do motor genérico (IntegrationHttpClient, RecordMapper,
 * TenantRecordPersister), mas lê a config de um bloco isolado
 * `requests.lookups.{product,sales}` que o motor em massa ignora (ele só lê
 * `requests.paths`). Assim nada do fluxo existente é afetado.
 *
 * Por decisão de produto: a loja é obrigatória (uma por chamada) e a
 * atualização dos dados do produto é opt-in — por padrão só busca vendas.
 */
class SingleProductFetchService
{
    public function __construct(
        private readonly RecordMapper $mapper = new RecordMapper(new FieldValueResolver),
        private readonly DeterministicIdGenerator $idGenerator = new DeterministicIdGenerator,
        private readonly LookupPayloadBuilder $payloadBuilder = new LookupPayloadBuilder,
    ) {}

    public function fetch(
        TenantIntegration $integration,
        Product $product,
        Store $store,
        bool $updateProduct = false,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): SingleProductFetchResult {
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

        if ($updateProduct) {
            $productLookup = data_get($lookups, 'product');

            if (is_array($productLookup) && $productLookup !== []) {
                $this->fetchProduct($integration, $config, $requests, $productLookup, $product, $store, $result);
            }
        }

        $salesLookup = data_get($lookups, 'sales');

        if (is_array($salesLookup) && $salesLookup !== []) {
            $this->fetchSales($integration, $config, $requests, $salesLookup, $product, $store, $result, $dateFrom, $dateTo);
        }

        return $result;
    }

    // ─── Produto (opt-in) ────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $requests
     * @param  array<string, mixed>  $lookup
     */
    private function fetchProduct(
        TenantIntegration $integration,
        array $config,
        array $requests,
        array $lookup,
        Product $product,
        Store $store,
        SingleProductFetchResult $result,
    ): void {
        try {
            $code = $this->productCode($lookup, $product);

            if ($code === '') {
                $result->addError('Produto: sem '.(string) data_get($lookup, 'lookup_key', 'ean'));

                return;
            }

            $records = $this->request($config, $requests, $lookup, $code, $this->storeValue($lookup, $store), null, null, $integration, $store->getKey());

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
     */
    private function fetchSales(
        TenantIntegration $integration,
        array $config,
        array $requests,
        array $lookup,
        Product $product,
        Store $store,
        SingleProductFetchResult $result,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): void {
        try {
            $code = $this->productCode($lookup, $product);

            if ($code === '') {
                $result->addError('Vendas: sem '.(string) data_get($lookup, 'lookup_key', 'ean'));

                return;
            }

            $storeValue = $this->storeValue($lookup, $store);

            if ((string) data_get($lookup, 'store_field', '') !== '' && ($storeValue === null || $storeValue === '')) {
                $result->addError('Vendas: loja sem '.(string) data_get($lookup, 'store_key', 'document'));

                return;
            }

            $result->storesQueried++;

            [$dateStart, $dateEnd] = $this->salesDateRange($lookup, $dateFrom, $dateTo);

            $records = $this->request($config, $requests, $lookup, $code, $storeValue, $dateStart, $dateEnd, $integration, $store->getKey());

            if ($records === []) {
                return;
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
                'store_id' => (string) $store->getKey(),
                'error' => $e->getMessage(),
            ]);
            $result->addError('Vendas: '.$e->getMessage());
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
            throw new \RuntimeException(sprintf(
                'HTTP %d em %s: %s',
                $response->status(),
                $url,
                Str::limit((string) $response->body(), 300),
            ));
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

    /**
     * Valor da loja a enviar no request.
     *
     * Normaliza para só dígitos quando `store_transform: 'digits'` OU quando o
     * campo é `document` (CNPJ/CPF é sempre numérico nestes ERPs — a API da Sysmo
     * rejeita empresa formatada, e o import em massa também envia só dígitos).
     * Assim funciona mesmo sem o flag configurado no blueprint. Para forçar o
     * envio literal de um `document`, defina `store_transform: 'raw'`.
     *
     * @param  array<string, mixed>  $lookup
     */
    private function storeValue(array $lookup, Store $store): ?string
    {
        $key = (string) data_get($lookup, 'store_key', 'document');
        $value = trim((string) ($store->{$key} ?? ''));

        $transform = (string) data_get($lookup, 'store_transform', '');
        $shouldStripToDigits = $transform === 'digits' || ($transform === '' && $key === 'document');

        if ($value !== '' && $shouldStripToDigits) {
            $value = preg_replace('/\D/', '', $value) ?? '';
        }

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
     * Janela de datas para vendas.
     *
     * Usa as datas selecionadas no formulário quando informadas (mesmo que só uma
     * delas); caso contrário, cai no padrão [hoje - initial_days, hoje].
     *
     * @param  array<string, mixed>  $lookup
     * @return array{0: string, 1: string}
     */
    private function salesDateRange(array $lookup, ?string $from, ?string $to): array
    {
        $format = (string) data_get($lookup, 'date_format', 'Y-m-d');
        $initialDays = (int) (data_get($lookup, 'initial_days') ?? 200);

        $hasFrom = $from !== null && trim($from) !== '';
        $hasTo = $to !== null && trim($to) !== '';

        if ($hasFrom || $hasTo) {
            $end = $hasTo ? Carbon::parse($to) : Carbon::now();
            $start = $hasFrom ? Carbon::parse($from) : $end->copy()->subDays($initialDays);

            return [$start->format($format), $end->format($format)];
        }

        $end = Carbon::now();
        $start = $initialDays > 0 ? $end->copy()->subDays($initialDays) : $end->copy();

        return [$start->format($format), $end->format($format)];
    }
}
