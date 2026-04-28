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

Pre-validacao antes do dispatch (`initial` e `daily`):
- ambos usam `ValidateIntegrationStoresService`;
- lojas com falha de configuracao/API sao marcadas como `draft` para nao reentrar automaticamente;
- o tenant so e bloqueado quando **nenhuma** loja valida permanece;
- se houver ao menos uma loja valida, o dispatch continua para evitar bloqueio total por uma loja ruim;
- os comandos devem carregar a integracao completa (`$query->get()`), pois `integration_type`/config sao obrigatorios na validacao.

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
- executa pre-validacao das lojas antes de enfileirar jobs.

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
- executa a mesma pre-validacao de lojas usada no fluxo inicial.

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
- `store_document` (metadado interno para idempotencia do ID deterministico)

### Loja -> empresa

Prioridade para montar `empresa`:
1. `stores.document`
2. `stores.code`
3. fallback `processing.empresa`

### Persistencia

- persistencia em lote com `upsert` por `id` (chave deterministica);
- `id` de venda e gerado por: `tenant_id + integration_id + store_document + codigo_erp + sale_date + promotion` (prefixo `S1`);
- `store_document` e obrigatorio para gerar ID:
  - prioridade: `item.store_identifier` (retorno da API);
  - fallback controlado: `filters.store_document` (enviado pelo job);
  - se ausente, a linha de venda e ignorada com log de warning;
- `store_id` continua salvo na tabela `sales` para rastreabilidade operacional;
- sincronizacao de referencia de produto ocorre ao final da carga:
  - atualiza `sales.product_id` e `sales.ean` por `tenant_id + codigo_erp`;
  - em MySQL/MariaDB usa `UPDATE ... JOIN` em lote;
  - em SQLite (testes) usa fallback por subquery.

## Fluxo de produtos

### Restricao da API

Para delta diario, produtos usam `data_ultima_alteracao`.

Body base:
- `pagina`
- `tamanho_pagina` (config)
- `data_ultima_alteracao` = dia
- `empresa` (loja)
- `partner_key`

### Estrategia de paginacao progressiva

1. `DispatchTenantProductStorePagesJob`
   - dispara apenas a `pagina = 1` por loja;
   - nao faz pre-descoberta de `total_paginas`.

2. `SyncTenantProductStorePageJob`
   - processa uma pagina e persiste produtos/relacao loja;
   - enfileira a proxima pagina progressivamente quando necessario;
   - ao finalizar a loja, dispara a etapa de finalizacao pos-persistencia.

### Persistencia

- produto unico por tenant (nao duplica por loja);
- persistencia e finalizacao sao separadas: primeiro grava, depois normaliza/reconcilia;
- usa `ean_references` na etapa de finalizacao para enriquecer categoria e dados comerciais;
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
- IDs deterministicos para `products` e `sales` (sem depender de `store_id` no caso de vendas);
- controle por dia/recurso em `integration_sync_days`;
- reprocessamento seguro de lacunas.

## Proximos passos recomendados

- adicionar retries com backoff por tipo de erro da API;
- observabilidade por tenant (metricas de paginas processadas e latencia);
- limites de concorrencia por tenant/loja para proteger banco e API.
