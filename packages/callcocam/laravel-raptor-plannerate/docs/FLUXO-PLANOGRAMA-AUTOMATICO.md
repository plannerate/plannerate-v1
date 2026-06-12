# Fluxo da Geração Automática de Planogramas

> Atualizado em 2026-06-12 para refletir a implementação atual.
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

**Análise de Papel (papéis estratégicos por produto):** o `PaperAnalysisService` cruza market share × crescimento para classificar cada produto em `leader`, `anchor`, `rising` ou `lagging`. Regras (corrigidas em 2026-06-12):

- O limiar de "alto crescimento" é **relativo**: mediana dos crescimentos da categoria (antes era fixo em 0, o que degenerava a matriz — anchor/lagging nunca apareciam em períodos de alta generalizada). `setGrowthThreshold()` permite fixar um limiar manual;
- **Produto novo** (sem venda no período anterior) recebe `is_new = true`, `growth_rate = null` e papel `rising` (item em introdução) — antes ganhava +100% de crescimento artificial;
- Produto sem venda nos dois períodos é `lagging` (candidato à revisão de mix);
- O papel alimenta o componente `growth` do CompositeScorer e a regra `space_fallback = remove_dog`.

> No modo automático, o `CategoryRoleInferrer` também calcula o papel estratégico de cada **categoria** (não produto individual) para orientar a síntese do template.

---

## 6. Quinto passo — Análise de sortimento (ABC)

O `AbcAnalysisService` classifica os produtos em curvas A, B e C por média ponderada de:

- Quantidade vendida (peso padrão: 30%)
- Valor vendido (peso padrão: 30%)
- Margem de contribuição (peso padrão: 40%)

Cortes configuráveis: `abcCutoffA` (padrão 80%) e `abcCutoffB` (padrão 90%).

**Regra de classificação (corrigida em 2026-06-12):** a classe é definida pelo percentual acumulado **antes** de somar o item. Consequências práticas:

- O primeiro produto do ranking da categoria é **sempre A** — inclusive em categorias com um único produto (antes, o acumulado saltava para 100% e o único produto virava C);
- Um produto que cruza um corte ainda pertence à classe anterior (ex.: produto com 85% de share individual é A, não B);
- Categorias inteiras sem venda continuam todas C (não há evidência para promover ninguém).

A classificação ABC é usada para:

- Pré-ordenar o pool (A > B > C > sem ABC)
- Influenciar critérios de ordenação visual (`score_abc`)
- Aplicar `space_fallback = RemoveCurvC` quando falta espaço
- Marcar `retirar_do_mix` (classe C com participação menor que metade do menor B)

O sistema pode **excluir curva C do pool** antes do placement quando a flag `exclude_class_c` estiver ativa, com **presença mínima por subcategoria** (padrão de mercado — cobertura de mix):

1. Produtos marcados como `retirar_do_mix` saem sempre (recomendação explícita do ABC);
2. Demais produtos C saem, **exceto** o de maior venda de cada subcategoria que ficaria vazia — nenhuma subcategoria ativa some da gôndola por causa do corte;
3. Produtos sem classificação ABC (sem vendas no período) não são afetados.

> Fonte dos dados: tabela `product_analyses` (cache, quando `useExistingAnalysis = true`) ou cálculo on-the-fly direto de `sales` ou `monthly_sales_summaries`.

---

## 7. Sexto passo — Calcular o estoque alvo

O `TargetStockService` calcula `estoque_alvo` e `estoque_seguranca` por produto com base na classificação ABC:

| Classe | Cobertura padrão | Nível de serviço padrão |
|---|---|---|
| A | 2 dias | 70% |
| B | 5 dias | 80% |
| C | 7 dias | 90% |

Os defaults (regra da planilha VBA original — `docs/ESTOQUE-ALVO.md`) são configuráveis em `config/plannerate.php` → `auto_planogram.target_stock` (`service_levels` e `coverage_days` por classe). Instalações que preferem o padrão de mercado (classe A com nível de serviço mais alto, ex.: 95%) podem sobrescrever ali sem alterar código; os setters do service seguem funcionando como override pontual.

O estoque alvo define quantas frentes o produto precisa para cobrir a demanda sem ruptura.

A flag `use_target_stock` no slot do template (ou em `planogram_gondola_slot_overrides`) controla se o estoque alvo influencia o cálculo de frentes durante o placement.

---

## 8. Sétimo passo — Aplicar frente mínima e ajustar espaço

A frente mínima garante presença visual suficiente para cada produto alocado.

**Se faltar espaço**, o sistema aplica a regra `space_fallback` configurada no slot (enum `SpaceFallback`):

- `reduce_facings` — tenta recolocar os rejeitados com 1 frente no espaço restante
- `reduce_c` — produtos curva C vão para o fim da fila e são rejeitados primeiro
- `remove_dog` — produtos retardatários (papel `lagging` da Análise de Papel) são rejeitados primeiro
- `skip` — deixa incompleto, sem nova tentativa

**Se sobrar espaço**, o sistema aplica a regra `facing_expansion` (enum `FacingExpansion`):

- `none` — não expande
- `score` — expande na ordem de score (maior relevância primeiro)
- `current_stock` — expande produtos com maior estoque atual
- `target_stock` — expande produtos com maior déficit de estoque alvo
- `equal` — expande em round-robin igualitário

Essas regras podem ser sobrescritas por categoria em `planogram_gondola_slot_overrides`, permitindo comportamentos diferentes por categoria dentro da mesma gôndola.

**Overflow pass**: após processar todos os slots, produtos definitivamente rejeitados por falta de espaço horizontal são realocados (com frentes mínimas por curva ABC) em qualquer prateleira da gôndola que ainda tenha espaço — respeitando o vão livre de altura. No modo automático, slots que ficaram sem produtos são removidos do subtemplate sintetizado após a geração.

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

### Blocagem vertical real (`layout_orientation`) — 2026-06-12

O campo `layout_orientation` do **subtemplate** (`horizontal` | `vertical`, null = horizontal legado) ativa a blocagem vertical de verdade — diferente do `brand_exposure = vertical`, que apenas agrupa a sequência dentro de cada prateleira:

- **Como funciona**: quando uma categoria ocupa 2+ prateleiras consecutivas do mesmo módulo, cada marca recebe uma **coluna de largura proporcional à sua demanda** (piso = produto mais largo da marca), preenchida de cima para baixo — mesma faixa de X em todas as prateleiras, formando o bloco visual alinhado;
- **Chão fora da blocagem**: com 3+ prateleiras, o slot do chão (shelf_order 1) segue o caminho horizontal (fardos/embalagens econômicas);
- **Prateleiras compartilhadas** (micro-categorias adensadas) nunca entram em modo vertical;
- **Expansão de frentes por célula** (marca × prateleira): nunca invade a coluna vizinha;
- **Sobras** viram `NoHorizontalSpace` e são recolocadas pelo overflow pass;
- **Fluxo RTL** espelha as colunas inteiras mantendo o alinhamento;
- Onde escolher: select "Disposição" no stepper/modal de geração (modo automático, persiste no subtemplate sintetizado) e no `ModuleDefaultsModal` (modo template). Alterar a disposição exige **regerar** o planograma.
- Engine: `TemplatePlacementEngine::buildVerticalGroups()` + `placeVerticalGroup()`.

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

Cada critério aceita direção `asc`, `desc` ou `none`. O critério `embalagem` usa uma lista ordenada customizada (`packaging_order`) em vez de direção — tipos não listados vão para o fim.

A ordenação é centralizada no `ProductOrderingService` — usado tanto pela geração (`TemplatePlacementEngine`) quanto pelos ajustes pós-geração (Reordenar/Redistribuir), garantindo resultado idêntico nos dois caminhos.

**Exemplo de hierarquia:**

```
marca (asc) → embalagem (asc) → tamanho (desc) → preco (asc)
```

O sistema aplica do critério menos prioritário ao mais prioritário, garantindo que o primeiro critério seja o dominante.

---

## 12. Décimo primeiro passo — Respeitar o fluxo de leitura

A ordenação visual respeita o sentido de leitura configurado no **subtemplate** (`flow_direction`):

- `left_to_right` — início da exposição na esquerda
- `right_to_left` — início da exposição na direita

No modo automático, o fluxo escolhido no stepper/modal de geração é gravado no subtemplate sintetizado. Em templates pré-configurados, o fluxo é definido nos defaults do módulo (editor de template) — o campo `flow` da gôndola não é lido pelo engine.

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
