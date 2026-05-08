# GesCooper Products Integration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar o serviço de integração GesCooper para produtos, análogo ao Sysmo, sincronizando na mesma tabela `products` do tenant.

**Architecture:** `GesCooperAuthService` obtém JWT dinâmico via `POST /v1/Token` com cache em memória. `GesCooperProductsIntegrationService` implementa `ProductsIntegrationService`, faz requests GET com o token e persiste via upsert idêntico ao Sysmo. `IntegrationServiceResolver` roteará `integration_type = 'gescooper'` para o novo serviço.

**Tech Stack:** Laravel 13, PHP 8.5, Laravel HTTP Client, `TenantIntegrationConfigNormalizer`, `DeterministicIdGenerator`, `EanReference`, `SyncSalesProductReferencesService`

---

## File Map

| Ação | Arquivo |
|---|---|
| Criar | `app/Services/Integrations/GesCooper/Concerns/NormalizesGesCooperValues.php` |
| Criar | `app/Services/Integrations/GesCooper/Concerns/ExtractsGesCooperPayloadItems.php` |
| Criar | `app/Services/Integrations/GesCooper/GesCooperEndpoints.php` |
| Criar | `app/Services/Integrations/GesCooper/GesCooperAuthService.php` |
| Criar | `app/Services/Integrations/GesCooper/GesCooperProductsResponseMapper.php` |
| Criar | `app/Services/Integrations/GesCooper/GesCooperProductsIntegrationService.php` |
| Modificar | `app/Services/Integrations/Support/IntegrationServiceResolver.php` |

---

## Task 1: Concerns — NormalizesGesCooperValues e ExtractsGesCooperPayloadItems

**Files:**
- Create: `app/Services/Integrations/GesCooper/Concerns/NormalizesGesCooperValues.php`
- Create: `app/Services/Integrations/GesCooper/Concerns/ExtractsGesCooperPayloadItems.php`

- [ ] **Step 1: Criar NormalizesGesCooperValues**

```php
<?php

namespace App\Services\Integrations\GesCooper\Concerns;

use Illuminate\Support\Carbon;

trait NormalizesGesCooperValues
{
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

    private function normalizeCodigoErp(?string $codigoErp): ?string
    {
        if ($codigoErp === null) {
            return null;
        }

        $codigoErp = trim($codigoErp);

        $invalidValues = ['N/A', 'n/a', 'NA', 'na', 'NULL', 'null', 'NONE', 'none', '-', ''];

        if (in_array($codigoErp, $invalidValues, true)) {
            return null;
        }

        return $codigoErp;
    }
}
```

- [ ] **Step 2: Criar ExtractsGesCooperPayloadItems**

A API GesCooper retorna `{ "data": [...], "pagination": {...}, "success": true }`.

```php
<?php

namespace App\Services\Integrations\GesCooper\Concerns;

trait ExtractsGesCooperPayloadItems
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractItemsFromPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if (is_array($payload['data'] ?? null)) {
            /** @var array<int, array<string, mixed>> $data */
            $data = array_values(array_filter($payload['data'], 'is_array'));

            return $data;
        }

        return [];
    }
}
```

- [ ] **Step 3: Formatar e commitar**

```bash
./vendor/bin/sail vendor/bin/pint --dirty
git add app/Services/Integrations/GesCooper/Concerns/
git commit -m "feat: add GesCooper normalization and payload extraction concerns"
```

---

## Task 2: GesCooperEndpoints

**Files:**
- Create: `app/Services/Integrations/GesCooper/GesCooperEndpoints.php`

- [ ] **Step 1: Criar GesCooperEndpoints**

```php
<?php

namespace App\Services\Integrations\GesCooper;

use RuntimeException;

class GesCooperEndpoints
{
    /**
     * @var array<string, string>
     */
    private const ENDPOINTS = [
        'token'    => 'v1/Token',
        'products' => 'Produtos/Produtos',
    ];

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return self::ENDPOINTS;
    }

    public function get(string $key): string
    {
        if (! array_key_exists($key, self::ENDPOINTS)) {
            throw new RuntimeException('Endpoint GesCooper nao mapeado: '.$key);
        }

        return self::ENDPOINTS[$key];
    }
}
```

- [ ] **Step 2: Formatar e commitar**

```bash
./vendor/bin/sail vendor/bin/pint --dirty
git add app/Services/Integrations/GesCooper/GesCooperEndpoints.php
git commit -m "feat: add GesCooper endpoints definition"
```

---

## Task 3: GesCooperAuthService

**Files:**
- Create: `app/Services/Integrations/GesCooper/GesCooperAuthService.php`

A GesCooper exige autenticação dinâmica: um `POST /v1/Token` retorna um Bearer JWT que expira (~72h). O token é cacheado em propriedade privada durante a execução do job para evitar chamadas repetidas na paginação.

- [ ] **Step 1: Criar GesCooperAuthService**

```php
<?php

namespace App\Services\Integrations\GesCooper;

use App\Models\TenantIntegration;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GesCooperAuthService
{
    private ?string $cachedToken = null;

    public function __construct(
        private readonly TenantIntegrationConfigNormalizer $configNormalizer,
        private readonly GesCooperEndpoints $endpoints,
    ) {}

    public function getToken(TenantIntegration $integration): string
    {
        if ($this->cachedToken !== null) {
            return $this->cachedToken;
        }

        $normalized = $this->configNormalizer->normalize($integration);
        $baseUrl = rtrim($normalized['connection']['base_url'], '/');
        $credentials = $normalized['auth']['credentials'];

        $response = Http::acceptJson()
            ->timeout($normalized['connection']['timeout'])
            ->connectTimeout($normalized['connection']['connect_timeout'])
            ->withOptions(['verify' => $normalized['connection']['verify_ssl']])
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($baseUrl.'/'.$this->endpoints->get('token'), [
                'usuario'       => (string) ($credentials['usuario'] ?? ''),
                'senha'         => (string) ($credentials['senha'] ?? ''),
                'dispositivoUID' => (string) ($credentials['dispositivo_uid'] ?? ''),
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'GesCooper: falha na autenticacao. HTTP %s: %s',
                $response->status(),
                mb_substr($response->body(), 0, 500),
            ));
        }

        $json = $response->json();
        $token = is_array($json) ? ($json['token'] ?? $json['access_token'] ?? null) : null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('GesCooper: token nao encontrado na resposta de autenticacao. Campos disponiveis: '.implode(', ', is_array($json) ? array_keys($json) : []));
        }

        $this->cachedToken = $token;

        return $this->cachedToken;
    }
}
```

> **Nota:** O campo do token na resposta (`token` vs `access_token`) é verificado com fallback. Se a API retornar outro campo, adicionar o fallback no array de lookup.

- [ ] **Step 2: Formatar e commitar**

```bash
./vendor/bin/sail vendor/bin/pint --dirty
git add app/Services/Integrations/GesCooper/GesCooperAuthService.php
git commit -m "feat: add GesCooper dynamic auth service with in-memory token cache"
```

---

## Task 4: GesCooperProductsResponseMapper

**Files:**
- Create: `app/Services/Integrations/GesCooper/GesCooperProductsResponseMapper.php`

Mapeia os campos da resposta `GET /Produtos/Produtos` para o formato interno usado por `persistMappedProducts`.

- [ ] **Step 1: Criar GesCooperProductsResponseMapper**

```php
<?php

namespace App\Services\Integrations\GesCooper;

use App\Services\Integrations\GesCooper\Concerns\NormalizesGesCooperValues;
use App\Services\Integrations\Mappers\ProductsResponseMapper;

class GesCooperProductsResponseMapper implements ProductsResponseMapper
{
    use NormalizesGesCooperValues;

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function mapMany(array $items): array
    {
        return array_map(
            fn (array $item): array => $this->mapItem($item),
            $items,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapItem(array $item): array
    {
        return [
            'external_id'            => $this->normalizeCodigoErp($this->normalizeString($item['id_produto'] ?? null)),
            'ean'                    => $this->normalizeString($item['ean'] ?? null),
            'name'                   => $this->normalizeString($item['descricao_completa'] ?? null),
            'brand'                  => $this->normalizeString($item['marca'] ?? null),
            'subbrand'               => $this->normalizeString($item['submarca'] ?? null),
            'status'                 => $this->normalizeString($item['status_produto'] ?? null),
            'last_purchase_date'     => $this->normalizeDate($item['data_ultima_compra'] ?? null),
            'height'                 => $this->normalizeFloat($item['altura'] ?? null),
            'width'                  => $this->normalizeFloat($item['largura'] ?? null),
            'depth'                  => $this->normalizeFloat($item['profundidade'] ?? null),
            'packaging_type'         => $this->normalizeString($item['tipo_embalagem'] ?? null),
            'packaging_size'         => $this->normalizeString($item['tamanho_embalagem'] ?? null),
            'unit'                   => $this->normalizeString($item['unidade_medida'] ?? null),
            'fragrance'              => $this->normalizeString($item['fragrancia'] ?? null),
            'flavor'                 => $this->normalizeString($item['sabor'] ?? null),
            'color'                  => $this->normalizeString($item['cor'] ?? null),
            'reference'              => $this->normalizeString($item['referencia'] ?? null),
            'auxiliary_description'  => $this->normalizeString($item['descricao_auxiliar'] ?? null),
            'additional_information' => $this->normalizeString($item['informacao_adicional'] ?? null),
            'current_stock'          => $this->normalizeFloat($item['estoque_atual'] ?? null),
            'sortiment_attribute'    => $this->normalizeString($item['segmento_varejista'] ?? null),
            'raw'                    => $item,
        ];
    }
}
```

- [ ] **Step 2: Formatar e commitar**

```bash
./vendor/bin/sail vendor/bin/pint --dirty
git add app/Services/Integrations/GesCooper/GesCooperProductsResponseMapper.php
git commit -m "feat: add GesCooper products response mapper"
```

---

## Task 5: GesCooperProductsIntegrationService

**Files:**
- Create: `app/Services/Integrations/GesCooper/GesCooperProductsIntegrationService.php`

Implementa `ProductsIntegrationService`. Obtém o token via `GesCooperAuthService`, faz `GET /Produtos/Produtos` com paginação por query params, e persiste via upsert idêntico ao Sysmo.

- [ ] **Step 1: Criar GesCooperProductsIntegrationService**

```php
<?php

namespace App\Services\Integrations\GesCooper;

use App\Models\EanReference;
use App\Models\TenantIntegration;
use App\Services\Integrations\Contracts\ProductsIntegrationService;
use App\Services\Integrations\GesCooper\Concerns\ExtractsGesCooperPayloadItems;
use App\Services\Integrations\GesCooper\Concerns\NormalizesGesCooperValues;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use App\Services\Integrations\Support\SyncSalesProductReferencesService;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GesCooperProductsIntegrationService implements ProductsIntegrationService
{
    use ExtractsGesCooperPayloadItems;
    use NormalizesGesCooperValues;

    public function __construct(
        private readonly GesCooperAuthService $authService,
        private readonly GesCooperEndpoints $endpoints,
        private readonly GesCooperProductsResponseMapper $responseMapper,
        private readonly DeterministicIdGenerator $deterministicIdGenerator,
        private readonly SyncSalesProductReferencesService $syncSalesProductReferencesService,
        private readonly TenantIntegrationConfigNormalizer $configNormalizer,
    ) {}

    public function fetchProducts(TenantIntegration $integration, array $filters = []): array
    {
        $payload = $this->requestProducts($integration, $filters);
        $mappedItems = $this->responseMapper->mapMany($this->extractItemsFromPayload($payload));

        $this->persistMappedProducts(
            tenantId: (string) $integration->tenant_id,
            source: (string) ($integration->integration_type ?: 'gescooper'),
            mappedItems: $mappedItems,
            storeId: is_string($filters['store_id'] ?? null) ? $filters['store_id'] : null,
        );

        return $mappedItems;
    }

    public function discoverProductsTotalPages(TenantIntegration $integration, array $filters = []): int
    {
        $payload = $this->requestProducts($integration, array_merge($filters, ['page' => 1]));
        $lastPage = $payload['pagination']['last_page'] ?? 1;

        return max(1, (int) $lastPage);
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
            $ean = $this->normalizeAndValidateEan($item['ean'] ?? null);
            if ($ean !== null) {
                $eanValues[] = $ean;
            }
        }

        $references = EanReference::query()
            ->whereIn('ean', array_values(array_unique($eanValues)))
            ->get()
            ->keyBy('ean');

        $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
        $now = Carbon::now();
        $productsRows = [];
        $invalidItemsCount = 0;
        $invalidItemsExamples = [];

        foreach ($mappedItems as $item) {
            $normalizedEan = $this->normalizeAndValidateEan($item['ean'] ?? null);
            $externalId = $this->normalizeCodigoErp($this->normalizeString($item['external_id'] ?? null));

            if ($normalizedEan === null || $externalId === null) {
                $invalidItemsCount++;
                if (count($invalidItemsExamples) < 5) {
                    $invalidItemsExamples[] = [
                        'codigo_erp' => $item['external_id'] ?? null,
                        'ean'        => $item['ean'] ?? null,
                    ];
                }

                continue;
            }

            $reference = $references->get($normalizedEan);

            $productId = $this->deterministicIdGenerator->productId($tenantId, $normalizedEan, $externalId);

            $productsRows[] = [
                'id'                     => $productId,
                'tenant_id'              => $tenantId,
                'name'                   => $this->normalizeString($item['name'] ?? null) ?? $reference?->reference_description,
                'ean'                    => $normalizedEan,
                'codigo_erp'             => $externalId,
                'brand'                  => $this->normalizeString($item['brand'] ?? null) ?? $reference?->brand,
                'subbrand'               => $this->normalizeString($item['subbrand'] ?? null) ?? $reference?->subbrand,
                'description'            => $reference?->reference_description,
                'auxiliary_description'  => $this->normalizeString($item['auxiliary_description'] ?? null),
                'additional_information' => $this->normalizeString($item['additional_information'] ?? null),
                'reference'              => $this->normalizeString($item['reference'] ?? null),
                'color'                  => $this->normalizeString($item['color'] ?? null),
                'fragrance'              => $this->normalizeString($item['fragrance'] ?? null),
                'flavor'                 => $this->normalizeString($item['flavor'] ?? null),
                'height'                 => $this->normalizeFloat($item['height'] ?? null),
                'width'                  => $this->normalizeFloat($item['width'] ?? null),
                'depth'                  => $this->normalizeFloat($item['depth'] ?? null),
                'packaging_type'         => $this->normalizeString($item['packaging_type'] ?? null) ?? $reference?->packaging_type,
                'packaging_size'         => $this->normalizeString($item['packaging_size'] ?? null) ?? $reference?->packaging_size,
                'measurement_unit'       => $this->normalizeString($item['unit'] ?? null) ?? $reference?->measurement_unit,
                'sortiment_attribute'    => $this->normalizeString($item['sortiment_attribute'] ?? null),
                'current_stock'          => $this->normalizeFloat($item['current_stock'] ?? null),
                'last_purchase_date'     => $this->normalizeDate($item['last_purchase_date'] ?? null),
                'sales_status'           => $this->normalizeString($item['status'] ?? null),
                'status'                 => 'synced',
                'sync_source'            => $source,
                'sync_at'                => $now,
                'deleted_at'             => null,
                'updated_at'             => $now,
                'created_at'             => $now,
            ];
        }

        if ($invalidItemsCount > 0) {
            Log::warning('GesCooper: sincronizacao de produtos ignorou itens sem EAN ou codigo_erp valido.', [
                'tenant_id'             => $tenantId,
                'store_id'              => $storeId,
                'invalid_items_count'   => $invalidItemsCount,
                'mapped_items_count'    => count($mappedItems),
                'invalid_items_examples' => $invalidItemsExamples,
            ]);
        }

        if ($productsRows === []) {
            Log::warning('GesCooper: sincronizacao de produtos nao persistiu registros.', [
                'tenant_id'   => $tenantId,
                'store_id'    => $storeId,
                'items_count' => count($mappedItems),
            ]);

            return;
        }

        DB::connection($tenantConnectionName)->table('products')->upsert(
            $productsRows,
            ['id'],
            [
                'tenant_id', 'name', 'ean', 'codigo_erp', 'brand', 'subbrand',
                'description', 'auxiliary_description', 'additional_information',
                'reference', 'color', 'fragrance', 'flavor',
                'height', 'width', 'depth',
                'packaging_type', 'packaging_size', 'measurement_unit',
                'sortiment_attribute', 'current_stock', 'last_purchase_date',
                'sales_status', 'status', 'sync_source', 'sync_at', 'deleted_at', 'updated_at',
            ]
        );

        if ($storeId !== null && $storeId !== '') {
            $pivotRows = [];

            foreach ($productsRows as $productRow) {
                $pivotRows[] = [
                    'id'           => (string) str()->ulid(),
                    'tenant_id'    => $tenantId,
                    'product_id'   => $productRow['id'],
                    'store_id'     => $storeId,
                    'last_synced_at' => $now,
                    'created_at'   => $now,
                    'updated_at'   => $now,
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
                    'description'    => $reference->reference_description,
                    'brand'          => $reference->brand,
                    'subbrand'       => $reference->subbrand,
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
        $normalized = $this->configNormalizer->normalize($integration);
        $connection = $normalized['connection'];
        $processing = $normalized['processing'];
        $baseUrl = rtrim($connection['base_url'], '/');

        $token = $this->authService->getToken($integration);

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout($connection['timeout'])
            ->connectTimeout($connection['connect_timeout'])
            ->withOptions(['verify' => $connection['verify_ssl']])
            ->get($baseUrl.'/'.$this->endpoints->get('products'), [
                'pagina'               => (int) ($filters['page'] ?? 1),
                'registros_por_pagina' => (int) ($filters['page_size'] ?? $processing['products_page_size']),
                'api-version'          => '1.0',
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'GesCooper: falha ao buscar produtos. HTTP %s: %s',
                $response->status(),
                mb_substr($response->body(), 0, 500),
            ));
        }

        return is_array($response->json()) ? $response->json() : [];
    }

    private function normalizeAndValidateEan(mixed $value): ?string
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
}
```

- [ ] **Step 2: Formatar e commitar**

```bash
./vendor/bin/sail vendor/bin/pint --dirty
git add app/Services/Integrations/GesCooper/GesCooperProductsIntegrationService.php
git commit -m "feat: add GesCooper products integration service"
```

---

## Task 6: Registrar no IntegrationServiceResolver

**Files:**
- Modify: `app/Services/Integrations/Support/IntegrationServiceResolver.php`

Adiciona `'gescooper'` nos três `match` do resolver. Sales e providers lançam `RuntimeException` por enquanto.

- [ ] **Step 1: Atualizar IntegrationServiceResolver**

Substituir o conteúdo completo do arquivo:

```php
<?php

namespace App\Services\Integrations\Support;

use App\Models\TenantIntegration;
use App\Services\Integrations\Contracts\ProductsIntegrationService;
use App\Services\Integrations\Contracts\ProvidersIntegrationService;
use App\Services\Integrations\Contracts\SalesIntegrationService;
use App\Services\Integrations\GesCooper\GesCooperProductsIntegrationService;
use App\Services\Integrations\Sysmo\SysmoProductsIntegrationService;
use App\Services\Integrations\Sysmo\SysmoProvidersIntegrationService;
use App\Services\Integrations\Sysmo\SysmoSalesIntegrationService;
use RuntimeException;

class IntegrationServiceResolver
{
    public function __construct(
        private readonly SysmoProductsIntegrationService $sysmoProductsIntegrationService,
        private readonly SysmoSalesIntegrationService $sysmoSalesIntegrationService,
        private readonly SysmoProvidersIntegrationService $sysmoProvidersIntegrationService,
        private readonly GesCooperProductsIntegrationService $gesCooperProductsIntegrationService,
    ) {}

    public function resolveProductsService(TenantIntegration $integration): ProductsIntegrationService
    {
        return match ($this->normalizeIntegrationType($integration->integration_type)) {
            'sysmo'     => $this->sysmoProductsIntegrationService,
            'gescooper' => $this->gesCooperProductsIntegrationService,
            default     => throw new RuntimeException('Servico de produtos nao mapeado para este tipo de integracao: '.(string) $integration->integration_type),
        };
    }

    public function resolveSalesService(TenantIntegration $integration): SalesIntegrationService
    {
        return match ($this->normalizeIntegrationType($integration->integration_type)) {
            'sysmo'     => $this->sysmoSalesIntegrationService,
            'gescooper' => throw new RuntimeException('Servico de vendas GesCooper ainda nao implementado.'),
            default     => throw new RuntimeException('Servico de vendas nao mapeado para este tipo de integracao: '.(string) $integration->integration_type),
        };
    }

    public function resolveProvidersService(TenantIntegration $integration): ProvidersIntegrationService
    {
        return match ($this->normalizeIntegrationType($integration->integration_type)) {
            'sysmo'     => $this->sysmoProvidersIntegrationService,
            'gescooper' => throw new RuntimeException('Servico de fornecedores GesCooper ainda nao implementado.'),
            default     => throw new RuntimeException('Servico de fornecedores nao mapeado para este tipo de integracao: '.(string) $integration->integration_type),
        };
    }

    private function normalizeIntegrationType(mixed $integrationType): string
    {
        if (! is_string($integrationType) && ! is_numeric($integrationType)) {
            return '';
        }

        return strtolower(trim((string) $integrationType));
    }
}
```

- [ ] **Step 2: Formatar e commitar**

```bash
./vendor/bin/sail vendor/bin/pint --dirty
git add app/Services/Integrations/Support/IntegrationServiceResolver.php
git commit -m "feat: register GesCooper in IntegrationServiceResolver"
```

---

## Task 7: Verificação end-to-end

- [ ] **Step 1: Verificar que o resolver reconhece o novo tipo**

```bash
./vendor/bin/sail artisan tinker --execute '
$resolver = app(App\Services\Integrations\Support\IntegrationServiceResolver::class);
$integration = new App\Models\TenantIntegration();
$integration->integration_type = "gescooper";
$service = $resolver->resolveProductsService($integration);
echo get_class($service);
'
```

Esperado: `App\Services\Integrations\GesCooper\GesCooperProductsIntegrationService`

- [ ] **Step 2: Configurar TenantIntegration de teste no banco**

Criar (via tinker ou painel) um `TenantIntegration` com:
```php
[
    'integration_type' => 'gescooper',
    'config' => [
        'auth' => [
            'type' => 'none',
            'credentials' => [
                'usuario'        => 'GOMARKAPI',
                'senha'          => '3axb$KuU%',
                'dispositivo_uid' => 'plannerate-dev',
            ],
        ],
        'connection' => [
            'base_url'        => 'https://web.cooasgo.com.br/GesCooper/Cadastro/Api',
            'timeout'         => 30,
            'connect_timeout' => 10,
            'verify_ssl'      => true,
        ],
        'processing' => [
            'products_page_size' => 200,
        ],
    ],
]
```

- [ ] **Step 3: Disparar sincronização inicial**

```bash
./vendor/bin/sail artisan integrations:dispatch-initial --tenant={TENANT_ID} --resource=products
```

- [ ] **Step 4: Verificar logs e banco**

```bash
# Logs em tempo real
./vendor/bin/sail artisan pail

# Conferir produtos persistidos (em outra sessão)
./vendor/bin/sail artisan tinker --execute '
echo \Illuminate\Support\Facades\DB::table("products")
    ->where("sync_source", "gescooper")
    ->count();
'
```

Esperado: contagem maior que 0, logs de itens ignorados (sem EAN) conforme esperado.
