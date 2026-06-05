# Fluxo da Geração Automática de Planogramas

> Atualizado em 2026-06-05 para refletir a implementação atual.
> Versão anterior era conceitual; este documento descreve o que está em produção.

---

## 1. Objetivo

Permitir que o sistema gere automaticamente um planograma a partir de uma estrutura física (gôndola) e de dados de vendas e sortimento, aplicando uma sequência de cálculos obrigatórios para posicionar os produtos de forma estratégica.

O sistema opera em dois modos:

**Modo Template** — existe um template pré-configurado para a combinação de estrutura mercadológica + quantidade de módulos. O template define as regras de cada slot: categoria, zonas, frentes, ordenação e tipo de exposição.

**Modo Automático** — não existe template pré-configurado. O sistema sintetiza um template dinamicamente a partir do mix de produtos e do espaço físico disponível, e então aplica o mesmo pipeline de geração.

---

## 2. Primeiro passo — Criar a base do planograma

O usuário cria a gôndola pelo stepper do editor, definindo em 6 etapas:

- Nome, localização, lado do corredor e fluxo de leitura
- Quantidade de módulos, altura, largura e profundidade
- Configurações de base e cremalheira
- Quantidade de prateleiras e tipo de produto padrão
- Modo de geração: **manual**, **template** ou **automático**
- Configurações de workflow (responsável, data de início)

Quando o modo é **automático**, a geração é disparada imediatamente após a criação da gôndola, no mesmo request.

Quando o modo é **template**, o editor abre com o modal de geração já ativo para o usuário confirmar os parâmetros.

---

## 3. Segundo passo — Buscar ou sintetizar o template

### Modo Template

O sistema busca o `PlanogramSubtemplate` onde:

```
template_id = gondola.template_id
num_modules <= número de seções da gôndola
```

O subtemplate com maior `num_modules` dentro desse limite é selecionado. Cada slot do subtemplate define:

- Categoria vinculada
- Zona térmica (quente ou fria)
- Frentes mínimas e máximas
- Tipo de exposição (vertical/horizontal por marca ou sabor)
- Critérios de ordenação visual
- Regra de falta de espaço (`space_fallback`)
- Regra de sobra de espaço (`facing_expansion`)
- Uso de estoque alvo

### Modo Automático

O sistema sintetiza um template do zero:

1. **`CategoryRoleInferrer`** — infere o papel estratégico de cada subcategoria com base no mix de produtos pontuados: `StarCategory`, `QuickWin`, `BudgetItem`, `NicheItem`
2. **`SlotPlanBuilder`** — distribui as categorias pelos módulos e prateleiras disponíveis, criando o plano de slots
3. **`AutoTemplateSynthesizer`** — persiste o template sintetizado como `PlanogramSubtemplate`

O template sintetizado entra no mesmo engine de placement do modo template.

---

## 4. Terceiro passo — Selecionar e validar produtos

Antes de gerar, o sistema filtra o pool de candidatos. Um produto só participa se atender **todos** os critérios:

| Critério | Como é verificado |
|---|---|
| Produto ativo (não draft) | `products.status != 'draft'` |
| Pertence à estrutura mercadológica | CTE recursiva de categorias descendentes |
| Pertence ao sortimento da loja ou cluster | `product_store` (loja direta) ou `clusters.store_id` (herança do cluster) |
| Tem dimensões cadastradas | `width > 0` e `height > 0` (modo automático); engine rejeita com `MissingDimensions` (modo template) |
| Não está bloqueado | `planogram_product_rules` com `type = blocked` (por produto, marca ou subcategoria) |
| Cabe fisicamente | Engine rejeita com `HeightExceedsShelf` ou `NoHorizontalSpace` durante o placement |

Produtos obrigatórios (`planogram_product_rules` com `type = mandatory`) entram mesmo com score baixo.

> Planogramas sem loja ou cluster definidos não aplicam o filtro de sortimento — compatibilidade com planogramas legados.

---

## 5. Quarto passo — Calcular o score de relevância

O sistema calcula um score numérico (0.0–1.0) para cada produto usando o `CompositeScorer`:

| Componente | Descrição |
|---|---|
| `giro_norm` | Volume de vendas normalizado (log-transform para evitar distorção por outliers) |
| `margem_norm` | Margem de contribuição normalizada pelo min-max do pool |
| `doh_norm` | Days of hand — cobertura de estoque disponível |
| `strategic` | 1.0 se o produto estiver na lista de estratégicos (`product_strategic_flags`), 0.0 caso contrário |

Os pesos de cada componente são configuráveis por tenant em `ScoringWeights`.

O score ordena o pool — produtos com maior score têm prioridade na alocação de slots. Quando não há dados de venda no período, o sistema aplica um **score neutro** (0.5) para todos os produtos, garantindo que o template ainda possa distribuir produtos.

> No modo automático, o `CategoryRoleInferrer` também calcula o papel estratégico de cada **categoria** (não produto individual) para orientar a síntese do template.

---

## 6. Quinto passo — Análise de sortimento (ABC)

O `AbcAnalysisService` classifica os produtos em curvas A, B e C por média ponderada de:

- Quantidade vendida (peso padrão: 30%)
- Valor vendido (peso padrão: 30%)
- Margem de contribuição (peso padrão: 40%)

Cortes configuráveis: `abcCutoffA` (padrão 80%) e `abcCutoffB` (padrão 90%).

A classificação ABC é usada para:

- Pré-ordenar o pool (A > B > C > sem ABC)
- Influenciar critérios de ordenação visual (`score_abc`)
- Aplicar `space_fallback = RemoveCurvC` quando falta espaço

O sistema pode **excluir curva C do pool** antes do placement quando a flag `exclude_class_c` estiver ativa. Produtos sem classificação ABC (sem vendas no período) não são afetados por essa flag.

> Fonte dos dados: tabela `product_analyses` (cache, quando `useExistingAnalysis = true`) ou cálculo on-the-fly direto de `sales` ou `monthly_sales_summaries`.

---

## 7. Sexto passo — Calcular o estoque alvo

O `TargetStockService` calcula `estoque_alvo` e `estoque_seguranca` por produto com base na classificação ABC:

| Classe | Cobertura padrão | Nível de serviço padrão |
|---|---|---|
| A | 2 dias | 70% |
| B | 5 dias | 80% |
| C | 7 dias | 90% |

O estoque alvo define quantas frentes o produto precisa para cobrir a demanda sem ruptura.

A flag `use_target_stock` no slot do template (ou em `planogram_gondola_slot_overrides`) controla se o estoque alvo influencia o cálculo de frentes durante o placement.

---

## 8. Sétimo passo — Aplicar frente mínima e ajustar espaço

A frente mínima garante presença visual suficiente para cada produto alocado.

**Se faltar espaço**, o sistema aplica a regra `space_fallback` configurada no slot:

- `ReduceFacings` — reduz frentes até o mínimo
- `RemoveLowestPriority` — remove produtos de menor score
- `RemoveDeadWeight` — remove produtos sem vendas
- `RemoveCurvC` — remove curva C primeiro
- `PreserveMandatory` — nunca remove produtos obrigatórios

**Se sobrar espaço**, o sistema aplica a regra `facing_expansion`:

- `NoExpansion` — não expande
- `ExpandHighPriority` — expande produtos de maior score
- `ExpandHighStock` — expande produtos com maior estoque alvo
- `ExpandHighMargin` — expande produtos de maior margem
- `ExpandHighSales` — expande produtos de maior venda

Essas regras podem ser sobrescritas por categoria em `planogram_gondola_slot_overrides`, permitindo comportamentos diferentes por categoria dentro da mesma gôndola.

---

## 9. Oitavo passo — Aplicar estratégia por zona

O `ShelfZoneResolver` mapeia cada prateleira em uma zona térmica com base em sua posição física:

- **Zona quente** (Eye + Hand): prateleiras centrais — melhor acesso visual e físico
- **Zona fria** (High + Low): prateleiras no topo e no chão

A priorização por zona é configurada no template:

- Zona quente: `maior_margem`, `maior_giro`, `maior_valor_vendido`, `curva_a`
- Zona fria: `menor_margem`, `complementar_fria`, `maior_volume`, `menor_prioridade`

A estratégia por zona é **preferencial, não absoluta**: orienta o posicionamento, mas não quebra regras físicas (categoria, dimensão, frente mínima).

---

## 10. Nono passo — Aplicar o tipo de exposição

O tipo de exposição define o padrão visual de agrupamento:

- **Vertical por marca**: produtos da mesma marca formam uma coluna entre prateleiras
- **Vertical por sabor**: variantes de sabor formam colunas
- **Horizontal**: produtos se distribuem lateralmente na prateleira
- **Combinada**: slots diferentes usam regras diferentes (vertical em um módulo, horizontal em outro)

Configurado por slot no template via `brand_exposure` e `flavor_exposure`.

---

## 11. Décimo passo — Aplicar a ordenação visual

A ordenação visual define a sequência dos produtos dentro de cada slot.

O sistema aplica os critérios em cascata (do menos para o mais prioritário), usando `visual_criteria` configurado no slot do template.

**Critérios disponíveis:**

| Critério | Campo no produto | Descrição |
|---|---|---|
| `marca` | `brand` | Agrupa por marca |
| `tipo` | `type` | Agrupa por tipo de produto |
| `embalagem` | `packaging_type` | Ordena por tipo de embalagem (PET, lata, vidro…) |
| `tamanho` | `width` / `height` / `depth` | Ordena por volume calculado |
| `preco` | `price` | Ordena por preço de venda |
| `sabor` | `flavor` | Agrupa por sabor |
| `atributo` | `sortiment_attribute` | Ordena por atributo de sortimento |
| `score_abc` | ABC calculado | Ordena por curva A > B > C |
| `margem` | Métricas de venda | Ordena por margem de contribuição |

Cada critério aceita direção `asc`, `desc` ou `none`.

**Exemplo de hierarquia:**

```
marca (asc) → embalagem (asc) → tamanho (desc) → preco (asc)
```

O sistema aplica do critério menos prioritário ao mais prioritário, garantindo que o primeiro critério seja o dominante.

---

## 12. Décimo primeiro passo — Respeitar o fluxo de leitura

A ordenação visual respeita o sentido de leitura da gôndola:

- `left_to_right` — início da exposição na esquerda
- `right_to_left` — início da exposição na direita

O fluxo define onde começa e onde termina a leitura. Quando o fluxo é `right_to_left`, o engine espelha as posições físicas dos segmentos nas prateleiras.

---

## 13. Décimo segundo passo — Validação pós-placement

Antes de gravar, o `PlanogramValidator` executa 7 regras de integridade:

| Regra | O que verifica |
|---|---|
| `FacingMinimumRule` | Todo produto alocado respeita `min_facings` |
| `SectionCapacityRule` | Nenhuma seção ultrapassa 100% da largura |
| `EmptyShelfRule` | Não há prateleiras completamente vazias |
| `AdjacencyRule` | Categorias incompatíveis não ficam adjacentes |
| `BlockIntegrityRule` | Blocos verticais íntegros entre prateleiras |
| `ShelfLevelRule` | Produtos pesados não estão em prateleiras altas |
| `UnplacedProductsRule` | Taxa de produtos não alocados dentro do limite aceitável |

O resultado da validação é retornado no `PlanogramOutput` junto com o planograma gerado.

---

## 14. Décimo terceiro passo — Gerar e gravar o planograma final

O `PlanogramWriter` persiste os resultados em transação. O `PlanogramOutput` contém:

- `placedSegments` — produtos alocados com posição, largura e camadas
- `rejectedProducts` — produtos rejeitados com motivo explícito (`PlacementFailureReason`)
- `slotAnalysis` — análise de ocupação por slot
- `suggestions` — sugestões automáticas de ajuste
- `validationReport` — resultado das 7 regras de validação
- `explanationReport` — explicação textual das decisões do engine
- `modulesMismatch` — flag quando o template tem mais módulos que a gôndola física

---

## 15. Ajustes após a geração

Depois do planograma gerado, o usuário pode fazer ajustes no editor. O `AlterationClassifier` detecta automaticamente o tipo de alteração necessária:

**Reordenar** — muda apenas a sequência visual dos produtos.
- Exemplos: inverter ordem de marcas, mudar preço de menor para maior.
- O sistema mantém os mesmos produtos e frentes, apenas reorganiza.
- Serviço: `VisualReorderService`

**Redistribuir** — muda o tipo de exposição ou agrupamento.
- Exemplos: mudar de vertical para horizontal, trocar agrupamento principal.
- O sistema tenta manter os mesmos produtos e frentes, mas redistribui as posições.
- Serviço: `ExposureRedistributeService`

**Regerar** — muda uma regra de decisão.
- Exemplos: mudar parâmetros dos cálculos, mudar estratégia, mudar frente mínima, mudar estrutura mercadológica.
- O sistema recalcula a geração do zero com os novos parâmetros.
- Serviço: `AutoGenerationRunner::run()`

---

## 16. Resumo do fluxo

```
1.  Criar a base da gôndola (stepper)
2.  Buscar template OU sintetizar automaticamente
3.  Filtrar produtos elegíveis (sortimento, categoria, dimensões, bloqueios)
4.  Calcular score de relevância por produto
5.  Classificar por curva ABC (+ opção de excluir curva C)
6.  Calcular estoque alvo por produto
7.  Aplicar frente mínima e resolver falta/sobra de espaço
8.  Aplicar estratégia por zona (quente/fria)
9.  Aplicar tipo de exposição (vertical/horizontal/combinada)
10. Aplicar ordenação visual hierárquica
11. Respeitar o fluxo de leitura
12. Validar integridade pós-placement (7 regras)
13. Gravar planograma final
14. Permitir ajustes no editor (reordenar / redistribuir / regerar)
```

---

## 17. Síntese conceitual

O **template** define a estrutura da exposição — distribuição de categorias, zonas e regras por slot.

O **score** define a relevância estratégica de cada produto — combina giro, margem, cobertura de estoque e flag estratégico.

A **análise ABC** define a composição do planograma — o que é prioritário, intermediário ou candidato a saída.

O **estoque alvo** define quanto espaço cada produto precisa para cobrir a demanda.

As **regras de falta e sobra** ajustam a ocupação real ao espaço disponível.

A **estratégia por zona** define onde cada produto deve ficar dentro da gôndola.

O **tipo de exposição** define se a leitura será vertical, horizontal ou combinada.

A **ordenação visual** define a sequência dos produtos dentro de cada slot.

O **fluxo de leitura** define o sentido da exposição.

O resultado é um planograma gerado automaticamente com base em estratégia, dados de venda, sortimento da loja e regras configuradas — com validação de integridade antes de gravar.
