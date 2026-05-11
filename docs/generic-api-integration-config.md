# Integracoes: Configuracao Generica de APIs

## Objetivo

Centralizar a configuracao de busca, paginacao, response e mapeamento de campos das APIs no backend, usando o cadastro de APIs do landlord.

A integracao do tenant deve guardar somente o que pertence ao tenant:

- API selecionada
- URL base
- autenticacao
- headers/params/body fixos da conexao
- janela e regras de processamento

A API cadastrada deve guardar o que pertence ao contrato da API:

- request base
- paths de produtos/vendas
- paginacao
- response
- field map
- transforms

## Fontes de Configuracao

### Cadastro de API

Modelo:

- `App\Models\IntegrationApi`

Tela:

- `resources/js/pages/landlord/integration-apis/Form.vue`

Campos principais:

- `slug`
- `requests`
- `response`
- `is_active`

O slug da API e usado como `TenantIntegration.integration_type`.

### Integracao do Tenant

Modelo:

- `App\Models\TenantIntegration`

Tela:

- `resources/js/pages/landlord/tenants/Integration.vue`

A tela do tenant deve listar somente APIs criadas e ativas em `integration_apis`.
Isso garante que o tenant nao salve um tipo solto, como `body-api` ou `generic`, sem configuracao cadastrada.

## Payload Resolvido

Ao salvar a integracao do tenant, o controller monta um payload final para debug:

- `app/Http/Controllers/Landlord/TenantIntegrationController.php`
- arquivo: `storage/app/private/{tenant_id}/last_payload.json`

Esse payload final junta:

- `requests` e `response` da API cadastrada
- `auth`, `connection` e `processing` do tenant

O banco continua salvando no tenant apenas a configuracao propria do tenant, para nao duplicar a API cadastrada.

## Estrutura Esperada do Payload

```json
{
  "integration_type": "body-api",
  "is_active": true,
  "config": {
    "requests": {},
    "response": {},
    "auth": {},
    "connection": {},
    "processing": {}
  }
}
```

## Request

Exemplo de request configurado:

```json
{
  "method": "POST",
  "payload": "body",
  "page_field": "pagina",
  "page_value_type": "integer",
  "page_size_field": "tamanho_pagina",
  "page_size_payload": "body",
  "min_page_size": 100,
  "max_page_size": 5000,
  "store_document_field": "empresa",
  "products": {
    "target_table": "products",
    "fallback_path": "/hubprodutos.listar_produtos",
    "date_fields": {
      "changed_since": "data_ultima_alteracao"
    },
    "field_map": []
  },
  "sales": {
    "target_table": "sales",
    "fallback_path": "/hubvendas.vendas_produtos",
    "date_fields": {
      "start": "data_inicial",
      "end": "data_final"
    },
    "field_map": []
  }
}
```

## Response

Exemplo BodyApi:

```json
{
  "items_path": "dados",
  "pagination": {
    "current_page_path": "pagina",
    "last_page_path": "total_paginas"
  }
}
```

Exemplo QueryApi:

```json
{
  "items_path": "data",
  "pagination": {
    "current_page_path": "pagination.current_page",
    "per_page_path": "pagination.per_page",
    "total_path": "pagination.total",
    "last_page_path": "pagination.last_page"
  }
}
```

## Field Map

O `field_map` transforma campos da API em campos internos.

Formato:

```json
{
  "target": "codigo_erp",
  "source": "produto",
  "transforms": ["string", "alnum"]
}
```

### Paths Simples

```json
{
  "target": "brand",
  "source": "marca.descricao",
  "transforms": ["string"]
}
```

### Arrays com Filtro

Para pegar o GTIN principal:

```json
{
  "target": "ean",
  "source": "gtins.completo[principal=S].gtin",
  "transforms": ["first", "ean"]
}
```

### Arrays com Wildcard

Para pegar a maior data de compra:

```json
{
  "target": "last_purchase_date",
  "source": "fornecedores.*.data_ultima_compra",
  "transforms": ["filter_filled", "max_date"]
}
```

## Expressoes

O campo `source` tambem pode ser uma expressao aritmetica.

Exemplo:

```json
{
  "target": "margem_contribuicao",
  "source": "valor_liquido - valor_impostos - custo_medio_loja",
  "transforms": ["round2"]
}
```

A expressao procura valores nesta ordem:

1. campos ja mapeados;
2. dados brutos da API.

Assim nao e preciso criar campos internos auxiliares apenas para calcular outro campo.

## Transforms Suportados

Atuais:

- `string`
- `alnum`
- `decimal`
- `integer`
- `float`
- `int`
- `ean`
- `date`
- `first`
- `filter_filled`
- `max`
- `max_date`
- `round2`

Observacao:

- `decimal` e alias de `float`.
- `integer` e alias de `int`.
- `round2` arredonda numeros em duas casas.

## Autenticacao

Configuracao atual no tenant:

- `none`
- `basic`
- `bearer` com token manual
- `bearer` buscando token em endpoint

Token buscado por endpoint:

- metodo
- path
- headers/params/body
- usuario/senha
- path do token na resposta

## Arquivos Principais

Backend:

- `app/Models/IntegrationApi.php`
- `app/Models/TenantIntegration.php`
- `app/Http/Controllers/Landlord/IntegrationApiController.php`
- `app/Http/Controllers/Landlord/TenantIntegrationController.php`
- `app/Http/Requests/Landlord/UpdateTenantIntegrationRequest.php`
- `app/Services/Integrations/IntegrationApiConfigResolver.php`
- `app/Services/Integrations/Importers/GenericIntegrationImporter.php`
- `app/Services/Integrations/Http/IntegrationHttpClient.php`
- `app/Services/Integrations/Http/IntegrationTokenResolver.php`
- `app/Services/Integrations/Support/IntegrationResponseReader.php`
- `app/Services/Integrations/Support/FieldResolver.php`
- `app/Services/Integrations/Support/FieldNormalizerRegistry.php`
- `app/Services/Integrations/Support/PersistImportedProductsService.php`
- `app/Services/Integrations/Support/PersistImportedSalesService.php`

Frontend:

- `resources/js/pages/landlord/integration-apis/Form.vue`
- `resources/js/pages/landlord/integration-apis/components/IntegrationApiPathRepeater.vue`
- `resources/js/pages/landlord/integration-apis/components/IntegrationApiFieldMapRepeater.vue`
- `resources/js/pages/landlord/integration-apis/components/IntegrationApiTransformTags.vue`
- `resources/js/pages/landlord/tenants/Integration.vue`

## Tarefa Pronta: Proxima Limpeza

### Objetivo

Fortalecer a validacao e a cobertura do sistema generico de APIs sem reintroduzir codigo por provider.

### Escopo Sugerido

1. Criar testes unitarios do resolvedor.

Cenarios minimos:

- mescla `requests/response` da API com `auth/connection/processing` do tenant;
- tenant nao sobrescreve `requests/response` sem necessidade;
- API inativa nao aparece no select;
- `last_payload.json` reflete a config resolvida;
- `field_map` com `gtins.completo[principal=S].gtin`;
- `field_map` com `fornecedores.*.data_ultima_compra`;
- expressao `valor_liquido - valor_impostos - custo_medio_loja`.

2. Criar validacoes de integridade para API cadastrada.

Validar no backend:

- recursos `products` e `sales` com `fallback_path` quando usados por importacao;
- `field_map` minimo para produtos: `codigo_erp` e `ean`;
- `field_map` minimo para vendas: `codigo_erp` e `sale_date`;
- token fetch com `path`, `username_field`, `password_field` e `response_path`.

### Resultado Esperado

Adicionar uma API nova deve exigir apenas:

1. cadastrar API no painel;
2. selecionar API no tenant;
3. configurar credenciais/conexao do tenant;
4. executar importacao.

Sem criar importer dedicado e sem alterar codigo para cada provider novo.
