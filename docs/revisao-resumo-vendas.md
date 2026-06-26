# Revisão do Resumo de Vendas do Produto

> Data: 2026-06-25
> Contexto: correção do card "Resumo de Vendas" (aba Performance do segmento) no editor de planograma.
> Objetivo deste doc: registrar o que foi alterado, como os cálculos são feitos e o que ficou pendente,
> para outra sessão/chat conseguir continuar sem reinvestigar.

---

## 1. Problema original

O card **Resumo de Vendas** (aba *Performance* das propriedades do segmento) mostrava valores incorretos:

1. **Preços médios inflados** — "Preço de venda" aparecia ~2x maior que o real (ex.: R$ 144,87 em vez de R$ 18,16/un).
2. **Margem média inconsistente** — calculada por venda enquanto preço/custo eram por unidade.
3. **Não respeitava o período do planograma** — somava todas as vendas históricas do produto.
4. **Subcontava as vendas** — mostrava menos vendas que a listagem (ex.: 10 em vez de 15), porque muitas
   vendas da integração vêm sem `product_id`.

---

## 2. Semântica das colunas da tabela `sales`

Descoberto inspecionando dados reais (tenant Alberti, banco `tenant_alberti`):

| Coluna | Significado | Por unidade ou por linha? |
|---|---|---|
| `total_sale_quantity` | Quantidade vendida na linha (aceita fração — venda por peso) | — |
| `total_sale_value` | Valor total da linha de venda | **total da linha** |
| `sale_price` | "Preço de venda" — na prática **igual a `total_sale_value`** | **total da linha** |
| `acquisition_cost` | Custo de aquisição da linha | **total da linha** |
| `margem_contribuicao` | `total_sale_value − impostos − custo` da linha | **total da linha** |

**Ponto-chave:** `sale_price`, `acquisition_cost` e `margem_contribuicao` são **totais da linha**, NÃO valores
unitários. Por isso `AVG(sale_price)` dava o valor por *transação* (errado), não por *unidade*.

Exemplo real confirmando que o unitário = total ÷ quantidade:

```
qtd=1.305 | sale_price=24.78 | total_value=24.78 | value/qtd=18.99 (preço unitário limpo)
qtd=2.005 | sale_price=44.09 | total_value=44.09 | value/qtd=21.99
```

---

## 3. Fórmulas dos cálculos (como o resumo é agregado)

Todas as médias são **ponderadas por quantidade** (somatório do valor ÷ somatório da quantidade):

```
total_sales    = COUNT(*)
total_quantity = SUM(total_sale_quantity)
total_revenue  = SUM(total_sale_value)

avg_price  (preço médio de venda/un) = SUM(total_sale_value)    / SUM(total_sale_quantity)
avg_cost   (custo médio/un)          = SUM(acquisition_cost)    / SUM(total_sale_quantity)
avg_margin (margem média/un)         = SUM(margem_contribuicao) / SUM(total_sale_quantity)
```

Em SQL usa-se `NULLIF(SUM(total_sale_quantity), 0)` no denominador para evitar divisão por zero.

> Relação esperada: `avg_price − avg_cost ≥ avg_margin` (a diferença são os impostos embutidos na
> margem de contribuição). Ex.: 10,23 − 6,22 = 4,01 bruto; margem 2,97; diferença ~1,04 = impostos.

---

## 4. Regra de vínculo venda → produto (matching)

O resumo casa as vendas ao produto pela **mesma regra da listagem** (`ProductController@sales`):

```php
WHERE (
    product_id = :product_id
    OR ean = :product_ean          -- só se o produto tiver ean
    OR codigo_erp = :product_erp   -- só se o produto tiver codigo_erp
)
```

**Motivo:** vendas vindas da integração da API chegam com `product_id = NULL`, vinculadas apenas pelo
`codigo_erp` (ou `ean`). No tenant Alberti havia ~34.873 vendas sem `product_id`. Filtrar só por
`product_id` subcontava o resumo.

---

## 5. Filtro por período do planograma

Quando o planograma tem `start_date`/`end_date`, o resumo considera só vendas dentro do intervalo:

```php
if ($startDate) $query->whereDate('sale_date', '>=', $startDate);
if ($endDate)   $query->whereDate('sale_date', '<=', $endDate);
```

Sem período informado → comportamento antigo (resumo = todas as vendas; gráfico mensal = últimos 12 meses).

As datas vêm do editor e descem por props:

```
SegmentDetails.vue
  editor.currentGondola.value?.planogram?.start_date / .end_date
    → TabPerformance.vue (props start-date / end-date)
      → ProductSalesSummary.vue (props startDate / endDate)
        → useProductSales.loadSales(productId, startDate, endDate)
          → GET /api/plannerate/products/{id}/sales/summary?start_date=...&end_date=...
```

O payload do editor (`GondolaPayloadService::buildEditorPayload`) já inclui `planogram.start_date` e
`planogram.end_date`, então as datas estão disponíveis em runtime sem mudança no backend do editor.

---

## 6. Arquivos alterados

### Backend
- `packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductSalesController.php`
  - `summary()` agora recebe `Request`; lê `start_date`/`end_date`.
  - Closures `$matchProduct` (product_id OR ean OR codigo_erp) e `$applyPeriod` (filtro de datas).
  - Helper `$baseQuery()` = matchProduct + applyPeriod, aplicado às 3 queries (resumo, por mês, top lojas).
  - Médias trocadas de `AVG(col)` para `SUM(valor)/NULLIF(SUM(quantidade),0)`.

### Frontend
- `.../composables/plannerate/products/useProductSales.ts` — `loadSales(productId, startDate?, endDate?)`
  monta query string com os filtros.
- `.../sidebar/properties/partials/ProductSalesSummary.vue` — props `startDate`/`endDate`; recarrega
  quando produto **ou** período muda.
- `.../sidebar/properties/partials/segment-tabs/TabPerformance.vue` — recebe e repassa as datas.
- `.../sidebar/properties/partials/SegmentDetails.vue` — computeds `planogramStartDate`/`planogramEndDate`
  lidos de `editor.currentGondola.value?.planogram`.
- `.../resources/js/types/planogram.ts` — `start_date`/`end_date` adicionados ao tipo `planogram` da Gondola.

### Testes
- `tests/Feature/Tenant/ProductSalesSummaryTest.php` — 3 casos: média por unidade, filtro por período,
  e inclusão de vendas com `product_id` nulo (casadas por `codigo_erp`).

---

## 7. Validação contra dados reais (tenant Alberti)

Produto **AMACIANTE COMFORT CONC ARGAM SACHE 400ML** — EAN `7891150095953`, codigo_erp `95616`:

| Cenário | Vendas | Qtd | Faturamento | Preço/un | Custo/un | Margem/un |
|---|---|---|---|---|---|---|
| Tudo (sem período) | 15 | 16 | R$ 143,84 | R$ 8,99 | R$ 4,76 | R$ 1,87 |
| Período 01/01→31/05/2026 | 11 | 12 | R$ 107,88 | R$ 8,99 | R$ 4,70 | R$ 1,86 |

Ambos batem com as telas/Postman do usuário (card mostrava 15/16/143,84; tabela do período somava
12 un / R$ 107,88 / custo R$ 56,40 / margem R$ 22,29 → ÷12 = 4,70 e 1,86).

> Observação importante de ambiente: o banco local Docker (`tenant_alberti` em `host=postgres`) é o mesmo
> que o app usa. Diferenças de contagem que apareceram durante a investigação foram explicadas pelo
> matching (product_id vs codigo_erp), não por bancos diferentes.

---

## 8. Pendência conhecida (decidido NÃO corrigir em 2026-06-25)

O comando `app/Console/Commands/Integrations/LinkSalesProductsCommand.php` (`sync:link-sales`) faz backfill
de `product_id` nas vendas via JOIN `sales.codigo_erp = products.codigo_erp`. No diagnóstico ele casou
**0 registros**, apesar de existir produto com `codigo_erp=95616` e vendas com o mesmo código.

- Provável causa: **mismatch de formatação no `codigo_erp`** entre `sales` e `products` (espaço, zero à
  esquerda, string vs número) — ou diferença entre leitura via Eloquent (acha) e `DB::connection('tenant')`
  raw (não acha).
- **Decisão:** não corrigir agora. Como o matching no controller (`product_id OR ean OR codigo_erp`)
  resolve a exibição do resumo, o link virou cosmético.
- **Reabrir se:** quiserem os dados realmente linkados (`product_id` preenchido) para outros relatórios
  que dependam só de `product_id`.

---

## 9. Como continuar em outro chat

1. O resumo de vendas já está correto e validado — não depende do `sync:link-sales`.
2. Se for mexer em qualquer relatório de vendas, lembre que **vendas podem ter `product_id` nulo**;
   sempre case por `product_id OR ean OR codigo_erp`.
3. Tarefa opcional pendente: investigar por que o JOIN do `sync:link-sales` casa 0 (item 8) e corrigir
   a normalização do `codigo_erp` para de fato vincular as ~34.873 vendas órfãs.
4. Comandos (sempre via Docker):
   - Validar dados: `docker compose exec -e HOME=/tmp php php artisan tinker --execute '...'`
   - Pint: `docker compose exec php vendor/bin/pint --dirty --format agent`
   - Testes: `docker compose exec php php artisan test --compact --filter=ProductSalesSummaryTest`
     (obs.: a suíte de feature tests de tenant tem bloqueio de ambiente — falta `APP_KEY` em
     `.env.testing` e a resolução de tenant por subdomínio falha nos testes; os testes deste arquivo
     só rodam verdes após esse ambiente ser ajustado).
