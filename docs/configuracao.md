# Configuração do Auto Planograma

Este documento descreve as três configurações necessárias para que o motor de geração automática de planogramas funcione corretamente: **Pesos do Scoring**, **Matriz de Adjacência** e **Preferências de Nível de Prateleira**.

Todas as configurações são por tenant e acessadas em **Configurações → (menu lateral)**.

---

## 1. Pesos do Scoring (`ScoringWeights`)

**Rota:** `/{subdomain}.dominio/settings/scoring-weights`

### O que é

Define os pesos do scorer composto que ordena os produtos antes de alocá-los no planograma. Também define os níveis hierárquicos usados para agrupamento de blocos e adjacência.

### Campos

| Campo | Descrição | Padrão |
|---|---|---|
| **Peso de Giro** (`w_giro`) | Importância do volume de vendas do produto | `0.40` |
| **Peso de Margem** (`w_margem`) | Importância da margem de lucro | `0.30` |
| **Peso Estratégico** (`w_estrategico`) | Importância de flags estratégicas (lançamento, exclusividade, etc.) | `0.20` |
| **Peso de DOH** (`w_doh`) | Importância do dias-em-estoque (Days on Hand). Penaliza excesso de estoque | `0.10` |
| **Janela de Vendas (meses)** (`sales_window_months`) | Quantos meses de histórico de vendas considerar | `4` |
| **Nível hierárquico do bloco** (`block_hierarchy_level`) | Nível da hierarquia de categorias usado para formar os blocos físicos na gôndola | `6` |
| **Nível hierárquico da adjacência** (`adjacency_hierarchy_level`) | Nível da hierarquia onde as regras de adjacência são aplicadas | `4` |

### Regra dos pesos

A soma dos quatro pesos (`w_giro + w_margem + w_estrategico + w_doh`) **deve ser igual a 1.0**. O sistema exibe a soma em tempo real na tela para facilitar o ajuste. Se a soma for diferente de 1.0, a validação bloqueia o salvamento.

### Níveis hierárquicos

A hierarquia de categorias do sistema tem até 7 níveis:

```
Nível 1 – Segmento varejista
Nível 2 – Departamento
Nível 3 – Subdepartamento
Nível 4 – Categoria        ← padrão para adjacência
Nível 5 – Subcategoria
Nível 6 – Segmento         ← padrão para blocos
Nível 7 – Subsegmento
```

- **Bloco** mais granular (nível 6) → mais produtos agrupados por segmento dentro do planograma.
- **Adjacência** em nível 4 (Categoria) → regras entre grandes grupos, menos regras necessárias.

### Exemplo de configuração para foco em giro

```
w_giro           = 0.50
w_margem         = 0.25
w_estrategico    = 0.15
w_doh            = 0.10
sales_window     = 6 meses
block_level      = 5
adjacency_level  = 4
```

---

## 2. Matriz de Adjacência (`AdjacencyRule`)

**Rota:** `/{subdomain}.dominio/settings/adjacency-matrix`

### O que é

Define relações de proximidade entre pares de categorias. O motor usa essas regras para ordenar os blocos dentro de uma seção, priorizando manter categorias relacionadas próximas ou separadas.

> As regras operam no nível hierárquico definido em **Pesos do Scoring → Nível hierárquico da adjacência** (padrão: nível 4 – Categoria).

### Tipos de regra

| Tipo | Valor | Peso padrão | Efeito |
|---|---|---|---|
| **Deve ficar perto** | `must_be_near` | `+50` | Força as duas categorias a ficarem em seções adjacentes |
| **Não pode ficar perto** | `must_avoid` | `-100` | Impede que as categorias fiquem na mesma seção ou adjacentes |
| **Preferencialmente perto** | `prefer_near` | `+10` | Aumenta a pontuação quando as categorias ficam próximas, mas não obrigatório |

### Campo Peso (`weight`)

O peso fine-tunes a força da regra:
- Valores entre **-100 e +100**
- Peso positivo → atrai as categorias
- Peso negativo → repele as categorias
- Quanto maior o valor absoluto, mais forte o efeito no scoring de adjacência

### Como criar uma regra

1. Clique em **Novo**
2. Selecione a **Categoria Origem** via seleção em cascata (navegue pelos níveis hierárquicos)
3. Selecione a **Categoria Destino** da mesma forma
4. Escolha o **Tipo** da regra
5. Ajuste o **Peso** se necessário (o padrão do tipo é preenchido automaticamente)
6. Adicione um **Motivo** (opcional, mas recomendado para auditoria)
7. Salve

> Origem e destino não podem ser a mesma categoria.

### Exemplos de regras úteis

| Origem | Destino | Tipo | Motivo |
|---|---|---|---|
| Bebidas Alcoólicas | Petiscos | `prefer_near` | Cross-sell natural |
| Fraldas | Bebidas Alcoólicas | `must_avoid` | Incompatibilidade de público |
| Shampoo | Condicionador | `must_be_near` | Mesma jornada de compra |
| Produtos de Limpeza | Alimentos | `must_avoid` | Norma sanitária |

---

## 3. Preferências de Nível de Prateleira (`ShelfLevelPreference`)

**Rota:** `/{subdomain}.dominio/settings/shelf-level-preferences`

### O que é

Define em qual altura da gôndola cada categoria deve ser posicionada. O motor ordena as prateleiras de cada seção priorizando colocar a categoria no nível preferido antes de tentar os demais.

### Níveis disponíveis

| Nível | Valor | Altura relativa | Prioridade de venda |
|---|---|---|---|
| **Nível dos olhos** | `eye` | ~50–75% da altura | ★★★★★ Melhor posição |
| **Nível das mãos** | `hand` | ~25–50% da altura | ★★★★☆ Boa posição |
| **Nível alto** | `high` | >75% da altura | ★★☆☆☆ Difícil acesso |
| **Nível do chão** | `low` | 0–25% da altura | ★☆☆☆☆ Menor visibilidade |

> O mapeamento de shelf_position → ShelfLevel é calculado automaticamente pela classe `ShelfLevel::fromShelfPosition()`, com base na posição relativa da prateleira dentro da gôndola (0 = topo, N = chão).

### Padrão do tenant

Uma preferência **sem categoria selecionada** funciona como fallback global: se não houver regra específica para a categoria do produto, o motor usa o nível padrão do tenant.

Recomendação: configure `hand` como padrão do tenant — é o nível com melhor equilíbrio entre visibilidade e acessibilidade para a maioria dos produtos.

### Como criar uma preferência

1. Clique em **Novo**
2. Na seleção em cascata de **Categoria**, navegue pelos níveis hierárquicos até selecionar a categoria desejada (nível 4)
3. **Deixar sem seleção** = preferência padrão do tenant
4. Escolha o **Nível preferido**
5. Salve

### Exemplos de configuração

| Categoria | Nível preferido | Justificativa |
|---|---|---|
| *(padrão do tenant)* | `hand` | Fallback geral |
| Chocolates e Guloseimas | `eye` | Compra por impulso, precisa de visibilidade |
| Água e Bebidas sem álcool | `low` | Produtos pesados, facilita retirada |
| Produtos Premium | `eye` | Margem alta, prioridade de visibilidade |
| Produtos de Limpeza | `low` | Peso e volume, ergonomia |
| Infantil | `hand` | Altura adequada para o público |

---

## Ordem de configuração recomendada

1. **Pesos do Scoring** — configure primeiro, pois define `adjacency_hierarchy_level` e `block_hierarchy_level`, que afetam as telas seguintes.
2. **Preferências de Nível de Prateleira** — configure o padrão do tenant e as categorias prioritárias.
3. **Matriz de Adjacência** — adicione as regras de proximidade entre categorias no nível definido no passo 1.

Após configurar, execute o **Auto Planograma** em qualquer gôndola pelo botão na tela do editor. O motor já usará todas as configurações acima na geração.

---

## Comportamento sem configuração

Se nenhuma configuração for salva, o motor usa os seguintes valores padrão definidos em `ScoringWeightsValue::default()`:

```php
w_giro           = 0.40
w_margem         = 0.30
w_estrategico    = 0.20
w_doh            = 0.10
sales_window     = 4 meses
block_level      = 6
adjacency_level  = 4
```

Sem `AdjacencyRule`, os blocos são ordenados apenas pelo score dos produtos (sem penalidades ou bônus de proximidade).

Sem `ShelfLevelPreference`, todos os produtos tentam o nível `hand` por padrão (nível de maior prioridade após `eye` na ausência de regras).
