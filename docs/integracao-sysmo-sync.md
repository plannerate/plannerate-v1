# Integracao Sysmo: Sync de Produtos e Vendas

## Objetivo

Documentar o fluxo atual da integracao Sysmo para:
- sincronizacao inicial por periodo em dias;
- sincronizacao diaria com reprocessamento de lacunas;
- padronizacao pos-sync de produtos por `ean_references`;
- vinculacao pos-sync de vendas aos produtos por `codigo_erp`;
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
- `ean_references`
  - referencia canonica por `tenant_id + ean`;
  - usada em rotina separada para preencher campos vazios do produto, principalmente `category_id`.

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
- `sync:cleanup`
- `sync:products-from-ean-references`
- `sync:link-sales`

Pre-validacao antes do dispatch (`initial` e `daily`):
- ambos usam `ValidateIntegrationStoresService`;
- lojas com falha de configuracao/API sao marcadas como `draft` para nao reentrar automaticamente;
- o tenant so e bloqueado quando **nenhuma** loja valida permanece;
- se houver ao menos uma loja valida, o dispatch continua para evitar bloqueio total por uma loja ruim;
- os comandos devem carregar a integracao completa (`$query->get()`), pois `integration_type`/config sao obrigatorios na validacao.

Agendado em `routes/console.php`:
- diario `07:30`: dispatch diario
- diario `03:30`: manutencao noturna

Servicos no `compose.yaml`:
- `scheduler`
  - executa `php artisan schedule:work`;
  - e o responsavel por acionar os horarios definidos em `routes/console.php`.
- `queue`
  - executa `php artisan queue:work --queue=default --sleep=3 --tries=1 --timeout=650 --memory=1024`;
  - processa os jobs de sync, pos-sync, notificacoes e broadcasts;
  - usa `DB_QUEUE_RETRY_AFTER` maior que o timeout dos jobs longos.

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
- ao fim da cadeia principal, dispara pos-sync do tenant:
  - `sync:cleanup --tenant=<tenant_id> --all`;
  - `sync:products-from-ean-references --tenant=<tenant_id>`;
  - `sync:link-sales --tenant=<tenant_id>`.

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
- envia `AppNotification` (`database` + `broadcast`) informando `Sincronização diária iniciada` quando a integracao e efetivamente enfileirada.
- ao fim da cadeia principal, dispara pos-sync do tenant:
  - `sync:cleanup --tenant=<tenant_id> --all`;
  - `sync:products-from-ean-references --tenant=<tenant_id>`;
  - `sync:link-sales --tenant=<tenant_id>`.

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
- a persistencia de vendas nao vincula produtos no meio do fluxo;
- vinculacao de produto ocorre em comando separado (`sync:link-sales`), normalmente no pos-sync.

### Vinculacao de vendas aos produtos

Comando:
- `php artisan sync:link-sales --tenant=<tenant_id>`
- preview: `php artisan sync:link-sales --tenant=<tenant_id> --preview`

Regra:
- atualiza `sales.product_id` e `sales.ean` por `tenant_id + codigo_erp`;
- considera vendas com `product_id` nulo e `codigo_erp` preenchido;
- em MySQL/MariaDB usa `UPDATE ... JOIN` em lote;
- em SQLite (testes) usa fallback por `UPDATE ... FROM`;
- hoje a regra de banco ainda esta no command;
- proximo passo planejado: extrair para service para permitir futura execucao por fila.

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
   - nao dispara reconciliacao pesada ao finalizar a pagina.

### Persistencia

- produto unico por tenant (nao duplica por loja);
- persistencia grava apenas os dados vindos da integracao;
- padronizacao por `ean_references` roda em comando separado no pos-sync;
- restaura produto soft deleted quando reaparece;
- registra pivot `product_store` (`tenant_id`, `product_id`, `store_id`, `last_synced_at`);
- ID deterministico com prefixo `P1`.

### Padronizacao de produtos por `ean_references`

Comando:
- `php artisan sync:products-from-ean-references --tenant=<tenant_id>`
- preview: `php artisan sync:products-from-ean-references --tenant=<tenant_id> --preview`

Regra:
- busca referencia por `tenant_id + ean`;
- preenche apenas campos vazios/nulos do produto;
- nao sobrescreve informacoes ja preenchidas no produto;
- campo principal: `category_id`;
- campos extras: `description`, `brand`, `subbrand`, `packaging_type`, `packaging_size`, `measurement_unit`;
- nao executa nenhuma vinculacao de vendas.

## Rotina diaria com lacunas

`DispatchTenantIntegrationDailySyncJob`:
- calcula janela por `daily_lookback_days`;
- sempre inclui ontem;
- inclui dias com falha/pending no controle `integration_sync_days`;
- monta uma cadeia de jobs de vendas/produtos por dia;
- adiciona `RunTenantIntegrationPostSyncJob` ao final da cadeia.

Notificacoes:
- `DispatchDailyCommand` envia `Sincronização diária iniciada` para usuarios ativos do tenant quando a integracao passa na pre-validacao e e enfileirada;
- `ValidateIntegrationStoresService` envia notificacao de erro quando lojas falham na pre-validacao;
- `SyncTenantSalesDayJob` envia notificacao de sucesso/falha por dia de vendas;
- `SyncTenantProductsDayJob` envia notificacao de sucesso/falha por dia de produtos;
- `sync:cleanup` e `sync:link-sales` podem enviar notificacoes no pos-sync.

## Pos-sync da integracao

`RunTenantIntegrationPostSyncJob` roda apos a cadeia principal de sync do tenant:

1. `sync:cleanup --tenant=<tenant_id> --all`
   - executa limpeza de vendas antigas/orfas e ciclo de vida de produtos.
2. `sync:products-from-ean-references --tenant=<tenant_id>`
   - padroniza produtos por EAN sem sobrescrever campos existentes.
3. `sync:link-sales --tenant=<tenant_id>`
   - vincula vendas aos produtos por `codigo_erp`.

Observacao importante:
- as paginas de produtos ainda sao progressivas;
- se for necessario garantir pos-sync somente apos a ultima pagina progressiva de produto, o proximo passo e transformar as paginas em batch/cadeia controlada.

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
- `withoutOverlapping()` evita sobreposicao dos schedules principais usando lock compartilhado do cache configurado;
- subdominios nao disparam scheduler individualmente: o scheduler roda uma vez no container e consulta as integracoes ativas no landlord.

## Proximos passos recomendados

- adicionar retries com backoff por tipo de erro da API;
- observabilidade por tenant (metricas de paginas processadas e latencia);
- limites de concorrencia por tenant/loja para proteger banco e API.
