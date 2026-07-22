# Migrar leitores de `current_stock` / `last_purchase_date` para `product_store`

> Plano para **outra sessão**. A parte de importação já está feita e em produção local;
> o que falta é trocar os **leitores**.

## Context

`products.current_stock` e `products.last_purchase_date` vêm do feed do ERP, onde as duas
são **por unidade**: estoque é da loja, e a última compra é daquela filial. O id do produto
é derivado de `tenant + ean` — **sem loja** —, então, num tenant com mais de uma loja, as
cadeias de importação gravam na mesma linha de `products` e vence a última a terminar.

Medido na integração RP Info (Supermercado Maringá, 2 unidades): **67 de 100 produtos da
primeira página têm estoque diferente** entre Matriz e Filial. Exemplos reais:

```
Batata Doce Grl Kg          Matriz  42,698    Filial  19,405
Tomate Longa Vida Grl Kg    Matriz 122,709    Filial 162,831
Pimentao Verde Grl Kg       Matriz  38,304    Filial   9,347
```

Com um tenant de uma loja só o problema era invisível. Com duas, o número em `products`
passou a ser não-determinístico.

### O que já foi feito (não refazer)

- `product_store` ganhou `current_stock` (double) e `last_purchase_date` (date) —
  [2026_07_21_000100_add_store_scoped_metrics_to_product_store_table.php](database/migrations/2026_07_21_000100_add_store_scoped_metrics_to_product_store_table.php)
- O motor de importação ganhou duas chaves de blueprint:
  - `paths.<x>.pivot_only_targets` — alvos mapeados que o
    [TenantUpsertRecordPreparer](app/Services/Integrations/TenantUpsertRecordPreparer.php) remove **antes** do upsert da tabela principal
  - `pivot_tables[].update_columns` — colunas que o upsert da pivot atualiza (sem isso só
    `updated_at` era tocado e o valor congelava no primeiro import)
- O blueprint `rpinfo` já usa as duas —
  [2026_07_21_000003_move_rpinfo_store_metrics_to_pivot.php](database/migrations/landlord/2026_07_21_000003_move_rpinfo_store_metrics_to_pivot.php)
- Coberto por [tests/Feature/Integrations/StoreScopedPivotMetricsTest.php](tests/Feature/Integrations/StoreScopedPivotMetricsTest.php) (6 testes)

**Resultado hoje:** a importação da RP Info escreve as métricas em `product_store` e **não
escreve mais** em `products`. As colunas de `products` continuam existindo com o **último
valor gravado antes da mudança** — dado congelado e de uma loja só. É isso que este plano
precisa resolver.

## O que fazer

### 1. A loja está sempre no contexto

`planograms.store_id` existe e `gondolas.planogram_id` → `planograms`. Todo fluxo de
planograma/gôndola/análise sabe de qual loja está falando — é o que torna a troca possível
sem inventar parâmetro novo. Confirme o caminho em cada chamador antes de assumir.

### 2. Leitores a migrar

Ordem sugerida: os que decidem layout primeiro (impacto real no planograma), depois os de
exibição.

**Decidem layout / análise — prioridade alta**

| Arquivo | Uso |
|---|---|
| [TemplatePlacementEngine.php:1663](packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Placement/TemplatePlacementEngine.php#L1663) | `FacingExpansion::CurrentStock` — expande facings pelo estoque |
| [TemplatePlacementEngine.php:1666,2519,2538](packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Placement/TemplatePlacementEngine.php#L1666) | déficit vs. estoque alvo e desempate de ordenação |
| [TargetStockService.php:116](packages/callcocam/laravel-raptor-plannerate/src/Services/Analysis/TargetStockService.php#L116) | carrega `current_stock` por EAN direto de `products` |
| [SlotReviewAnalysisService.php:58,483](packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Template/SlotReviewAnalysisService.php#L58) | seleção de colunas + ordenação por estoque |
| [AbcAnalysisService.php:898-922](packages/callcocam/laravel-raptor-plannerate/src/Services/Analysis/AbcAnalysisService.php#L898) | ruptura/ABC usa as **duas** colunas |
| [GondolaAnalysisController.php:412](packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaAnalysisController.php#L412) | `total_current_stock` agregado |

> `AbcAnalysisService` tem um comentário dizendo que `last_purchase_date` "ainda NÃO é
> populada pela importação". Isso mudou: agora é populada, mas em `product_store`.
> Atualize o comentário junto com o código.

**Exibição — prioridade menor**

| Arquivo | Uso |
|---|---|
| [ProductController.php:238-239](app/Http/Controllers/Tenant/ProductController.php#L238) e `:536` | payload da listagem e do detalhe |
| [GondolaPrintService.php:287](packages/callcocam/laravel-raptor-plannerate/src/Services/Export/GondolaPrintService.php#L287) | PDF |
| [products/Index.vue:119](resources/js/pages/tenant/products/Index.vue#L119) | badge "Estoque N" |
| `TabPerformance.vue`, `ProductSalesSummary.vue`, `ShelfDetails.vue`, `indicators.ts` | painéis do editor |

**Não mexer:** as opções de `facing_expansion` com valor `'current_stock'`
(`AutomaticGenerateModal`, `SlotEditorFields`, `CategoryConfigPanel`, `ModuleDefaultsModal`,
`SlotCard`, `FacingsSettingsSection`, `FacingExpansion.php`, `TemplateSlotService.php`).
São o **nome da estratégia**, não leitura da coluna.

### 3. Como ler

Prefira uma única fonte reutilizável em vez de espalhar joins. Sugestão: um método no
`Product` (`->stockForStore($storeId)`) ou um serviço que carregue o mapa
`ean|product_id => current_stock` de `product_store` para a loja em questão — é a forma que
o `TargetStockService` já usa (pluck por EAN), então o encaixe é direto.

Cuidado com N+1: os motores de placement iteram milhares de produtos. Carregue o mapa uma
vez por loja, não por produto.

**Fallback:** enquanto houver tenant de uma loja só cuja importação ainda não passou pelo
novo blueprint (sysmo, gescooper), `product_store.current_stock` estará nulo. Decida
conscientemente entre (a) cair para `products.current_stock` quando o pivot for nulo, ou
(b) migrar esses blueprints também (é só acrescentar `pivot_only_targets` +
`update_columns`, mesmo padrão do rpinfo). **A opção (b) é a mais limpa** e evita carregar
o fallback para sempre.

### 4. Corte final

Depois que todos os leitores estiverem migrados:

1. Zerar `products.current_stock` e `products.last_purchase_date` — hoje guardam valor
   congelado de uma loja, e deixá-los preenchidos é convite a alguém reintroduzir a leitura
   errada.
2. **Não** dropar as colunas na mesma leva: `BuildsProductRules.php:55` permite editar
   `last_purchase_date` manualmente pela UI. Decida antes se esse campo manual continua
   existindo (e onde) — pode ser que precise virar edição por loja.
3. Tirar `current_stock`/`last_purchase_date` do whitelist `products` em
   [config/integrations.php](config/integrations.php) para que nenhum blueprint novo volte a
   mapeá-los na tabela principal.

## Verificação

- **Teste do motor** já existe e deve continuar verde:
  `docker compose exec php php artisan test --compact tests/Feature/Integrations/StoreScopedPivotMetricsTest.php`
- **Teste novo por leitor migrado**: monte duas lojas com estoques diferentes para o mesmo
  produto e assere que cada uma enxerga o seu. É exatamente o caso que hoje quebra.
- **Dado real para conferir**: tenant `RPInfo` (`01ky3c72cc412acxz8zt7tt0ds`), lojas
  `00073351000122` (Matriz) e `00073351000203` (Filial), ~20,9 mil produtos com estoque
  divergente em ~2/3 dos casos.
- `docker compose exec php vendor/bin/pint --dirty --format agent`
- Um arquivo de teste por invocação em `tests/Feature/Integrations` (o `migrate:fresh` do
  `beforeEach` cascateia falhas nos arquivos seguintes).

## Armadilhas

- **`HasSlug` renomeia em todo `save()`.** `IntegrationApi` regenera o slug a partir do
  `name` inclusive em update — um `$api->save()` numa migration renomeia o blueprint.
  Grave via query base (`->toBase()->update(...)`) quando mexer em blueprint.
- **Chave nova no blueprint precisa aparecer na UI.** `Form.vue` reconstrói `requests` do
  zero ao salvar; chave desconhecida é apagada. `pivot_only_targets` e `update_columns` já
  foram expostos — siga o mesmo caminho para qualquer chave nova.
- **`migrate:fresh` é proibido** neste projeto, em qualquer conexão. Para inspecionar o
  banco use query de leitura.
