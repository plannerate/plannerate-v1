# 43 — RELATÓRIO de cobertura: `subir-nivel.md` × (modo template + modo automático)

> Auditoria somente-leitura executada conforme o prompt `43-verificacao-cobertura-template-vs-automatico.md`.
> **Data:** 2026-05-27. **Branch:** `feature/auto-planogram`.
> Toda marcação tem evidência em `arquivo:linha` (código, não Resumo).

---

## 1. Resumo executivo

- **Modo template: 20/20 ✅** — é a implementação de referência. Todas as 20 etapas estão presentes e exercidas.
- **Modo automático: ~13/20 ✅, 6 ⚠️ parciais, 0 ❌** — o automático **sintetiza um subtemplate e reusa o mesmo `TemplatePlacementEngine`**, então herda quase tudo. As lacunas concentram-se na **etapa de síntese** (`AutoTemplateSynthesizer`/`SlotPlanBuilder`): ela deixa em branco os campos estratégicos finos (prioridade de zona por métrica, sentido de fluxo, critérios visuais customizáveis, ordem preço/tamanho/embalagem, exposição por marca, limites de participação por categoria). O engine sabe executá-los — só não recebe os valores.
- **Lacuna mais crítica:** **Etapa 4 (estratégia por zona)** e **Etapa 11 (fluxo de leitura)** — o automático posiciona categorias por zona (via papel) mas **não reordena produtos dentro do slot por margem/giro** (`hot/cold_zone_priority` nascem `null`) nem espelha o fluxo (`flow_direction` nasce `null` → sempre L→R).

> **Arquitetura é compartilhada:** o automático chama `generateWithTemplate()` ([AutoPlanogramService.php:124](app/Services/AutoPlanogram/AutoPlanogramService.php#L124)), que aplica `withProductRules`, `withZoneMetrics` e `withSlotOverrides` igual ao template ([AutoPlanogramService.php:239-249](app/Services/AutoPlanogram/AutoPlanogramService.php#L239-L249)). Por isso obrigatórios/bloqueados, ABC, estoque alvo e relatório de explicação **funcionam nos dois modos sem esforço extra**.

---

## 2. Matriz das 20 etapas

Legenda: ✅ completo · ⚠️ parcial · ❌ ausente · 🟦 melhor que a spec

| # | Etapa | Template | Automático | Evidência |
|---|---|---|---|---|
| 1 | Papel da categoria | ✅ | ✅ 🟦 | Template: `role_override` no slot. Auto: `CategoryRoleInferrer.infer()` infere de venda e **respeita `categories.role` manual** ([CategoryRoleInferrer.php:37-44](app/Services/AutoPlanogram/Synthesis/CategoryRoleInferrer.php#L37-L44)); papel grava em `role_override` do slot sintetizado ([AutoTemplateSynthesizer.php:172](app/Services/AutoPlanogram/Synthesis/AutoTemplateSynthesizer.php#L172)) |
| 2 | ABC / sortimento | ✅ | ✅ 🟦 | `CompositeScorer` (log-transform) + `abcClassMap`; `scoreOrNeutral()` roda antes de ambos os modos ([AutoPlanogramService.php:65](app/Services/AutoPlanogram/AutoPlanogramService.php#L65)) |
| 3 | Estoque alvo | ✅ | ✅ | `targetStockMap` no engine ([TemplatePlacementEngine.php:44](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L44),1154); `useTargetStock` propagado ao slot sintetizado ([SlotPlanBuilder.php:137](app/Services/AutoPlanogram/Synthesis/SlotPlanBuilder.php#L137)) |
| 4 | **Estratégia por zona** | ✅ | ⚠️ | Engine lê `hot/cold_zone_priority` ([:117-118](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L117-L118)) e ordena por margem/giro ([:804-851](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L804-L851)). **Auto não define essas colunas** no subtemplate ([AutoTemplateSynthesizer.php:150-156](app/Services/AutoPlanogram/Synthesis/AutoTemplateSynthesizer.php#L150-L156)) → `None` → `applyZoneOrdering` é no-op ([:810-812](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L810-L812)). Compensação parcial: slots são ordenados hot-first e papéis quentes vão para zona quente ([SlotPlanBuilder.php:152-160,178-187](app/Services/AutoPlanogram/Synthesis/SlotPlanBuilder.php#L152-L160)) |
| 5 | Frente mínima | ✅ | ✅ 🟦 | `max($slot->min_facings,1)` ([:893](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L893)). Auto deriva piso por classe ABC (`deriveMinFacings`, A/B/C) ([SlotPlanBuilder.php:644-649](app/Services/AutoPlanogram/Synthesis/SlotPlanBuilder.php#L644-L649)) |
| 6 | Frente máx → expansão + limites participação | ✅ | ⚠️ | `expandFacings` + `violatesParticipationLimit` nos dois ([:1025,1077](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L1025)). Auto copia `maxSharePer*` do `$settings` global ([SlotPlanBuilder.php:138-140](app/Services/AutoPlanogram/Synthesis/SlotPlanBuilder.php#L138-L140)) — **não há ajuste por categoria** e o default costuma ser `null` (limite desligado) |
| 7 | Falta de espaço (corte) | ✅ | ✅ | `SpaceFallback`; auto default `reduce_c` ([AutoTemplateSynthesizer.php:177](app/Services/AutoPlanogram/Synthesis/AutoTemplateSynthesizer.php#L177)); `reduce_c` ativa com `abcClassMap` ([:209](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L209)) |
| 8 | Sobra de espaço (expansão) | ✅ | ✅ | `FacingExpansion` propagado ([SlotPlanBuilder.php:136](app/Services/AutoPlanogram/Synthesis/SlotPlanBuilder.php#L136)); expansão prioriza A>B>C via `score_abc desc` |
| 9 | Motor visual dinâmico (arrastável) | ✅ | ⚠️ | Cascade `applyCriteriaCascade` roda nos dois ([:708](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L708)). **Auto fixa** `visual_criteria=[score_abc desc, margem desc]` ([SlotPlanBuilder.php:657-663](app/Services/AutoPlanogram/Synthesis/SlotPlanBuilder.php#L657-L663)) — sem UI arrastável na síntese (editável só depois, no slot já materializado) |
| 10 | Tipo de exposição (vert/horiz/comb) | ✅ | ⚠️ | `brand_exposure`/`flavor_exposure` no engine legado ([:694](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L694)). Auto **não** define esses campos no slot e usa o caminho `visual_criteria` (legado não dispara) → sem blocagem vertical por marca por padrão |
| 11 | **Fluxo de leitura (←/→)** | ✅ | ⚠️ | Espelhamento RTL no engine ([:982](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L982)). **Auto não define `flow_direction`** → `null` → default `LeftToRight` ([:119](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L119)). Fluxo sempre L→R no automático |
| 12 | Ordem preço/tamanho/embalagem | ✅ | ⚠️ | `applySingleCriterion` suporta `preco/tamanho/embalagem` ([:730-763](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L730-L763)). **Auto não inclui** esses critérios na cascade sintetizada (só ABC+margem) |
| 13 | Reordenar (só visual) | ✅ | ✅ | `VisualReorderService` opera sobre o subtemplate da gôndola — sintetizado existe para auto |
| 14 | Redistribuir (estrutura) | ✅ | ✅ | `ExposureRedistributeService` idem |
| 15 | Regerar (regra comercial) | ✅ | ✅ | `AlterationClassifier` + repipeline; auto re-sintetiza idempotente por `source_gondola_id` ([AutoTemplateSynthesizer.php:83-84](app/Services/AutoPlanogram/Synthesis/AutoTemplateSynthesizer.php#L83-L84)) |
| 16 | Obrigatórios (entra sempre) | ✅ | ✅ | `loadProductRules` + `withProductRules` em `generateWithTemplate` — **vale para os dois modos** ([AutoPlanogramService.php:236,243-248](app/Services/AutoPlanogram/AutoPlanogramService.php#L236)); topo via `mandatoryProductIds` ([:664-665](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L664-L665)) |
| 17 | Bloqueados (nunca entra) | ✅ | ✅ | `partitionBlocked`/`isProductBlocked` ([:575-590](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L575-L590)) — caminho compartilhado |
| 18 | Produtos sem histórico | ✅ | ✅ 🟦 | `scoreOrNeutral` → score 0.5 neutro ([AutoPlanogramService.php:65](app/Services/AutoPlanogram/AutoPlanogramService.php#L65)); não descarta produto novo |
| 19 | Relatório de explicação | ✅ | ✅ | `buildExplanationReport` no engine ([:1302](app/Services/AutoPlanogram/Placement/TemplatePlacementEngine.php#L1302)); controller expõe `explanation_report` para **template E auto** (`$effectiveTemplateId = $templateId ?? $synthTemplateId`) ([AutoPlanogramController.php:108-119](app/Http/Controllers/AutoPlanogramController.php#L108-L119)) |
| 20 | Ajustes dinâmicos (UI) | ✅ | ✅ | `PanelLeftGeneration.vue` + rotas `reorder-all`/`redistribute-all`; auto usa `PlanogramAuto.vue` quando `generation_mode != manual` |

---

## 3. Lacunas do automático (priorizadas)

Todas têm a **mesma raiz**: a síntese (`AutoTemplateSynthesizer`/`SlotPlanBuilder`) não popula campos que o engine já sabe consumir.

```
[ALTA] Etapa 4 (zona) — auto ⚠️: AutoTemplateSynthesizer.createSlots não grava hot_zone_priority/
   cold_zone_priority no subtemplate (AutoTemplateSynthesizer.php:150-156). Resultado: engine lê None
   (TemplatePlacementEngine.php:117-118) e applyZoneOrdering vira no-op — produtos NÃO são reordenados
   por margem/giro dentro da zona quente/fria.
   Resolver: na síntese, inferir hot_zone_priority=MaiorMargem (ou MaiorGiro) e cold=Complementar a
   partir do papel/objetivo, e persistir no PlanogramSubtemplate::create/update. Esforço: baixo-médio.

[ALTA] Etapa 11 (fluxo) — auto ⚠️: flow_direction nunca é definido na síntese → default LeftToRight
   (TemplatePlacementEngine.php:119). O automático ignora o fluxo da loja.
   Resolver: propagar flow_direction (do settings/gondola) ao PlanogramSubtemplate sintetizado.
   Esforço: baixo.

[MÉDIA] Etapa 12 (ordem preço/tamanho/embalagem) — auto ⚠️: buildVisualCriteria fixa apenas
   [score_abc, margem] (SlotPlanBuilder.php:657-663). Preço/tamanho/embalagem existem no engine
   (applySingleCriterion) mas nunca entram na cascade do automático.
   Resolver: permitir que a config de geração automática injete critérios secundários após o ABC.
   Esforço: médio.

[MÉDIA] Etapa 9 (critérios arrastáveis) — auto ⚠️: cascade roda, mas a lista é fixa e não há UI de
   reordenação no momento da síntese (o usuário só edita depois, no slot materializado).
   Resolver: expor os critérios sintetizados como editáveis no painel de geração antes/depois.
   Esforço: médio (sobreposição com Etapa 12).

[MÉDIA] Etapa 10 (exposição vert/horiz) — auto ⚠️: brand_exposure/flavor_exposure não são definidos
   nos slots sintetizados; como visual_criteria != null, o caminho legado (que faria blocagem vertical
   por marca) não dispara (TemplatePlacementEngine.php:656-658). Blocagem por marca só se 'marca'
   estiver na cascade.
   Resolver: adicionar critério 'marca' à cascade sintetizada quando a exposição desejada for vertical,
   OU persistir brand_exposure e respeitá-lo dentro da cascade. Esforço: médio.

[BAIXA] Etapa 6 (limites de participação) — auto ⚠️: maxSharePer* vêm do $settings global e não são
   ajustáveis por categoria; default null = limite desligado (SlotPlanBuilder.php:138-140). Idêntico ao
   template quando não configurado — só falta o ajuste fino por categoria no automático.
   Resolver: derivar limites por papel (ex.: impulso/zona quente recebe teto por SKU) na síntese.
   Esforço: baixo.
```

---

## 4. Lacunas do template

Nenhuma lacuna estrutural. O modo template cobre as 20 etapas. Achados menores:

```
[MÉDIA] Etapa 9 (ABC como critério primário fixo) — template ⚠️: a spec exige que score_abc
   NUNCA saia da primeira posição na cascade visual ("é a espinha estratégica"). Mas o
   VisualCriteriaEditor.vue não impõe essa restrição — o usuário pode arrastar score_abc
   para baixo de outros critérios (todos os chips são igualmente arrastáveis, linhas 230-240
   VisualCriteriaEditor.vue). O backend (TemplateSlotService:37-41) tampouco valida posição.
   No modo automático o piso é garantido por código (buildVisualCriteria sempre coloca ABC[0]).
   Resolver: travar o chip score_abc como não-arrastável (posição 0 fixa) no editor Vue,
   e/ou validar no TemplateSlotService que visual_criteria[0].key === 'score_abc'. Esforço: baixo.

[BAIXO] Etapa 7 (política de corte) — template ⚠️: existe reduce_c/reduce_facings/skip, mas a
   spec sugere cortes por menor margem/menor venda. Hoje o corte fino baseia-se em ordem ABC.
   Refinamento opcional — não é bloqueante.
```

**Nota:** após geração automática, `gondola.generation_mode` é promovido para `'template'` e
`gondola.template_id` recebe o ID do subtemplate sintetizado ([AutoGenerationRunner.php:135-137](app/Services/AutoPlanogram/AutoGenerationRunner.php#L135-L137)).
Isso significa que reorder/redistribute (Etapas 13-14, 20) funcionam para gôndolas auto após a
primeira geração — `resolveGondolaSlots` encontra o subtemplate pelo `template_id`.

---

## 5. Melhor que a spec (🟦) — confirmados + novos

- **Auto-inferência de papel da categoria** (`CategoryRoleInferrer`) — a spec só prevê papel configurado; o sistema infere de venda/margem e ainda respeita override manual. **Novo vs. comparativo original.**
- **Síntese de template + reuso do engine** — o automático não tem motor próprio: ele constrói um subtemplate e roda o mesmo `TemplatePlacementEngine`. A spec trata os modos como separados; aqui há uma só fonte de verdade. **Arquitetura superior.**
- **Demanda real por largura (`ceil(totalWidth/shelfWidth)`) + overflow-routing** ([SlotPlanBuilder.php:474-479,561-604](app/Services/AutoPlanogram/Synthesis/SlotPlanBuilder.php#L474-L479)) — distribuição de prateleiras por demanda física, não coberta pela spec.
- **Micro-categorias compartilham prateleira** ([SlotPlanBuilder.php:317-356](app/Services/AutoPlanogram/Synthesis/SlotPlanBuilder.php#L317-L356)) — adensamento que a spec não menciona.
- **min_facings por classe ABC** (A=3/B=2/C=1 conforme largura) em vez de fixo — refinamento sobre a Etapa 5.
- **Poda de slots vazios pós-geração** ([AutoPlanogramService.php:147-162](app/Services/AutoPlanogram/AutoPlanogramService.php#L147-L162)) — o template sintetizado reflete só o que foi usado.
- **Confirmados do comparativo original:** scoring log-transform, `category_id` recursivo, score neutro 0.5, dedup global, `SlotSuggestionGenerator`, `RejectedProductsWriter` cruzando colocados, overrides por gôndola.

---

## 6. Recomendação

Priorizada por valor/esforço. **Todas tocam só a camada de síntese** — o engine não muda, risco baixo.

1. **[ALTA · esforço baixo] Persistir `hot_zone_priority`/`cold_zone_priority` e `flow_direction` no subtemplate sintetizado.** Resolve as Etapas 4 e 11 de uma vez. Derivar das settings de geração ou de um default sensato (hot=MaiorMargem, cold=Complementar, flow do stepper). Editar `AutoTemplateSynthesizer::findOrReplaceSubtemplate`/`create` para gravar as 3 colunas.
2. **[MÉDIA · esforço médio] Enriquecer `buildVisualCriteria` com critérios secundários configuráveis** (preço/tamanho/embalagem/marca) após o ABC fixo. Resolve Etapas 9, 10 e 12 juntas. Manter ABC sempre em 1º.
3. **[BAIXA · esforço baixo] Derivar limites de participação por papel** na síntese (Etapa 6) — opcional.
4. **[opcional] Modo de corte por margem/venda** no `SpaceFallback` (Etapa 7) — vale para os dois modos.

> Não implementado nesta auditoria (somente leitura). Cada ação acima deve sair com teste, seguindo
> o padrão de `app/Services/AutoPlanogram` (cobertura existente em `tests/`).

---

### Nota de método

- Verificado contra **código**, não contra o `Resumo sessao plannerate.md`. O Resumo descreve os campos `hot_zone_priority`/`flow_direction` como implementados — e **estão**, no engine e no schema; a lacuna é que a **síntese do automático não os preenche**. Não há contradição, só um detalhe que o Resumo não destacava.
- Não foi gerado planograma real nem rodada migration. Inspeção estática + leitura dos serviços de síntese, engine, DTOs e controller.
