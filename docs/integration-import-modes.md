# Integration Import Modes

## Visão Geral

O sistema de importação via integrações opera em **dois modos exclusivos**, determinados pela configuração do `pathConfig` de cada caminho da API.

---

## Modo 1 — Por Página (Page-based)

**Quando ativa:** `initial_days` não definido (ou 0) no `pathConfig`.

**Fluxo:**
```
RunIntegrationImportCommand
  └─ DiscoverIntegrationPagesJob (por path × integração)
       └─ HTTP para descobrir total de páginas
            └─ FetchIntegrationPageJob (por página × loja)
                 └─ ProcessPageResponseJob
```

**Uso:** Dados sem dependência temporal, ex: catálogo de produtos, estoque, cadastros. A API é consultada para descobrir quantas páginas existem e cada página vira um job independente.

---

## Modo 2 — Por Dia (Day-by-day)

**Quando ativa:** `initial_days > 0` **e** `last_date_column` **e** `target_table` definidos no `pathConfig`.

**Substitui** o comportamento anterior de `initial_days` (que buscava um range inteiro de datas de uma vez).

**Fluxo:**
```
RunIntegrationImportCommand
  └─ DiscoverIntegrationPagesJob (por path × integração)
       └─ Consulta banco → encontra dias faltando
            └─ FetchIntegrationPageJob (por dia × loja, page=1)
                 └─ ProcessPageResponseJob
```

### Lógica de detecção de dias faltando

1. Gera o intervalo completo de datas: `[hoje, hoje-1, ..., hoje - initial_days]` (mais recente primeiro)
2. Consulta a `target_table` no banco do tenant:
   ```sql
   SELECT DISTINCT DATE(last_date_column)
   FROM target_table
   WHERE store_id = ?
     AND last_date_column BETWEEN ? AND ?
   ```
3. **Dias faltando** = intervalo gerado − dias já existentes no banco
4. Despacha um `FetchIntegrationPageJob` por dia faltando, com `date_start = date_end = aquele dia`

### Ordenação: mais recente primeiro

A busca parte de **hoje em direção ao passado** (`initial_days` atrás). Isso garante:
- Cliente vê os dados mais recentes assim que possível
- Se o período for ampliado amanhã (`initial_days` aumentar), o sistema naturalmente vai buscar os dias mais antigos que ainda faltam — sem reprocessar os recentes já importados

### Comportamento em caso de erro

- Cada job de dia falha de forma isolada (`fail()` sem retry excessivo)
- Erro em um dia não afeta os demais
- Sem encadeamento: jobs são despachados todos de uma vez no loop do `DiscoverIntegrationPagesJob`, com delay entre eles via `fetch_delay`

### Premissa de paginação

O Modo 2 assume que os registros de **um único dia por loja cabem em uma página** (respeitando `max_page_size`). Se um dia gerar mais de uma página, use o Modo 1 com o range por data. Para a maioria das APIs de vendas com `max_page_size ≥ 1000`, isso não é problema.

---

## Chaves do `pathConfig` por modo

| Chave             | Modo 1 | Modo 2 | Descrição                                                    |
|-------------------|--------|--------|--------------------------------------------------------------|
| `initial_days`    | —      | ✅      | Quantos dias atrás cobrir. Presença ativa o Modo 2           |
| `last_date_column`| —      | ✅      | Coluna de data na `target_table` usada para detectar lacunas |
| `target_table`    | —      | ✅      | Tabela onde os registros são persistidos                     |
| `chunk_days`      | —      | ❌      | Removido do Modo 2 (substituído pelo dia único)              |
| `date_fields`     | ✅      | ✅      | Define os campos de data no payload da API                   |
| `max_page_size`   | ✅      | ✅      | Tamanho máximo de página                                     |
| `fetch_delay`     | ✅      | ✅      | Delay em segundos entre jobs despachados                     |

---

## Exemplo de `pathConfig` para cada modo

### Modo 1 — Catálogo de produtos
```json
{
  "fallback_path": "/api/v1/products",
  "target_table": "products",
  "max_page_size": 500,
  "date_fields": {
    "changed_since": "updated_at"
  }
}
```

### Modo 2 — Vendas diárias
```json
{
  "fallback_path": "/api/v1/sales",
  "target_table": "sales",
  "initial_days": 90,
  "last_date_column": "sale_date",
  "max_page_size": 1000,
  "date_fields": {
    "start": "date_start",
    "end": "date_end"
  }
}
```

---

## O que muda nos arquivos

| Arquivo                              | Mudança                                                                                     |
|--------------------------------------|---------------------------------------------------------------------------------------------|
| `RunIntegrationImportCommand.php`    | Sem mudança no dispatch — continua disparando `DiscoverIntegrationPagesJob`                 |
| `DiscoverIntegrationPagesJob.php`    | Novo método `resolveMissingDays()` e `dispatchDailyJobs()`. `discoverForStore()` bifurca por modo |
| `FetchIntegrationPageJob.php`        | Sem mudança — já aceita `date_start = date_end` e `page = 1`                               |
