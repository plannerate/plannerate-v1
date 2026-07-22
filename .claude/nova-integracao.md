# Cadastrar uma nova integração de ERP

Guia para plugar um ERP novo no motor de importação (produtos/vendas). Escrito a partir do trabalho real na integração **Coasgo/GesCooper** (2026-07-21).

---

## 1. A regra de ouro: não existe classe por provider

O motor é **100% config-driven**. Não escreva client, fetcher, mapper nem job para o ERP novo — tudo isso já existe e é genérico. Uma "integração" é **dado**, não código:

| Peça | Onde | O que guarda |
|---|---|---|
| `IntegrationApi` (blueprint) | tabela `integration_apis` (landlord), UI em `/integration-apis` | *Como* falar com a API: endpoints, paginação, `field_map`, pivots, validações. Sem credenciais. |
| `TenantIntegration` (conexão) | tabela `tenant_integrations` (landlord), UI em `/tenants/{tenant}/integration` | *Onde* e *com quem*: `base_url`, auth, credenciais, params fixos. `config` é `encrypted:array`. |

Um blueprint serve **N tenants**. Ex.: `gescooper` é o ERP GesCooper (Infogen) usado pela Coasgo — o slug é do **ERP**, não do cliente.

**Só escreva PHP se** a API exigir um transform, um modo de auth ou uma sintaxe de source que o motor ainda não tem (ver §7).

### Fluxo em runtime (só para entender, não para mexer)

```
integration:run (agendado 06:00, routes/console.php)
  └─ DiscoverIntegrationPagesJob   → descobre nº de páginas; itera lojas se store_document_field
      └─ FetchIntegrationPageJob   → HTTP + field_map + id determinístico → grava JSON no disco
          └─ ProcessPageResponseJob → TenantRecordPersister: reconcilia + upsert + pivots
```

Classes de referência (leia, não altere): `app/Services/Integrations/` (`IntegrationHttpClient`, `IntegrationPayloadBuilder`, `RecordMapper`, `FieldValueResolver`, `TenantRecordPersister`, `TenantNaturalKeyReconciler`, `TenantPivotRecordPersister`).

---

## 2. Passo a passo

### Passo 0 — Juntar material real (não pule)

Salve em `storage/app/private/<slug>/`:
- a coleção Postman / doc da API;
- **respostas reais**: uma página da listagem e um item único.

Sem payload real você vai mapear no escuro e o Passo 1 é impossível.

### Passo 1 — Medir taxa de preenchimento ANTES de mapear

Este passo evita o erro mais caro do motor (§7.1). Rode sobre o payload real:

```bash
python3 -c "
import json
d = json.load(open('storage/app/private/<slug>/resposta-produtos.json'))['data']
n = len(d)
for f in sorted(d[0].keys()):
    filled = [x.get(f) for x in d if x.get(f) not in (None, '')]
    ex = str(filled[0])[:38] if filled else '-'
    print(f'{f:28} {len(filled):>6} ({100*len(filled)//n:>3}%)  {ex}')
"
```

**Mapeie apenas campo com preenchimento real.** Campo 0% fica de fora.

### Passo 2 — Escolher a identidade (`unique_by`)

O `unique_by` gera o **id determinístico** (`DeterministicIdGenerator`), que é o que faz o re-import virar UPDATE em vez de duplicar.

Duas restrições que brigam entre si — resolva conscientemente:

1. **Chave natural do banco é fixa no código**, em `TenantNaturalKeyReconciler::NATURAL_KEYS`:
   - `products` → `['ean']` (índice `unique(tenant_id, ean)`)
   - `sales` → `['store_id','codigo_erp','sale_date','promotion']`
2. **Campo que pode vir null é veneno em `unique_by`**: todos os registros sem valor geram **o mesmo id** e colapsam numa linha só.

Receita:
- **Quer só itens com EAN?** → `unique_by: ['ean']` + `ean` com transform `not_null` (descarta o resto de propósito). Foi a escolha da Coasgo.
- **Quer o catálogo inteiro, mesmo sem EAN?** → `unique_by: ['codigo_erp']` (campo sempre presente) e **não** ponha `not_null` no `ean`. O reconciler continua casando por EAN quando houver.

### Passo 3 — Montar `requests`

```jsonc
{
  "method": "GET",                          // GET → params na query; POST → no body
  "page_field": "pagina",
  "page_size_field": "registros_por_pagina",
  "page_value_type": "integer",
  "store_document_field": "empresa",         // OPCIONAL — leia §7.3 antes de usar
  "paths": {
    "products": {
      "target_table": "products",
      "fallback_path": "/Produtos",          // concatenado em rtrim(base_url,'/')
      "unique_by": ["ean"],
      "id_prefix": "P1",
      "min_page_size": 1,                    // usado na descoberta (só quer o total)
      "max_page_size": 1000,
      "date_fields": { "changed_since": "data_alteracao" },   // ou {start, end}
      "field_map": [
        { "target": "name", "source": "descricao_completa", "transforms": ["string"] }
      ],
      "pivot_tables": [ /* §7.3 */ ],
      "validations": [
        { "type": "all_of", "sources": ["ativo"], "allowed_values": ["S"] }
      ]
    }
  }
}
```

Chaves reservadas no topo de `requests` (o resto vira path legado): `method, paths, page_field, page_value_type, page_size_field, page_size_payload, default_page_size, min_page_size, max_page_size, store_document_field, fixed_query, fixed_body, date_strategy, enabled, unique_by, initial_days, run_finalize, pagination_mode`.

#### Paginação por cursor (`pagination_mode: "cursor"`)

Para APIs que **não informam total de páginas** e identificam a próxima pelo id do
último item (RP Info: `.../lastid/{lastId}`). Chaves adicionais no path:

```jsonc
"fallback_path": "/v3.2/produtounidade/listaprodutos/{cursor}/unidade/{store_document}/detalhado",
"cursor_item_path": "Codigo",     // campo do ITEM BRUTO que alimenta o {cursor}
"cursor_initial": 0,              // valor que significa "do começo"
"items_path": "response.produtos" // override do items_path global (por endpoint)
```

Como funciona: o `CursorModeDiscoverer` **semeia** 1 job por loja (sem sondagem HTTP — não
há o que sondar) e cada `FetchIntegrationPageJob` despacha o seguinte com o cursor novo.
Para em página vazia ou cursor que não avança. O modo **diário tem precedência**: com
`initial_days` + `last_date_column`, cada dia vira uma cadeia própria (paralelismo entre
dias, cobertura por dia).

Cobertura do run em modo cursor: `expected_units = 1` — mede "a cadeia começou", já que o
número de páginas é desconhecido por definição.

Placeholders do `fallback_path`: `{cursor}` e `{store_document}`. Quando o documento está
no path, ele **não** é repetido na query (`IntegrationUrlBuilder::consumesStoreDocumentInPath`).

#### `date_query_format`

O motor manda datas em `Y-m-d`. APIs que exigem outro formato declaram no path:
`"date_query_format": "d-m-Y"`. Sem essa chave nada muda.

#### `lag_days`

Desloca a janela do modo diário para trás: `"lag_days": 1` faz a busca começar em ontem.
Use quando o ERP só fecha o movimento no dia seguinte (§7.9). Default 0.

#### Métrica por loja: `pivot_only_targets` + `update_columns`

Coluna que existe na tabela principal mas cujo valor é **por unidade** (estoque, última
compra) não pode ficar em `products`: o id do produto deriva de `tenant + ean`, sem loja, e
a última cadeia de importação a terminar sobrescreve as demais. Medido na RP Info: 52% dos
produtos com estoque diferente entre as lojas.

```jsonc
"paths": { "products": {
  "field_map": [ {"target": "current_stock", "source": "Estoque1", "transforms": ["decimal"]} ],
  "pivot_only_targets": ["current_stock", "last_purchase_date"],   // removidos do upsert de products
  "pivot_tables": [{
    "table": "product_store", "local_key": "id",
    "foreign_key": "product_id", "related_key": "store_id",
    "unique_by": ["tenant_id", "product_id", "store_id"],
    "update_columns": ["current_stock", "last_purchase_date"]      // sem isso o valor congela
  }]
}}
```

O `update_columns` é o detalhe fácil de esquecer: o upsert da pivot atualiza só
`updated_at` por padrão, então a métrica ficaria travada no primeiro import.

### Passo 4 — Montar `response`

Aponte para onde estão os itens e a paginação:

```jsonc
{
  "items_path": "data",                       // Coasgo: "data" | Sysmo: "dados"
  "pagination": {
    "current_page_path": "pagination.current_page",
    "per_page_path": "pagination.per_page",
    "total_path": "pagination.total",
    "last_page_path": "pagination.last_page"
  },
  // Opcional — só para APIs que devolvem erro com HTTP 200 (ver §7.8)
  "error_status_path": "response.status",
  "error_status_values": ["error"],           // default quando omitido
  "error_message_path": "response.messages"
}
```

Quando cada endpoint tem um envelope diferente (RP Info: `response.produtos` ×
`response.movimentos`), ponha `items_path` **no path config** — ele vence o global.

### Passo 5 — Gravar o blueprint (migration landlord)

Versione via migration — é o que replica o cadastro em todos os ambientes. Modelos: `database/migrations/landlord/2026_07_15_000001_add_lookups_to_sysmo_integration_api.php` e `2026_07_21_000001_expand_gescooper_product_field_map.php`.

Sempre **guardada e idempotente** (`where('slug', ...)->first()`, `return` se null, não duplicar target já mapeado) e com `down()` que remove só o que ela acrescentou.

```bash
# landlord NÃO roda no migrate padrão — precisa do --path
docker compose exec php php artisan migrate --path=database/migrations/landlord --database=landlord --no-interaction
```

Alternativas: a UI `/integration-apis`, ou o import de JSON (`POST integration-apis/import`, formato `{"integration_apis":[{name,slug,requests,response,is_active}]}`) — bom para mover config entre ambientes, mas não versiona.

### Passo 6 — Configurar o tenant (nunca em migration)

Credenciais são dado por tenant/ambiente e ficam **encriptadas**. Faça pela UI `/tenants/{tenant}/integration`:

```jsonc
{
  "connection": {
    "base_url": "https://host/Api/v1/",
    "headers": [], "params": [], "body": []       // {key,value,enabled} — params fixos (api-version, filtros)
  },
  "auth": {
    "type": "bearer",                              // "basic" | "bearer"
    "token_mode": "fetch",                         // "fetch" = busca token; senão usa credentials.token
    "token_header": "",                            // vazio = Authorization: Bearer; "token" = header próprio (RP Info)
    "credentials": { "username": "...", "password": "..." },
    "token_request": {
      "method": "POST", "path": "Token",
      "username_field": "usuario", "password_field": "senha",
      "response_path": "token"                     // onde o token está na resposta
    }
  }
}
```

Depois: `is_active = true` e `integration_type` = id do blueprint.

### Passo 7 — Testar e verificar

```bash
docker compose exec php vendor/bin/pint --dirty --format agent
docker compose exec php php artisan test --compact tests/Feature/Integrations/<SeuTeste>.php
```

Validar o mapeamento contra o payload real **sem bater na API** (rápido e conclusivo):

```php
$fm = data_get(IntegrationApi::where('slug','<slug>')->first()->requests, 'paths.products.field_map');
$item = json_decode(file_get_contents(storage_path('app/private/<slug>/resposta-produtos.json')), true)['data'][0];
app(RecordMapper::class)->map($item, $fm, 'STORE-1');           // registro mapeado
app(RecordMapper::class)->mapWithRejectionReason($item, $fm);   // [null, 'campo'] quando rejeita
```

Só então (opcional, contra a API real): `php artisan integration:run`.

---

## 3. Referência: transforms disponíveis

Whitelist de `FieldValueResolver::applyTransform` — **qualquer outro nome é ignorado silenciosamente**:

| Transform | Efeito |
|---|---|
| `string` / `decimal` / `integer` | cast (preserva null) |
| `alnum` | remove tudo que não é `[a-zA-Z0-9]` |
| `date` | `Carbon::parse` → `Y-m-d`; retorna null se não parsear |
| `ean` | normaliza código de barras |
| `first` | primeiro item de array |
| `filter_filled` | remove null/'' de array |
| `max_date` | maior data de um array |
| `round2` | arredonda em 2 casas |
| `date_dmy` | `dd/MM/yyyy` (com barra, opcionalmente com hora) → `Y-m-d`. **Necessário** porque `date` lê a barra como `m/d/Y`, vê mês 15 e devolve null |
| `not_null` | **rejeita o registro inteiro** se o valor for null |

Ordem importa: são aplicados em sequência.

## 4. Referência: sintaxe de `source`

| Forma | Exemplo |
|---|---|
| Caminho simples | `unidade_venda.descricao` |
| Filtro em array | `gtins.completo[principal=S].gtin` |
| Wildcard | `fornecedores.*.data_ultima_compra` (combine com `filter_filled` + `max_date`) |
| Aritmética | `valor_liquido - valor_impostos - custo_medio_loja` |

## 5. Referência: colunas mapeáveis

O `target` precisa estar no whitelist de `config/integrations.php` → `field_map_tables` (`products`, `sales`, `stores`). Além disso o `TenantUpsertRecordPreparer` descarta o que não for coluna real da tabela. Ou seja: **target fora do whitelist falha em silêncio**.

`products` não tem coluna de texto para categoria — só `category_id` (FK). Mapear `categoria`/`departamento` direto não funciona.

---

## 6. Testes

Modelos: `tests/Feature/Integrations/ImportPipelineEndToEndTest.php` (pipeline ponta a ponta com `Http::fake`) e `tests/Feature/Integrations/GescooperProductFieldMapTest.php` (contrato do `field_map`, incl. asserção de que campos proibidos **não** estão mapeados).

Testes que precisam das tabelas landlord fazem `migrate:fresh --database=landlord --path=database/migrations/landlord` no `beforeEach`.

> **Rode um arquivo por invocação.** `landlord` e o default apontam para o mesmo banco, então esse `migrate:fresh` derruba as tabelas e cascateia falhas nos arquivos seguintes. `tests/Feature/Integrations` inteiro já dá ~135 falhas **sem nenhuma mudança local**. Antes de concluir que quebrou algo, compare com `git stash`.

---

## 7. Armadilhas (aprendidas na marra)

### 7.1. Mapear campo vazio APAGA dado bom — a pior delas

Todo target presente no registro entra nas *update columns* do upsert. Se o source vier `null`/`0`, o import **grava null por cima**, a cada execução.

Casos reais no `gescooper`:
- `altura/largura/profundidade` vêm null em 998/1000 — mas `width/height/depth` são preenchidos pelo pipeline de IA (`.claude/dimension-research.md`). Mapear **zeraria ~4.800 dimensões pesquisadas**.
- `submarca`, `sabor`, `cor`, `referencia`…: 0% no feed → mapear apagaria o que for digitado na UI.

**Regra:** mapeie só o que o Passo 1 mostrou preenchido, e cheque se outra fonte já é dona daquela coluna. Trave o invariante num teste.

### 7.2. `unique_by` com campo nullable colapsa tudo

Ver Passo 2. Sintoma: milhares de registros viram uma linha só.

### 7.3. `store_document_field` acopla busca-por-loja e pode zerar o import

Setar essa chave faz o `DiscoverIntegrationPagesJob` **iterar as lojas publicadas** do tenant, mandar o CNPJ (só dígitos) de cada uma nesse campo e marcar os registros com aquele `store_id` — que é o que alimenta o pivot `product_store`.

Consequências:
- **Sem loja publicada com `document` preenchido, `loadStores()` devolve `[]` e nada é buscado** — falha silenciosa, zero produto importado.
- Se a API **não** filtra por loja, ela devolve tudo a cada iteração: com N lojas você importa N vezes e liga todo produto a todas.
- Não dá para derivar a loja de um **campo da resposta** (ex.: `filial_codigo`): o pivot é montado a partir dos registros **já deduplicados** (`TenantRecordPersister:115`), então só sobra uma loja por produto. Fazer isso exigiria código novo.

Só use se a API realmente filtra por loja. Para "ligar tudo à única loja do tenant", funciona — foi o caso da Coasgo.

### 7.4. Credenciais não vão em migration

`TenantIntegration.config` é `encrypted:array` e varia por ambiente. Migration é só para o blueprint.

### 7.5. `token_mode: fetch` só manda usuário e senha

`IntegrationHttpClient::fetchBearerToken()` monta o corpo **apenas** com `username_field`/`password_field` e **ignora** `token_request.body` e `token_request.headers`, embora o config exponha esses campos. Se a API exigir campo extra no `/Token` (ex.: `dispositivoUID`) ou um `Content-Type` específico, é preciso alterar essa classe. O token fica em cache (`integrations.token_cache_seconds`, 300s) e é invalidado em 401.

### 7.6. Sentinelas de data passam batido

ERPs SQL Server mandam `1753-01-01` como "nunca". Não há transform para nular — vai gravado como data real. Se incomodar, precisa de transform novo.

### 7.7. Migration landlord não roda no `migrate` padrão

`php artisan migrate` só cobre `database/migrations/`. Sem `--path=database/migrations/landlord` sua migration nunca roda (e você acha que rodou).

### 7.8. Erro com HTTP 200 marca o dia como coberto

Algumas APIs respondem **HTTP 200** sinalizando a falha no corpo
(`{"response":{"status":"error","messages":[...]}}`). O motor via `$response->successful()`
== true, lia zero itens, tratava como página vazia e chamava `recordCovered()`: o dia
entrava como completo e **nunca mais era re-buscado**.

Coberto por `IntegrationResponseGuard` — mas só se o blueprint declarar `error_status_path`
no `response` (§Passo 4). Sem a chave, o comportamento antigo continua.

Foi assim que a RP Info reprovou data em ISO: `datainicial=2026-07-15` → HTTP 200 +
`"Exception: date must not be null"`, zero movimentos, nenhum sinal de erro.

### 7.9. Buscar o dia corrente pode dar erro no ERP

Alguns ERPs só materializam o movimento do dia depois do fechamento. A RP Info
responde HTTP 200 com `Não localizada tabela de movimento ... para a data:<hoje>` quando o
import roda às 06:00 — e responde normal algumas horas depois. O guard de §7.8 trata como
falha (correto: senão o dia entraria como coberto e vazio), mas o custo é retentativa com
backoff e um ERROR no log por loja, todo dia.

Solução: `lag_days: 1` no path — a janela do modo diário começa em ontem. Nada se perde,
porque `integrations.recheck_days` (3) re-busca a janela recente em toda execução.

### 7.10. Editar blueprint pela UI apaga chave desconhecida

`Form.vue` → `buildRequestsPayload()` **reconstrói `requests` do zero** a partir dos campos
do formulário. Chave que a UI não conhece some ao salvar. Ao acrescentar uma chave nova no
motor, acrescente também o campo em `Form.vue` / `IntegrationApiPathRepeater.vue` e o tipo
em `components/types.ts` — senão abrir e salvar o blueprint quebra a integração em silêncio.

---

## 8. Checklist

- [ ] Payload real salvo em `storage/app/private/<slug>/`
- [ ] Fill rate medido; campos 0% **fora** do `field_map`
- [ ] Nenhuma coluna de que outra fonte é dona sendo mapeada (dimensões!)
- [ ] `unique_by` sem campo nullable, alinhado à chave natural do reconciler
- [ ] Todos os `target` no whitelist de `config/integrations.php`
- [ ] Transforms só da whitelist do `FieldValueResolver`
- [ ] `items_path` e paths de paginação conferidos contra a resposta real
- [ ] `store_document_field` só se a API filtra por loja **e** há loja publicada com `document`
- [ ] Blueprint em migration landlord, guardada + idempotente + `down()`
- [ ] Migration rodada com `--path=database/migrations/landlord`
- [ ] `TenantIntegration` configurada pela UI, `is_active = true`
- [ ] Mapeamento validado com `RecordMapper` contra o payload real
- [ ] Chave nova do motor também exposta na UI (§7.10)
- [ ] `error_status_path` configurado se a API erra com HTTP 200 (§7.8)
- [ ] Teste escrito e rodando (um arquivo por invocação)
- [ ] `vendor/bin/pint --dirty --format agent`

---

## 9. Estudo de caso: RP Info (cursor + header token)

Blueprint `rpinfo` — `database/migrations/landlord/2026_07_21_000002_create_rpinfo_integration_api.php`.
Payloads reais e spec OpenAPI em `storage/app/private/rpinfo/` (gitignored).

| | Endpoint | Medido |
|---|---|---|
| Auth | `POST /v1.1/auth` `{usuario,senha}` → `response.token` | expira em 4 h |
| Lojas | `GET /v1.5/unidades` → `response.content[]` | CNPJ por unidade |
| Produtos | `GET /v3.2/produtounidade/listaprodutos/{lastId}/unidade/{CNPJ}/detalhado` | 20.897 itens, 209 pgs de 100 |
| Vendas | `GET /v1.9/movimentoprodutos/listarmovimentos/lastid/{lastId}?tipoconsulta=CODIGO_DCTO&valores=<cod>&unidade={CNPJ}` | 1 linha por (unidade, produto, dia) |

**Não use `/v1.3/.../listarmovimentosevp`** (o da coleção Postman): devolve linha de cupom —
7.150 linhas para 2.404 pares (unidade, produto) no mesmo dia. Como o upsert é
last-write-wins **sem agregação**, ~66% do valor vendido sumiria em silêncio. E ele não traz
custo, então `margem_contribuicao` seria impossível.

O `valores` (código do documento, ex.: 7300 = "Importação - Vendas De Produtos PDV's") é
**por cliente**: vai em `connection.params` do tenant. Descubra em
`GET /v1.0/documentos/ativo_usuario?tipo=EVP`.

Pré-requisito: as lojas do tenant publicadas com `document` = CNPJ da unidade (§7.3).

Config do tenant (UI `/tenants/{tenant}/integration`):

| Campo | Valor |
|---|---|
| URL da API | `http://<host>:8010/` |
| Tipo de autenticação | Bearer Token, **Modo do token** = Buscar token |
| **Header do token** | `token` — sem isso a API responde 401 "Não autorizado" |
| Path do token | `v1.1/auth` |
| **Campo do token na resposta** | `response.token` — **não** `token`. O default (`token`) devolve string vazia e todo request vira 401 |
| Campo usuário / senha | `usuario` / `senha` |
| Params | `tipoconsulta=CODIGO_DCTO`, `valores=<cod. do documento>` |

> O painel "Testar conexão" resolve `{cursor}`/`{store_document}` e monta a query
> igual ao import (usa o `IntegrationPayloadBuilder`), então o que ele mostra é o
> que o import vai fazer. Para vendas, testa com a janela de hoje/hoje — dia sem
> movimento importado no ERP retorna 0 registros legitimamente.
