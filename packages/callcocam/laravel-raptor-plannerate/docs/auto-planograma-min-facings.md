# Auto-Planograma — Frentes Mínimas por ABC, Overflow Pass e Pruning de Slots

## Contexto

O pipeline de geração automática (`AutoPlanogramService`) sintetiza um template de slots e depois posiciona os produtos nas prateleiras via `TemplatePlacementEngine`. Cada slot do template carrega um valor `min_facings` que define quantas frentes mínimas cada produto precisa ocupar na prateleira.

---

## Mudança: `ABC_MIN_FACINGS` → todos = 1

### Antes

```php
// SlotPlanBuilder.php
public const ABC_MIN_FACINGS = ['A' => 3, 'B' => 2, 'C' => 1, '' => 2];
```

Produtos classe A exigiam 3 frentes antes de entrar na gôndola. Isso causava dois problemas:

1. **Espalhamento excessivo**: Com `min_facings=3` e `largura_produto=10cm`, cada produto ocupava 30cm. Em uma prateleira de 96cm cabiam apenas 3 produtos distintos, espalhando o sortimento pelas prateleiras em vez de concentrar 2–3 produtos por prateleira.
2. **Rejeições falsas**: Produtos rejeitados por falta de espaço horizontal quando o espaço real estava disponível em outros slots — especialmente porque categorias menores (ex.: DE MILHO, 3 produtos) deixavam slots vazios enquanto categorias maiores (ex.: FAROFA, 22 produtos) rejeitavam produtos por falta de espaço nos seus próprios slots.

### Depois

```php
// SlotPlanBuilder.php
public const ABC_MIN_FACINGS = ['A' => 1, 'B' => 1, 'C' => 1, '' => 1];
```

Todos os produtos começam com **1 frente mínima**. A densidade da gôndola aumenta: mais produtos distintos por prateleira. A prioridade A→B→C é preservada na **Phase 2 de expansão de frentes** (ver abaixo).

---

## Como a prioridade A → B → C é mantida

A expansão de frentes extra (Phase 2 do `TemplatePlacementEngine::distributeInShelf`) usa o critério `FacingExpansion::Score` com `visual_criteria: [score_abc desc, margem desc]`.

Isso garante que, ao distribuir o espaço sobrante da prateleira, produtos **classe A** recebam frentes adicionais primeiro, depois B, depois C — sem precisar bloquear espaço com `min_facings` alto.

```
Phase 1: posiciona todos os produtos com 1 frente (min_facings=1)
Phase 2: espaço restante → expande frentes dos produtos já posicionados
         ordenados por: score_abc desc → A primeiro, depois B, depois C
```

---

## Remoção do bloco `maxUseful` em `partitionIntoBlocks`

Para evitar que slots extras fossem dados a categorias que "não conseguiriam preenchê-los", foi adicionado (em sessão anterior) um bloco de controle baseado em `ABC_MIN_FACINGS['A']`:

```php
// REMOVIDO — incompatível com ABC_MIN_FACINGS['A'] = 1
$maxFacingFactor = (float) self::ABC_MIN_FACINGS['A'];
$effectiveOverflowWeights = $overflowWeights;

foreach ($subcatSlotsNeeded as $i => $demanded) {
    $totalW = $withDemand->values()[$i]['summary']->totalWidth;
    $maxUseful = (int) ceil($totalW * $maxFacingFactor / max($shelfWidth, 1.0));
    if ($maxUseful <= $demanded) {
        $effectiveOverflowWeights[$i] = 0.0;
    }
}
$slotCounts = $this->distributeWithExtraToOverflow(..., $effectiveOverflowWeights, ...);
```

**Por que remover?** Com `ABC_MIN_FACINGS['A'] = 1`, a fórmula `maxUseful = ceil(totalWidth × 1 / shelfWidth)` retorna exatamente o mesmo valor que `demanded` (calculado por `computePerSubcatSlots`). Isso zerava o peso de overflow de **todas** as categorias, fazendo os slots extras irem para a maior categoria via round-robin e piorando o espalhamento.

**Substituído por:**

```php
// O overflow natural (fmod(totalWidth, shelfWidth)) já é o critério correto:
// overflow > 0 → última prateleira parcialmente cheia → pode absorver um slot extra.
// overflow = 0 → última prateleira 100% cheia → slot extra ficaria vazio.
$slotCounts = $this->distributeWithExtraToOverflow($subcatSlotsNeeded, $overflowWeights, $totalUsed);
```

---

## Overflow Pass no `TemplatePlacementEngine`

A overflow pass foi adicionada ao final do loop principal de posicionamento para resolver o seguinte cenário:

> Categorias menores (poucas SKUs, ex.: DE MILHO) recebem slots inteiros que ficam com espaço sobrante. Ao mesmo tempo, categorias maiores (ex.: FAROFA) rejeitam produtos porque os seus próprios slots estão cheios.

### Como funciona

```php
// Após o loop principal de slots:
[$placed, $rejected] = $this->placeOverflow($placed, $rejected, $sections);
```

1. Identifica produtos **definitivamente rejeitados** por `NoHorizontalSpace` que ainda não foram posicionados em nenhum slot.
2. Calcula o espaço **restante por prateleira** subtraindo o que já foi ocupado pelos produtos posicionados.
3. Ordena prateleiras por maior espaço disponível (prioriza as mais vazias).
4. Posiciona os produtos em ordem **A → B → C** nas prateleiras com espaço suficiente.
5. Usa `ABC_MIN_FACINGS[abcClass]` para calcular a largura mínima necessária (agora = 1 frente).

### Verificação de largura mínima

```php
$abcClass = $this->abcClassMap[$product->id] ?? '';
$minFacings = SlotPlanBuilder::ABC_MIN_FACINGS[$abcClass] ?? SlotPlanBuilder::ABC_MIN_FACINGS[''];
$widthWithFacings = (int) round($singleWidth * $minFacings);

if ($meta['remaining'] < $widthWithFacings) {
    continue; // não cabe nem com 1 frente → não posicionar
}
```

Produto que não cabe nem com 1 frente (ex.: largura de produto > largura da seção) permanece rejeitado e é registrado em `planogram_rejected_products`.

---

## Pruning de Slots Vazios (pós-geração)

### Problema

O `SlotPlanBuilder` alocava mais slots que o necessário via overflow-routing e o mínimo de `MIN_SHELVES_PER_MODULE = 4` por módulo. Após o engine rodar, muitos `planogram_template_slots` ficavam **sem candidatos** — a categoria estava atribuída àquele slot, mas todos os produtos da categoria já tinham sido colocados em slots anteriores (via `globalPlacedProductIds` que impede duplicatas).

**Exemplo real (DE MANDIOCA + DE MILHO, 4 módulos × 4 prateleiras = 16 slots):**

```
"slots_processados": 16
"slots_com_produto":  6   ← 6 slots efetivamente usados
"slots_sem_matching": 10  ← 10 slots sem candidato (vazios)
```

Esses 10 slots eram entradas desnecessárias na tabela `planogram_template_slots`.

### Solução: `pruneEmptySlots()` em `AutoPlanogramService`

Após o `TemplatePlacementEngine::place()` retornar, os IDs dos slots sem candidatos são coletados em `emptySlotIds` e propagados pelo pipeline até `AutoPlanogramService::generate()`, que os deleta da tabela:

```php
// AutoPlanogramService::generate() — modo automático apenas
$output = $this->generateWithTemplate($updatedInput, $scored, $scoreType);
$this->pruneEmptySlots($output->emptySlotIds);
return $output;
```

```php
private function pruneEmptySlots(array $emptySlotIds): void
{
    if (empty($emptySlotIds)) {
        return;
    }

    $deleted = PlanogramTemplateSlot::withoutGlobalScope(TenantScope::class)
        ->whereIn('id', $emptySlotIds)
        ->delete();

    if ($deleted > 0) {
        Log::info('AutoPlanogramService: slots vazios removidos do subtemplate após geração', [
            'slots_removidos' => $deleted,
        ]);
    }
}
```

### Rastreamento no engine

`TemplatePlacementEngine::place()` popula `$emptySlotIds` quando `$candidates->isEmpty()`:

```php
if ($candidates->isEmpty()) {
    $groupingsSemProduto++;
    $emptySlotIds[] = $slot->id;   // ← rastreia o ID do slot vazio
    Log::debug('TemplatePlacementEngine: sem produto para slot', [...]);
    continue;
}
```

O campo `emptySlotIds: list<string>` foi adicionado a `PlacementResult` e propagado até `PlanogramOutput`.

### O que é deletado / o que permanece

| Elemento | Comportamento |
|---|---|
| `planogram_template_slots` sem candidatos | **Deletados** (soft-delete) |
| Prateleiras físicas (`shelves`) | **Preservadas** — estrutura da gôndola intacta |
| Seções físicas (`sections`) | **Preservadas** — igual ao número do formulário |
| Segmentos e layers | **Preservados** — apenas os com produto |

### Modo template vs. automático

`pruneEmptySlots` **só é chamado no modo automático** (dentro de `generate()`). No modo template, o usuário define manualmente quais categorias vão em cada slot — slots vazios podem ser intencionais.

---

## Limitação do `ProductWidthResolver`

O `ProductWidthResolver` tem `MAX_PLAUSIBLE_WIDTH = 60.0 cm`. Qualquer produto cadastrado com largura acima desse threshold recebe o fallback de `DEFAULT_WIDTH_CM = 10.0 cm` e um warning no log.

Isso significa que produtos com `width > 60cm` **sempre cabem** em qualquer prateleira do sistema (com 1 frente de 10cm). Para testar cenários de rejeição por largura, é necessário usar produtos com largura entre 1cm e 60cm, ajustando a largura da seção conforme necessário.

---

## Testes afetados e atualizações

### `SlotPlanBuilderTest`

| Teste | Comportamento anterior | Comportamento novo |
|---|---|---|
| `min_facings maior para curva A do que para curva C` | `facingsA > facingsC` | `facingsA == facingsC == 1` |
| `leaf: min_facings deriva da classe ABC dominante` | `facings > 1` (dominante A) | `facings == 1` |

### `AutomaticEndToEndTest`

| Teste | Mudança |
|---|---|
| Teste 3 — min_facings por classe | Verifica `toBe(1)` para todos os slots, independente da classe ABC |
| Teste 8 — produto Wide rejeitado | Fixture ajustada: seção com `width=50cm`, produto Wide com `width=55cm` (< 60cm threshold, > 50cm shelf) |

### `AutomaticReroutesToTemplateTest`

| Teste | Mudança |
|---|---|
| `abcClassMap injetado faz subcategoria A ter min_facings maior que C` | Renomeado; verifica `toBe(1)` para ambos |

### `AutomaticCreatesShelvesTest`

| Teste | Comportamento anterior | Comportamento novo |
|---|---|---|
| `preserva todas as seções` | Verifica 8 shelves (4 × 2 módulos) | Verifica 8 shelves **+** template slots < 9 |
| `usa exatamente os módulos do formulário` | Verifica 12 shelves (4 × 3 módulos) | Verifica 12 shelves **+** exatamente 5 template slots (1/subcategoria) |
| `1 subcategoria e 1 módulo` | Verifica 4 shelves | Verifica 4 shelves **+** exatamente 1 template slot |
| `regeração` | Verifica 4 shelves pós-regeneração | Verifica 4 shelves **+** exatamente 1 template slot |

---

## Resumo do impacto esperado

| Métrica | Antes (min_facings A=3) | Depois (min_facings=1 + expandFacings + pruning) |
|---|---|---|
| Produtos distintos por prateleira | ~3 (produto 10cm × 3 frentes = 30cm, 3 cabem em 96cm) | ~9 (produto 10cm × 1 frente, 9 cabem em 96cm) |
| Espaço por produto na Phase 1 | `largura × min_facings_abc` | `largura × 1` |
| Prioridade A→B→C | No espaço mínimo garantido | Na expansão de frentes (Phase 2) |
| Produtos rejeitados por falta de espaço | Maior (min alto comprime capacidade) | Menor (overflow pass + min=1 maximiza densidade) |
| Produtos overflow colocados | N/A | Sim — realocados nas prateleiras com espaço sobrante |
| Template slots após geração | N slots criados (muitos vazios) | Apenas slots com candidatos (slots vazios soft-deleted) |
| Prateleiras físicas | N prateleiras (muitas sem produto) | N prateleiras preservadas (estrutura intacta) |
