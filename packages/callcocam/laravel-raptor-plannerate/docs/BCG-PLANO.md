# Plano de Implementação — Análise BCG (Matriz de Quadrantes)

> Documento de planejamento. A referência VBA original está em [`BCG.md`](./BCG.md).
> Escrito em 2026-07-11, após pesquisa de mercado e auditoria do código atual.

---

## STATUS: as 6 fases foram implementadas (2026-07-11)

61 testes verdes, build OK, Pint limpo. **Validação no browser ainda pendente.**

Divergências do plano original, decididas durante a execução:

1. **`classify_by` foi implementado de verdade** (o plano o listava como parâmetro, mas o
   primeiro corte do service agrupava sempre pela categoria folha). Sem isso, o dropdown
   seria mais um controle ligado a nada — exatamente o bug que este trabalho corrige.
   Ver `BcgAnalysisService::HIERARCHY_LEVELS` e `resolveGroups()`.
2. **O gráfico mostra um grupo por vez** (§4.6 previa um scatter único). As linhas de corte
   são POR GRUPO: plotar várias categorias num 2×2 com uma única linha colocaria produtos
   do lado errado da própria linha deles.
3. **A cor da ação foi removida.** A matiz ficou toda ocupada pelos 4 quadrantes; a ação
   (`acao_espaco`) é lida por seta + texto, senão dois significados disputariam a mesma cor.
4. **Violeta foi descartado da paleta** — ΔE 2,5 contra o azul sob protanopia no tema escuro.
   Paleta final (verde/azul/amarelo/vermelho) validada nos 6 pares, não só nos adjacentes.

---

## 1. Sumário executivo

A Análise BCG é uma **matriz de quadrantes com dois eixos configuráveis**, calculada sobre um **único período**, que classifica cada SKU da gôndola comparando-o com a **média ou mediana do seu grupo mercadológico**, e cruza o resultado com o **espaço que o produto ocupa na gôndola** para gerar a ação de planograma.

Três decisões foram travadas antes deste plano:

| Decisão | Escolha |
|---|---|
| Posição em relação à Análise de Papel | **BCG é uma análise nova (`type = 'bcg'`). O Papel fica congelado.** |
| Escopo da hierarquia | **MVP exibe sempre por produto.** "Classificar por" continua configurável (define onde o corte é calculado). |
| Eixos | **Valor de Venda, Venda em Quantidade, Margem de Contribuição** + tamanho da bolha = **share de gôndola**. |

### A fronteira conceitual entre BCG e Papel

Esta é a parte mais importante do plano, porque hoje as duas análises se confundem:

|  | **Papel** (existente, congelado) | **BCG** (nova) |
|---|---|---|
| Eixos | share × **crescimento** | duas métricas de **nível** (valor, qtde, margem) |
| Períodos | **dois** (atual + anterior) | **um** |
| Corte | mediana da categoria | média **ou** mediana do grupo (configurável) |
| Pergunta que responde | "para onde este produto está indo?" | "quanto este produto vale hoje, e o espaço que ele ocupa é justo?" |
| Consumido por | auto-planograma (`CompositeScorer`) | decisão humana de frentes/exposição |

Sem essa fronteira, as duas análises seriam redundantes. **BCG não tem eixo de crescimento e não busca período anterior** — é o que a torna uma análise diferente, e não um segundo Papel.

---

## 2. O que a pesquisa de mercado diz

A pesquisa (fan-out web + verificação adversarial) **estourou o limite de sessão antes de verificar tudo**: 44 de 101 agentes concluíram e a etapa de síntese não rodou. O status de cada achado está marcado abaixo — isso importa, porque um dos achados mais convenientes foi **refutado**.

### 2.1 Confirmado (verificação 3-0)

Fonte: [*Cogent Business & Management* (2023), matriz CMQ](https://www.tandfonline.com/doi/full/10.1080/23311975.2023.2233272) — peer-reviewed.

- **A abordagem da planilha VBA é uma variante legítima e publicada.** A matriz CMQ usa *exatamente* os mesmos eixos (margem de contribuição × quantidade vendida), plota em scatter cartesiano com uma terceira variável como **tamanho de bolha**, e corta os quadrantes **na média** dos indicadores.
- **O motivo declarado para trocar os eixos clássicos é o nosso motivo.** A BCG canônica exige participação relativa de mercado (share próprio ÷ share do maior concorrente), o que **estruturalmente exige dado externo de concorrência** — Nielsen/Circana. Preço, custo variável e unidades de PDV são dados que todo varejista já tem internamente.
- **A definição do limiar é a fraqueza estrutural reconhecida do método.** Não existe forma principiada de decidir quando um valor é "alto" ou "baixo".
- **Evidência experimental de que a matriz pode piorar a decisão** (Armstrong & Brodie, 1994, >1000 gestores): 86,8% dos que usaram a matriz BCG na análise escolheram o projeto **não-lucrativo**, contra 15,3% dos que usaram cálculo de lucro. Os próprios autores da CMQ chamam o mapa quadrante→ação de **"receita"** e exigem análise de causa-raiz antes da decisão.

> **Consequência de projeto:** a BCG entra como **ferramenta de diagnóstico visual**, nunca como decisão automática. Nenhum quadrante dispara ação sozinho.

### 2.2 Refutado (verificação 0-3)

- **NÃO é verdade que o Blue Yonder tenha quadrantes fixos "Sleepers/Winners".** A fonte era um microsite de marketing SEO (`info.blueyonder.com`), sem autor e sem data, e o "e.g." do texto marcava exemplo do copywriter — não uma feature documentada. Material de parceiro (Cantactix) descreve o que existe de fato: **"Quadrant and Spectrum highlights" configuráveis e agnósticos de métrica**. Isso valida a arquitetura de eixos configuráveis *melhor* do que a alegação original.
- **NÃO é verdade que ferramentas comerciais de planograma usem só dado interno.** O Assortment Optimization do Blue Yonder lista *Market Share* entre os KPIs, e o Spaceman é nativamente acoplado ao dado sindicalizado da NielsenIQ. Nós usamos só dado interno **por restrição de dados, não por ser a melhor prática** — e isso deve ser dito com honestidade ao usuário na UI.

### 2.3 Indícios (verificadores morreram no limite — tratar como pista, não como fato)

- **Datawiz** (BI de varejo) vende um relatório de matriz BCG com **eixos configuráveis entre métricas internas** (Sales value, Qty, Profit, Margin, Mark-up) — precedente comercial direto para a nossa escolha.
- **DotActiv** colore SKUs na gôndola por **percentil** (top/bottom 5%), com **métrica única** por vez, não com matriz 2×2.
- **Lokad**: classificação por limiar é **instável** — 25% a 50% dos itens mudam de classe ABC por trimestre; vendas de varejo têm cauda gorda (Pareto), que é a razão estatística de a **média ser puxada por outliers**; e **score contínuo supera rótulo discreto**.
- **ECR Brasil**: o "papel" é atribuído à **categoria** (destino/rotina/conveniência/sazonal), não ao SKU — construto diferente do quadrante, e anterior a ele nos 8 passos. A prática brasileira de gôndola dá a regra de ação: **alta margem → nível dos olhos; alto giro → níveis inferiores**.

---

## 3. Onde estamos hoje (auditoria do código)

Três achados que encurtam o trabalho e um bug que vamos evitar herdar.

### 3.1 A UI do BCG já existe — com os controles desligados

[`PaperParamsModal.vue`](../resources/js/components/plannerate/analysis/PaperParamsModal.vue) já tem uma UI completa de BCG:

- **Eixos X/Y configuráveis** com as 3 métricas ([:90](../resources/js/components/plannerate/analysis/PaperParamsModal.vue#L90))
- **13 combinações "Classificar por → Exibir por"**, com validador verde/vermelho e preview ([:74-88](../resources/js/components/plannerate/analysis/PaperParamsModal.vue#L74-L88))
- Uma `interface BCGCombination` e a constante `VALID_BCG_COMBINATIONS`

O formulário envia `x_axis`, `y_axis`, `classify_by` e `display_by` no POST — e o `GondolaAnalysisController::calculatePaperApi` **descarta os quatro**. O `PaperAnalysisService` é hard-coded em share × crescimento.

> Hoje o usuário escolhe "Margem × Quantidade, classificar por Departamento" e recebe, silenciosamente, "share × crescimento por categoria". **Isso é um bug de confiança, não só uma feature faltando.**

Esses controles serão **movidos** do `PaperParamsModal` para o `BcgParamsModal`, onde passam a funcionar de verdade.

### 3.2 O banco já aceita `type = 'bcg'` — não precisa de migration

A migration original de `gondola_analyses` já criou `enum('type', ['abc', 'stock', 'bcg'])`; o `'paper'` é que foi adicionado depois, reescrevendo a CHECK constraint para `('abc','stock','bcg','paper')`.

### 3.3 O Papel alimenta o auto-planograma — por isso está congelado

`CompositeScorer` converte o papel em proxy de crescimento: `leader → 1.0`, `rising → 0.7`, `anchor → 0.3`, `lagging → 0.0` (`src/AutoPlanogram/Scoring/CompositeScorer.php:142-146`), via `PlacementSettings::$bcgMap`. Alterar a semântica do Papel alteraria a geração automática de planogramas. **Não tocar.**

### 3.4 Bug a não herdar: chaves de período divergentes

| Service | modo `sales` | modo `monthly_summaries` |
|---|---|---|
| ABC | `date_from`/`date_to` | **`month_from`/`month_to`** ❌ |
| TargetStock | `date_from`/`date_to` | **`month_from`/`month_to`** ❌ |
| Paper | `date_from`/`date_to` | `start_month`/`end_month` ✅ |

O controller e o frontend **só enviam** `start_month`/`end_month`. Ou seja, **ABC e Estoque-Alvo em modo mensal ignoram o período silenciosamente** — leem chaves que nunca chegam. O BCG usará a convenção do Papel. *(Corrigir ABC/TargetStock é trabalho separado — ver §9.)*

---

## 4. Especificação do cálculo

### 4.1 Entradas

| Parâmetro | Valores | Default |
|---|---|---|
| `table_type` | `sales` \| `monthly_summaries` | `sales` |
| período | `date_from`/`date_to` ou `start_month`/`end_month` | — |
| `x_axis` | `valor` \| `quantidade` \| `margem` | `quantidade` |
| `y_axis` | idem, `!= x_axis` | `margem` |
| `classify_by` | nível da hierarquia onde o corte é calculado | `categoria` |
| `threshold_method` | `median` \| `mean` | **`median`** |

Mapa eixo → coluna somável (todas já existem em `sales` e `monthly_sales_summaries`):

```
valor      → SUM(total_sale_value)
quantidade → SUM(total_sale_quantity)
margem     → SUM(margem_contribuicao)
```

> **`x_axis == y_axis` deve ser rejeitado** — hoje a UI permite escolher a mesma métrica nos dois eixos, o que produz uma diagonal degenerada onde só existem os quadrantes alto/alto e baixo/baixo.

### 4.2 Limiar: mediana por default, média opcional

O VBA corta na **média**. Vamos usar **mediana como default**, e expor a média como opção.

**Justificativa:** vendas de varejo são cauda-gorda (Pareto) — a média é puxada por poucos SKUs líderes, e o resultado é que quase todo mundo cai abaixo dela. A mediana é robusta a isso, e é a escolha que a Análise de Papel já fez. A opção `mean` existe para reproduzir a planilha original quando o usuário quiser conferir número a número.

> Divergência deliberada do VBA — documentar no cabeçalho do service, no mesmo padrão de `AbcAnalysisService` e `PaperAnalysisService`.

### 4.3 Casos de borda

| Caso | Tratamento |
|---|---|
| **Produto sem venda no período** | Entra no resultado com `(0, 0)` e `sem_venda = true`, classificado direto no quadrante baixo/baixo — **mas fica FORA do cálculo do limiar**. |
| **Margem negativa** | Válida, entra na estatística. Marca `alerta_margem_negativa = true` (padrão do `alerta_variabilidade` do Estoque-Alvo). |
| **Grupo com 1 produto** | Valor == limiar; com `>=` inclusivo cai em alto/alto. Mesmo comportamento já documentado e testado no Papel. |
| **Grupo inteiro sem venda** | Limiar = 0; todos em baixo/baixo, sem erro de divisão. |
| **Empate exato no limiar** | `>=` inclusivo em ambos os eixos (consistente com o Papel). |

**Por que excluir os zerados do limiar:** se uma gôndola tem muitos SKUs mortos, incluí-los arrasta a mediana para baixo e faz produtos medíocres parecerem "alto valor". O limiar deve refletir o **sortimento ativo**. Isso espelha o Papel, que já exclui produtos novos (`growth_rate = null`) do cálculo da mediana.

### 4.4 O problema dos rótulos: eles não sobrevivem a eixos configuráveis

O VBA tem 4 rótulos fixos:

```
X >= médiaX  e  Y >= médiaY  →  "Alto valor – manutenção"
X >= médiaX  e  Y <  médiaY  →  "Incentivo – volume"
X <  médiaX  e  Y >= médiaY  →  "Incentivo – lucro"
senão                        →  "Baixo valor – descontinuar"
```

Esses nomes **só fazem sentido se X = quantidade e Y = margem**. Se o usuário escolher X = Valor e Y = Quantidade, "Incentivo – lucro" é um rótulo sem eixo de lucro — mentira pura. **A configurabilidade dos eixos quebra a nomenclatura fixa do VBA.**

Solução: **chave estável + rótulo derivado**.

| Chave (estável, é o que vai pro banco) | Significado |
|---|---|
| `alto_alto` | forte nos dois eixos |
| `forte_x` | forte só no eixo X |
| `forte_y` | forte só no eixo Y |
| `baixo_baixo` | fraco nos dois |

O **rótulo exibido** é composto no frontend a partir dos eixos escolhidos ("Alto em Quantidade, baixo em Margem"). E quando o preset é o canônico (X=quantidade, Y=margem), exibimos os rótulos da planilha, que o usuário reconhece:

| Chave | Preset canônico (X=qtde, Y=margem) |
|---|---|
| `alto_alto` | Alto valor — manutenção |
| `forte_x` | Incentivo — volume |
| `forte_y` | Incentivo — lucro |
| `baixo_baixo` | Baixo valor — **revisar** |

> **"Descontinuar" vira "revisar".** A pesquisa é explícita: a evidência de Armstrong & Brodie mostra que ler ação direto do quadrante degrada a decisão; os autores da CMQ chamam isso de "receita" e exigem causa-raiz (sem demanda × ruptura); e há o caso legítimo do item de baixo giro mantido por **cross-selling**. Descontinuação continua sendo prerrogativa da ABC (`retirar_do_mix`), que já tem regra própria e testada.

### 4.5 Score contínuo (responde à crítica do Lokad)

Além do quadrante discreto, cada resultado carrega:

- `x_percentil`, `y_percentil` (0–100, posição dentro do grupo)
- `is_borderline` — verdadeiro quando o valor está a menos de 10% da amplitude do grupo em relação ao limiar

Isso ataca diretamente a instabilidade documentada (25–50% dos itens trocam de classe por trimestre): o usuário **vê quem está em cima da linha** em vez de confiar cegamente no rótulo.

### 4.6 O diferencial: espaço de gôndola (o tamanho da bolha)

Nenhum BI puro (Datawiz) tem esse dado. **Nós temos o planograma.**

```
espaco_linear_cm(produto) = Σ (layers.quantity × products.width)
                            para todos os layers do produto na gôndola

share_gondola(produto) = espaco_linear_cm(produto) / Σ espaco_linear_cm(todos)
```

Confirmado no schema: `layers.quantity` são as frentes, `layers.product_id` é o SKU, e a descida é gôndola → sections → shelves → segments → layers.

O cruzamento **quadrante × share de gôndola** é o que transforma diagnóstico em ação:

| Situação | Leitura | Ação sugerida |
|---|---|---|
| `alto_alto` + **pouco** espaço | Vende bem e ganha bem, mas está espremido | **Aumentar frentes**, subir para o nível dos olhos |
| `alto_alto` + muito espaço | Está correto | Manter |
| `baixo_baixo` + **muito** espaço | **É aqui que está o dinheiro** — item fraco ocupando gôndola nobre | **Reduzir frentes** |
| `baixo_baixo` + pouco espaço + ABC classe C + `retirar_do_mix` | Convergência de sinais | Candidato a descontinuar — **nunca automático** |
| `forte_y` (alta margem, baixo giro) | Ganha bem, vende pouco | Nível dos olhos, exposição |
| `forte_x` (alto giro, baixa margem) | Puxa tráfego | Níveis inferiores, revisar custo/mix |

As regras de nível de prateleira vêm da prática ECR brasileira levantada na pesquisa (alta margem → nível dos olhos; alto giro → níveis inferiores).

---

## 5. Contrato de dados

### Request — `POST /api/editor/gondolas/{gondola}/analysis/bcg`

```jsonc
{
  "table_type": "sales",
  "date_from": "2026-01-01", "date_to": "2026-06-30",
  "start_month": "", "end_month": "",
  "x_axis": "quantidade",
  "y_axis": "margem",
  "classify_by": "categoria",
  "threshold_method": "median"
}
```

Sem `prev_*` e sem `growth_threshold` — a BCG é de **período único**.

### Resultado (por SKU)

```php
[
  'product_id', 'product_name', 'ean', 'image_url', 'category_id', 'category_name',
  'x_value', 'y_value',            // valores brutos nos eixos escolhidos
  'x_threshold', 'y_threshold',    // limiar do grupo
  'x_percentil', 'y_percentil',    // score contínuo 0-100
  'quadrant',                      // 'alto_alto'|'forte_x'|'forte_y'|'baixo_baixo'
  'is_borderline',                 // bool — em cima da linha
  'sem_venda',                     // bool — fora do cálculo do limiar
  'alerta_margem_negativa',        // bool
  'facings', 'espaco_linear_cm', 'share_gondola',  // a bolha
]
```

Persistência: `GondolaAnalysis::updateOrCreate(['gondola_id' => …, 'type' => 'bcg'], …)`, com `parameters` guardando os eixos e o método de corte (a UI precisa deles para compor os rótulos).

---

## 6. Arquivos a tocar

### Backend

| # | Arquivo | Ação |
|---|---|---|
| 1 | `src/Sales/SalesStatistics.php` | + `mean()` (já tem `median()`), + `percentile()` |
| 2 | `src/Services/Analysis/BcgAnalysisService.php` | **novo** — espelho do `PaperAnalysisService`. Método público **puro** `classifyQuadrants(Collection): Collection` (sem banco) para testar sem fixture. Consome `ProductSalesAggregateQuery::for($tableType)`. |
| 3 | `src/Services/Analysis/GondolaSpaceService.php` | **novo** — `spaceByProduct(string $gondolaId): array` → `[product_id => ['facings', 'espaco_linear_cm', 'share_gondola']]` |
| 4 | `src/Http/Controllers/GondolaAnalysisController.php` | + `calculateBcgApi()` + `buildBcgSummary()` (contagem por quadrante) |
| 5 | `src/Models/GondolaAnalysis.php` | + `getLatestBcgAnalysis()` + `toBcgFormattedArray()` |
| 6 | `routes/editor.php` | + `POST …/analysis/bcg` |
| 7 | `src/Http/Controllers/Editor/GondolaController.php` | + `'bcg' => $bcgAnalysis?->toBcgFormattedArray()` no prop `analysis` |
| — | Migration | **desnecessária** — enum já aceita `'bcg'` |

**Armadilhas confirmadas no código** (todas já cometidas e resolvidas nas análises existentes):
- `->toBase()` obrigatório no retorno da query agregada, senão `EloquentCollection::groupBy()` chama `getKey()` em `stdClass`.
- Produtos sem venda precisam ser reinjetados zerados, senão **somem da gôndola**.
- `tenant_id` obrigatório nos filtros → `InvalidArgumentException`.
- Padrão da casa: sem FormRequest, sem JSON, sem DTO — `Request::input()` cru, `Collection<array>`, `redirect()->back()`.

### Frontend

| # | Arquivo | Ação |
|---|---|---|
| 8 | `analysis/bcg/types.ts` | **novo** — `BcgQuadrant`, `BcgResult`, `BcgSummary` |
| 9 | `analysis/BcgParamsModal.vue` | **novo** — recebe os controles **movidos** do `PaperParamsModal` (eixos, classificar-por) + seletor de método de corte. **Sem** período anterior, **sem** limiar de crescimento. |
| 10 | `analysis/PaperParamsModal.vue` | **remover** os controles mortos (`x_axis`, `y_axis`, `classify_by`, `display_by`) — param a mentir |
| 11 | `analysis/BcgResultsList.vue` | **novo** — molde do `PaperResultsList` (filtro inline, não o composable `useAnalysisFilters`, que é hard-coded em A/B/C) |
| 12 | `analysis/bcg/BcgSelectionPanel.vue` | **novo** — inclui a ação sugerida (§4.6) e `ProductSalesSummary` |
| 13 | `analysis/bcg/BcgScatterChart.vue` | **novo** — scatter com linhas de corte e bolha = share de gôndola. **Carregar a skill `dataviz` antes de escrever.** |
| 14 | `header/PerformanceBcgTab.vue` | **novo** — orquestra `router.post` + `localStorage` |
| 15 | `header/Performance.vue` | 4ª aba (`TabsList` passa de `grid-cols-3` → `grid-cols-4`). **A aba `value="bcg"` já existe e renderiza o Papel — renomear para `paper` e criar a `bcg` de verdade.** |
| 16 | `DropdownPerformance.vue` | card/switch de visibilidade + export CSV + `watch` do prop |
| 17 | `composables/…/useBcgAnalysis.ts` | **novo** — `createEanAnalysisStore<BcgQuadrant>('bcg-analysis')` |
| 18 | `composables/…/usePerformanceIndicators.ts` | registrar o BCG no orquestrador |
| 19 | `editor/BcgBadge.vue` + `editor/Segment.vue` | selo na gôndola. **⚠️ Colisão:** o slot `-top-2.5 left-1/2` já é do `PaperRoleBadge` — definir posição/z-index. |
| 20 | `composables/…/useAnalysisExport.ts` | + `exportBcgToCsv()` |
| 21 | `lang/pt_BR/plannerate/{analysis,performance,dropdown}.php` | chaves `bcg_*`. **Nenhum texto hardcoded** (o `PaperRoleBadge` tem labels em PT-BR cru — não repetir o erro). |

**Wayfinder:** seguir a regra do projeto — **não** rodar `wayfinder:generate`; adicionar a action `calculateBcgApi` à mão em `resources/js/actions/…/GondolaAnalysisController.ts`.

---

## 7. Testes

Três camadas, espelhando `PaperAnalysisServiceTest`:

1. **Unit puro** (`tests/Unit/Services/Analysis/BcgAnalysisServiceTest.php`) — zero banco, via `classifyQuadrants()`. Cenários obrigatórios:
   - os 4 quadrantes com corte pela mediana
   - `threshold_method = mean` reproduz o VBA
   - produto sem venda → `baixo_baixo`, `sem_venda = true`, **fora do limiar**
   - grupo de 1 produto → alto/alto (`>=` inclusivo)
   - margem negativa entra na estatística e levanta o alerta
   - `is_borderline` acende em cima da linha
   - `x_axis == y_axis` é rejeitado
2. **Unit de fórmulas** (`tests/Unit/SalesStatisticsTest.php`) — `mean()`, `percentile()`.
3. **Integração** (`tests/Feature/Tenant/SalesAnalysisServicesTest.php`) — reusa `setupAnalysisTenant()` / `makeAnalysisProduct()`. Inclui o cálculo de espaço de gôndola. ⚠️ SQLite nos testes: `SUM`/`AVG` ok, **`STDDEV_POP` não existe** (não é usado aqui, mas o `GondolaSpaceService` não pode depender de função exótica).

---

## 8. Fases

| Fase | Entrega | Verificável por |
|---|---|---|
| **1** | `BcgAnalysisService` + `SalesStatistics` + testes unitários | `php artisan test --filter=Bcg` |
| **2** | `GondolaSpaceService` (share de gôndola) + teste | idem |
| **3** | Controller + rota + model + prop `analysis` + teste de integração | idem |
| **4** | UI: types, ParamsModal (com a limpeza do Paper), ResultsList, SelectionPanel, Tab, registro | browser |
| **5** | Scatter chart com bolha (skill `dataviz`) | browser |
| **6** | Selo na gôndola + filtro por quadrante + export CSV | browser |

Fases 1–3 são backend puro e testável sem browser. **Não avançar para a fase 4 sem os testes das fases 1–3 verdes.**

---

## 9. Fora de escopo (registrado para depois)

- **Corrigir o bug `month_from`/`month_to`** em `AbcAnalysisService` e `TargetStockService` (§3.4). É um bug real, silencioso e pré-existente — merece seu próprio commit e seus próprios testes, não um carona neste.
- **As 13 combinações de hierarquia** (exibir por Departamento/Categoria em vez de Produto). Exigem uma segunda tela de resultados: quando o resultado não é um SKU, não há selo na gôndola nem ação de frentes.
- **Eixos de eficiência (GMROI, giro).** Dependem de custo de estoque médio por SKU — confirmar se o dado existe antes de prometer.
- **Crescimento como terceiro eixo opcional.** Seria fundir BCG e Papel; só faz sentido se um dia o auto-planograma for desacoplado do `$bcgMap`.

---

## 10. Riscos

| Risco | Mitigação |
|---|---|
| Usuário lê o quadrante como ordem de descontinuar | Rótulo é "revisar", não "descontinuar". Descontinuação continua na ABC. |
| Rótulos fixos mentem quando os eixos mudam | Chave estável + rótulo derivado dos eixos (§4.4) |
| Instabilidade de classe entre períodos (25–50% ao trimestre) | `is_borderline` + percentis contínuos expõem quem está na linha |
| Colisão de selos na gôndola (Papel × BCG) | Definir posição/z-index antes de codar o `BcgBadge` |
| Mexer no Papel quebra o auto-planograma | Papel congelado; BCG é `type` novo |
