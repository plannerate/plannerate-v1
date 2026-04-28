# Integracao Sysmo: Sync de Produtos e Vendas

## Objetivo

Documentar o fluxo atual da integracao Sysmo para:
- sincronizacao inicial por periodo em dias;
- sincronizacao diaria com reprocessamento de lacunas;
- manutencao noturna (retencao de vendas e ciclo de vida de produtos).

## Escopo e tabelas

### Landlord (configuracao e controle)

- `tenant_integrations` (conexao `landlord`)
  - contem credenciais, endpoint, parametros de processamento e flags de ativacao.
- `integration_sync_days` (conexao `landlord`)
  - controle de execucao por dia e recurso (`sales`/`products`).
  - status: `pending`, `running`, `success`, `failed`.

### Tenant (dados de negocio)

- `products`
  - produto unico por tenant (nao duplicar por loja).
- `sales`
  - vendas por dia, loja e produto.
- `product_store`
  - pivot de vinculacao produto x loja.
  - evita duplicidade de produto por tenant.

## Configuracoes normalizadas (processing)

As configuracoes sao lidas via `TenantIntegrationConfigNormalizer`:

- `sales_initial_days` (default 120)
- `products_initial_days` (default 120)
- `sales_retention_days` (default 120)
- `daily_lookback_days` (default 7)
- `sales_page_size` (default 20000)
- `products_page_size` (default 1000)
- `sales_tipo_consulta` (default `produto`)
- `empresa` (fallback)
- `partner_key`

## Comandos e agenda

Comandos:
- `integrations:dispatch-initial`
- `integrations:dispatch-daily`
- `integrations:dispatch-nightly-maintenance`

Agendado em `routes/console.php`:
- diario `01:30`: dispatch diario
- diario `03:30`: manutencao noturna

## Runbook rapido

### 1) Carga inicial (bootstrap do tenant)

Quando usar:
- tenant novo;
- reprocessamento completo de periodo inicial.

Comando:
- `php artisan integrations:dispatch-initial --tenant=<tenant_id>`

Efeito:
- dispara jobs de vendas e produtos desde o periodo configurado em dias;
- processa por dia (e produtos por loja/pagina).

### 2) Operacao diaria

Quando usar:
- execucao recorrente do dia a dia;
- recuperar lacunas recentes automaticamente.

Comando:
- `php artisan integrations:dispatch-daily`
- opcional por tenant: `php artisan integrations:dispatch-daily --tenant=<tenant_id>`

Efeito:
- sempre inclui ontem;
- inclui dias com `failed/pending` na janela de lookback;
- dispara sync de vendas e produtos.

### 3) Manutencao noturna

Quando usar:
- limpeza de vendas antigas;
- soft delete/restaure de produtos conforme janela de vendas.

Comando:
- `php artisan integrations:dispatch-nightly-maintenance`
- opcional por tenant: `php artisan integrations:dispatch-nightly-maintenance --tenant=<tenant_id>`

Efeito:
- remove vendas fora da retencao;
- soft delete em produtos sem venda na janela;
- restaura produtos que voltaram a vender.

## Fluxo de vendas

### Regra de consulta

Vendas sao consultadas por **dia** e por **loja**.

Body base:
- `pagina`
- `tamanho_pagina` (alto, default 20000)
- `data_inicial` = dia
- `data_final` = dia
- `tipo_consulta` (config da integracao)
- `partner_key` (config da integracao)
- `empresa` (identificador da loja na Sysmo)

### Loja -> empresa

Prioridade para montar `empresa`:
1. `stores.code`
2. `stores.document`
3. fallback `processing.empresa`

### Persistencia

- resolve produto por `tenant_id + codigo_erp`;
- preenche `sales.product_id`;
- preenche `sales.ean` a partir de `products.ean`;
- `store_id` salvo na venda;
- ID deterministico com prefixo `S1`.

## Fluxo de produtos

### Restricao da API

Para delta diario, produtos usam `data_ultima_alteracao`.

Body base:
- `pagina`
- `tamanho_pagina` (config)
- `data_ultima_alteracao` = dia
- `empresa` (loja)
- `partner_key`

### Estrategia de paginação (2 fases)

1. `DispatchTenantProductStorePagesJob`
   - faz chamada inicial (`pagina = 1`) por loja/dia;
   - le `total_paginas`;
   - dispara um job por pagina.

2. `SyncTenantProductStorePageJob`
   - processa apenas uma pagina especifica;
   - persiste produtos e relacao loja.

### Persistencia

- produto unico por tenant (nao duplica por loja);
- usa `ean_references` para enriquecer categoria e dados comerciais;
- restaura produto soft deleted quando reaparece;
- registra pivot `product_store` (`tenant_id`, `product_id`, `store_id`, `last_synced_at`);
- ID deterministico com prefixo `P1`.

## Rotina diaria com lacunas

`DispatchTenantIntegrationDailySyncJob`:
- calcula janela por `daily_lookback_days`;
- sempre inclui ontem;
- inclui dias com falha/pending no controle `integration_sync_days`;
- dispara jobs de vendas e produtos por dia.

## Manutencao noturna

`RunTenantIntegrationNightlyMaintenanceJob`:
- remove vendas antigas com delete fisico (`sale_date` fora da retencao);
- soft delete em produtos sem venda na janela de retencao;
- restaura produtos soft deleted que voltaram a vender.

## Idempotencia e resiliencia

- processamento por dia reduz impacto de erro (perde no maximo um dia por falha);
- IDs deterministicos para `products` e `sales`;
- controle por dia/recurso em `integration_sync_days`;
- reprocessamento seguro de lacunas.

## Proximos passos recomendados

- adicionar retries com backoff por tipo de erro da API;
- observabilidade por tenant (metricas de paginas processadas e latencia);
- limites de concorrencia por tenant/loja para proteger banco e API.
