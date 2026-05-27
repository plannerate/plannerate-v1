# Recomendações — Auto Planogram (pós-auditoria `subir-nivel.md`)

> Derivadas da auditoria `43-RELATORIO-cobertura-template-vs-automatico.md` (2026-05-27).
> Ordenadas por impacto/esforço. Todas tocam somente a camada de síntese ou o editor Vue
> — o `TemplatePlacementEngine` não precisa mudar.

---

## ALTA prioridade

### R1 — Persistir `hot_zone_priority`, `cold_zone_priority` e `flow_direction` no subtemplate sintetizado

**Etapas resolvidas:** 4 (estratégia por zona) + 11 (fluxo de leitura)
**Esforço:** baixo (~1-2h)
**Risco:** zero — campos nullable no schema, engine já os consome

O que fazer:
- Em `AutoTemplateSynthesizer::findOrReplaceSubtemplate` (e no bloco `create`), persistir as três
  colunas no `PlanogramSubtemplate`.
- Valores sugeridos como padrão inteligente:
  - `hot_zone_priority` → `ZonePriority::MaiorMargem` (produtos de maior margem ocupam zona quente)
  - `cold_zone_priority` → `ZonePriority::ComplementarFria` (complementares e curva C na zona fria)
  - `flow_direction` → propagar da config de geração (`AutoGenerateConfigDTO`) ou da gôndola
- Expor `flow_direction` como opção no formulário de geração automática (stepper / modal de geração).
- Adicionar 2–3 testes em `FlowDirectionTest` ou novo `AutoSynthesisZoneFlowTest`.

Código a alterar:
- `app/Services/AutoPlanogram/Synthesis/AutoTemplateSynthesizer.php` (linhas 135–155 e 150–156)
- `app/Services/AutoPlanogram/DTO/AutoGenerateConfigDTO.php` (adicionar campo `flowDirection`)
- `resources/js/...AutoGenerateModal.vue` (ou equivalente no stepper)

---

## MÉDIA prioridade

### R2 — Travar ABC na primeira posição do editor visual (template)

**Etapas resolvidas:** 9 (critério ABC sempre primeiro, spec linhas 53–66)
**Esforço:** baixo (<1h)
**Risco:** zero — só UX

O que fazer:
- Em `VisualCriteriaEditor.vue`, marcar o chip `score_abc` como não-arrastável (sem `draggable`
  quando é o primeiro e é `score_abc`), ou impedir drop antes dele.
- Em `TemplateSlotService::validateSlot`, adicionar regra: se `visual_criteria` presente,
  `visual_criteria[0].key` deve ser `score_abc`.
- Atualizar schema Zod em `validation.ts` com `.refine()` equivalente.

Código a alterar:
- `resources/js/components/planogram-templates/VisualCriteriaEditor.vue`
- `app/Services/AutoPlanogram/Template/TemplateSlotService.php` (linha 37)
- `resources/js/components/planogram-templates/validation.ts`

---

### R3 — Enriquecer `buildVisualCriteria` com critérios secundários configuráveis

**Etapas resolvidas:** 9 (arrastáveis no auto), 10 (exposição por marca), 12 (preço/tamanho/embalagem)
**Esforço:** médio (~3-4h)
**Risco:** baixo — `buildVisualCriteria` é local à síntese, engine já suporta todos os critérios

O que fazer:
- Adicionar ao `AutoGenerateConfigDTO` um campo `secondaryCriteria: list<{key, direction}>` opcional.
- Em `SlotPlanBuilder::buildVisualCriteria`, aceitar critérios secundários como parâmetro:
  `[score_abc desc] + secondaryCriteria` (ABC sempre no topo, não substituível).
- Expor no formulário de geração: pelo menos preço e tamanho como toggles simples.
- Para exposição vertical por marca: adicionar `['key' => 'marca', 'direction' => 'asc']` como
  secundário padrão quando o papel da categoria for `Destino` ou `Impulso`.

Código a alterar:
- `app/Services/AutoPlanogram/DTO/AutoGenerateConfigDTO.php`
- `app/Services/AutoPlanogram/Synthesis/SlotPlanBuilder.php` (método `buildVisualCriteria`)
- Modal/form de geração automática no frontend

---

### R4 — Derivar limites de participação por papel da categoria

**Etapas resolvidas:** 6 (limites relativos de participação)
**Esforço:** baixo (~1h)
**Risco:** baixo — campos nullable no slot, limite null = desligado (comportamento atual preservado)

O que fazer:
- Em `SlotPlanBuilder::partitionIntoBlocks`, ao construir `SlotPlanEntry`, derivar limites por papel:
  - `Destino` → `max_share_per_sku: 40` (nenhum SKU domina mais de 40% da prateleira)
  - `Impulso` → `max_share_per_sku: 35`
  - Outros papéis → `null` (sem limite, comportamento atual)
- Valores como constantes nomeadas, prontos para virar config por tenant.

Código a alterar:
- `app/Services/AutoPlanogram/Synthesis/SlotPlanBuilder.php`
- Opcional: `AutoGenerateConfigDTO` para tornar os valores configuráveis

---

## BAIXA prioridade

### R5 — Modo de corte por margem/venda no SpaceFallback

**Etapas resolvidas:** 7 (política de falta de espaço mais rica)
**Esforço:** médio-alto (~4-6h — envolve engine)
**Risco:** médio — toca `TemplatePlacementEngine`

O que fazer:
- Adicionar valores ao enum `SpaceFallback`: `reduce_lowest_margin`, `reduce_lowest_sales`.
- Implementar no bloco de corte do engine a ordenação por margem/venda antes de remover SKUs.
- Aplicar em ambos os modos (template e automático herdam automaticamente).

---

### R6 — Exposição do `flow_direction` e critérios no painel de override por gôndola

**Etapas resolvidas:** 11 (fluxo), 9 (critérios visuais) — nível override
**Esforço:** médio
**Nota:** `GondolaSlotOverride` não tem `flow_direction` nem `visual_criteria` como campos. O override
de fluxo hoje exige editar o subtemplate. Pode valer expor no painel de override por gôndola.

---

## Ordem sugerida de execução

```
R1 (zona + fluxo na síntese)
  → R2 (ABC trancado no editor)
  → R3 (critérios enriquecidos)
  → R4 (limites por papel)
  → R5 (corte por margem — se necessário)
  → R6 (override de fluxo — se necessário)
```

R1 e R2 são independentes e podem ser feitos em paralelo ou em sequência. Cada um deve sair com teste.
