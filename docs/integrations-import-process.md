# Integrações: Processo de Importação e Como Adicionar Nova API

## Visão Geral

O processo de importação foi dividido em etapas para ganhar resiliência:

1. **Dispatch**: comando diário dispara jobs por integração ativa.
2. **Fetch**: importer da API busca dados paginados.
3. **Batch**: cada página vira um lote.
4. **Process/Persist**: job separado trata e persiste o lote.

Com isso, falha de persistência não derruba a busca inteira e vice-versa.

---

## Fluxo Atual

### 1) Comando de disparo

- Arquivo: `app/Console/Commands/Integrations/DispatchDailyImportsCommand.php`
- Comando:
  - `php artisan integrations:daily-imports --type=products`
  - `php artisan integrations:daily-imports --type=sales`
  - `php artisan integrations:daily-imports --type=all`

Ele busca integrações ativas e dispara:

- `ImportProductsJob`
- `ImportSalesJob`

### 2) Importer por provider (fetch)

- `app/Services/Integrations/Importers/IntegrationImporter.php`
- Resolve o provider (`sysmo`, `gescooper`) e executa por escopo de loja (separação por loja quando habilitada).

Importers:

- `SysmoImporter`
- `GescooperImporter`

Responsabilidades do importer:

- Montar request (auth, headers, params/body).
- Paginar até o fim.
- Para cada página, enviar itens para lote de processamento.

### 3) Jobs de processamento por lote

- Produtos:
  - `ProcessImportedProductsBatchJob`
- Vendas:
  - `ProcessImportedSalesBatchJob`

Esses jobs recebem referência do lote e chamam os serviços de persistência.

### 4) Armazenamento temporário de lote

- `app/Services/Integrations/Support/ImportBatchPayloadStore.php`

O payload grande não vai no corpo serializado do job.
Ele é salvo temporariamente em arquivo local (`storage/app/imports/batches/...`) e o job recebe só a chave/caminho.

### 5) Persistência

- Produtos:
  - `PersistImportedProductsService`
  - DTO: `ProductNormalizedData`
- Vendas:
  - `PersistImportedSalesService`
  - DTO: `SalesNormalizedData`

Mapeamento declarativo por provider:

- Produtos: `ProductFieldMaps/*`
- Vendas: `SalesFieldMaps/*`

Resolução de campos + transforms:

- `FieldResolver`
- `FieldNormalizerRegistry`

IDs determinísticos:

- `DeterministicIdGenerator`

---

## Configuração da Integração (Tenant)

Campos importantes no `config` da integração:

- `connection.base_url`
- `connection.headers[]` (opcional)
- `connection.params[]` (opcional)
- `connection.body[]` (opcional)
- `auth` (basic/token/credenciais por provider)
- `paths.products`
- `paths.sales`
- `paths.auth` (quando necessário)
- `processing.separate_by_store` (bool)
- `processing.products_initial_days`
- `processing.sales_initial_days`
- `processing.products_page_size` (ex.: 300, 500)

Se `separate_by_store=true`, a busca roda por loja usando documento da loja (`empresa`), quando suportado pelo provider.

---

## Como Adicionar Nova API (Provider)

## 1) Criar importer

Criar classe em:

- `app/Services/Integrations/Importers/NovoProviderImporter.php`

Implementar `ClientApiImporter`:

- `importProducts(TenantIntegration $integration, ?Store $store = null): void`
- `importSales(TenantIntegration $integration, ?Store $store = null): void`

Padrão:

- Fazer fetch paginado.
- Resolver `total_pages`/`last_page` conforme payload da API.
- Para cada página:
  - salvar lote no `ImportBatchPayloadStore`
  - disparar `ProcessImportedProductsBatchJob` ou `ProcessImportedSalesBatchJob`

## 2) Registrar no IntegrationImporter

Editar `IntegrationImporter::resolve()` e incluir novo `match` para `integration_type`.

## 3) Criar mapas de campos

Produtos:

- Criar `app/Services/Integrations/Support/ProductFieldMaps/NovoProviderProductFieldMap.php`
- Registrar em `ProductFieldMapRegistry`

Vendas:

- Criar `app/Services/Integrations/Support/SalesFieldMaps/NovoProviderSalesFieldMap.php`
- Registrar em `SalesFieldMapRegistry`

Use formato declarativo:

- `paths` (fallback de campos)
- `transforms` (`string`, `float`, `date`, `ean`, `alnum`, etc.)

## 4) Validar regras mínimas

No map, definir `passesValidation()` com regras do provider.

Exemplos:

- Produto exige `name`
- Vendas exigem `codigo_erp` + `sale_date`

## 5) Configurar paths e auth no cadastro da integração

No tenant, preencher:

- `paths.products`
- `paths.sales`
- (se necessário) `paths.auth`
- credenciais e parâmetros de conexão

## 6) Testar

1. Disparar só products:
   - `php artisan integrations:daily-imports --type=products`
2. Acompanhar logs:
   - request por página
   - `... page fetched`
   - `Persistência ... concluída`

Depois repetir para sales.

---

## Observações Operacionais

- Se endpoint de sales não estiver ativo (ex.: 404), tratar como `skip` no importer para não quebrar o job todo.
- Evitar payload gigante no corpo do job.
- Ajustar `processing.products_page_size` para estabilidade de memória.
- Se houver filas antigas com schema de job antigo, reiniciar Horizon e limpar fila antes de novo disparo.

Comandos úteis (docker):

- `docker compose exec horizon php artisan horizon:terminate`
- `docker compose restart horizon`
- `docker compose exec php php artisan integrations:daily-imports --type=products`

