# GesCooper Products Integration — Design

**Date:** 2026-05-07
**Scope:** Produtos apenas (Sales em sprint futura)

## Contexto

O projeto já tem integração Sysmo para produtos/vendas/fornecedores. A Coasgo (cooperativa) usa o sistema GesCooper com API própria em `https://web.cooasgo.com.br/GesCooper/Cadastro/Api`. O objetivo é criar uma integração análoga à do Sysmo que sincronize produtos GesCooper na mesma tabela `products` do tenant, reutilizando os contratos, comandos e serviços de dispatch existentes.

## Padrão existente (referência)

- **Contratos:** `App\Services\Integrations\Contracts\ProductsIntegrationService`
- **Resolver:** `App\Services\Integrations\Support\IntegrationServiceResolver` — roteia `integration_type` para a implementação correta
- **Dispatch:** `DispatchDailyCommand` e `DispatchInitialCommand` já chamam o resolver; nenhuma alteração necessária
- **Persistência:** `SysmoProductsIntegrationService::persistMappedProducts()` — upsert em `products` + `product_store`

## Arquitetura GesCooper

### Novos arquivos

```
app/Services/Integrations/GesCooper/
├── GesCooperAuthService.php
├── GesCooperEndpoints.php
├── GesCooperProductsIntegrationService.php
├── GesCooperProductsResponseMapper.php
└── Concerns/
    ├── NormalizesGesCooperValues.php
    └── ExtractsGesCooperPayloadItems.php
```

### Arquivo modificado

- `app/Services/Integrations/Support/IntegrationServiceResolver.php` — adiciona `'gescooper'` no `match` de `resolveProductsService()`; sales/providers lançam `RuntimeException` por ora.

## Autenticação (dinâmica)

A API requer Bearer JWT obtido via login. **Não usa** o fluxo padrão do `ExternalApiBaseService` para auth — a `GesCooperProductsIntegrationService` gerencia o token internamente.

**`GesCooperAuthService`:**
- `getToken(TenantIntegration $integration): string`
- Lê credenciais de `$integration->config['auth']['credentials']`: `usuario`, `senha`, `dispositivo_uid`
- Faz `POST {base_url}/v1/Token` com `Content-Type: application/json`
- Retorna o Bearer token do campo `token` da resposta
- Cacheia o token em propriedade privada durante a execução do job (evita chamadas duplicadas em paginação)

**Config do `TenantIntegration` (tipo `gescooper`):**
```php
'auth' => [
    'type' => 'none', // ExternalApiBaseService não aplica auth
    'credentials' => [
        'usuario'       => 'GOMARKAPI',
        'senha'         => '...',
        'dispositivo_uid' => 'string',
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
```

## Endpoints

**`GesCooperEndpoints`:**
```php
'products' => 'Produtos/Produtos',
'product'  => 'Produtos/Produtos/{id}',
```

Query params obrigatórios: `pagina`, `registros_por_pagina`, `api-version=1.0`

## Response mapper

**`GesCooperProductsResponseMapper` implements `ProductsResponseMapper`:**

Extrai items de `$payload['data']`. Mapeamento campo a campo:

| Campo API GesCooper    | Campo interno (`mapItem` output) | Campo `products` |
|------------------------|----------------------------------|------------------|
| `id_produto`           | `external_id`                    | `codigo_erp`     |
| `ean`                  | `ean`                            | `ean`            |
| `descricao_completa`   | `name`                           | `name`           |
| `marca`                | `brand`                          | `brand`          |
| `submarca`             | `subbrand`                       | `subbrand`       |
| `status_produto`       | `status`                         | `sales_status`   |
| `data_ultima_compra`   | `last_purchase_date`             | `last_purchase_date` |
| `altura`               | `height`                         | `height`         |
| `largura`              | `width`                          | `width`          |
| `profundidade`         | `depth`                          | `depth`          |
| `tipo_embalagem`       | `packaging_type`                 | `packaging_type` |
| `tamanho_embalagem`    | `packaging_size`                 | `packaging_size` |
| `unidade_medida`       | `unit`                           | `measurement_unit` |
| `fragrancia`           | `fragrance`                      | `fragrance`      |
| `sabor`                | `flavor`                         | `flavor`         |
| `cor`                  | `color`                          | `color`          |
| `referencia`           | `reference`                      | `reference`      |
| `descricao_auxiliar`   | `auxiliary_description`          | `auxiliary_description` |
| `informacao_adicional` | `additional_information`         | `additional_information` |
| `estoque_atual`        | `current_stock`                  | `current_stock`  |
| `segmento_varejista`   | `sortiment_attribute`            | `sortiment_attribute` |

Inclui `'raw' => $item` para debugging.

## Service principal

**`GesCooperProductsIntegrationService` implements `ProductsIntegrationService`:**

```
__construct(
    GesCooperAuthService,
    GesCooperEndpoints,
    GesCooperProductsResponseMapper,
    DeterministicIdGenerator,
    SyncSalesProductReferencesService,
    TenantIntegrationConfigNormalizer,
)
```

**`fetchProducts()`:**
1. `GesCooperAuthService::getToken($integration)` → Bearer JWT
2. `Http::withToken($token)->get(...)` com `pagina`, `registros_por_pagina`, `api-version=1.0`
3. `$responseMapper->mapMany($payload['data'])`
4. `persistMappedProducts(...)` — lógica idêntica ao Sysmo (skip sem EAN, upsert, product_store)

**`discoverProductsTotalPages()`:**
- Retorna `$payload['pagination']['last_page']` (diferente do Sysmo que usa `total_paginas`)

**`persistMappedProducts()`:**
- Idêntico ao Sysmo: valida EAN + codigo_erp, gera ID determinístico, upsert em `products`, upsert em `product_store` se `store_id` presente.

**`finalizePersistedProductsSync()`:**
- Idêntico ao Sysmo: enriquece via `EanReference`, chama `SyncSalesProductReferencesService`.

## Concerns (traits)

**`NormalizesGesCooperValues`:**
- `normalizeString(mixed): ?string`
- `normalizeFloat(mixed): ?float`
- `normalizeDate(mixed): ?string`
- `normalizeCodigoErp(?string): ?string`
- `normalizeEan(mixed): ?string` — apenas dígitos, máx 13 chars

**`ExtractsGesCooperPayloadItems`:**
- `extractItemsFromPayload(array): array` — retorna `$payload['data'] ?? []`

## Paginação

A paginação usa query params GET (não request body como Sysmo):
- `pagina` = número da página
- `registros_por_pagina` = tamanho da página (default 200, configurável via `processing.products_page_size`)
- `api-version` = `1.0` (sempre fixo)

## Verificação

1. Configurar `TenantIntegration` com `integration_type = 'gescooper'` e credenciais corretas
2. Rodar: `php artisan integrations:dispatch-initial --tenant={id} --resource=products`
3. Verificar logs: token obtido, páginas descobertas, itens persisitidos vs ignorados (sem EAN)
4. Confirmar registros em `products` com `sync_source = 'gescooper'`
5. Verificar `product_store` se store_id configurado
