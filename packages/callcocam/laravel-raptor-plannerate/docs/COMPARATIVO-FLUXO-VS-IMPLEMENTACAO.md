# Comparativo: Fluxo Documentado × Implementação Real

> Gerado em 2026-06-03; revisado em 2026-06-10. Referência: `FLUXO-PLANOGRAMA-AUTOMATICO.md` vs código em `packages/` e `app/`.
> Os gaps 1, 3 e 4 da revisão original foram fechados (filtro de sortimento, critérios de ordenação, exclusão de curva C).

---

## Legenda de Localização

| Símbolo | Onde fica |
|---|---|
| 📦 **Pacote** | `packages/callcocam/laravel-raptor-plannerate/src/` |
| 🔧 **App** | `app/` (código específico do produto Plannerate) |
| 🔗 **Ambos** | Pacote fornece a base; App orquestra/estende |

---

## Visão Geral do Pipeline

```
Doc (14 passos)                          Localização principal
─────────────────────────────────────    ─────────────────────────────────────────────
1.  Criar base do planograma         →   📦 GondolaController + GondolaService
2.  Buscar template correspondente   →   🔧 TemplatePlacementEngine / AutoTemplateSynthesisOrchestrator
3.  Validações automáticas           →   🔗 ProductSelectionService (app) + modelos (pacote)
4.  Calcular papel (BCG)             →   🔗 BcgAnalysisService (pacote, análise visual)
                                          🔧 CompositeScorer + CategoryRoleInferrer (pipeline geração)
5.  Análise de sortimento (ABC)      →   🔗 AbcAnalysisService (pacote) ← ProductSelectionService (app)
6.  Calcular estoque alvo            →   🔗 TargetStockService (pacote) ← ProductSelectionService (app)
7.  Frente mínima + falta/sobra      →   🔧 GreedyShelfPlacer + SpaceFallback / FacingExpansion
8.  Estratégia por zona              →   🔧 ShelfZoneResolver + ZonePriority
9.  Tipo de exposição                →   🔧 BrandExposure / FlavorExposure + ExposureRedistributeService
10. Ordenação visual                 →   🔧 ProductOrderingService::applyCriteriaCascade()
11. Fluxo de leitura                 →   🔧 FlowDirection + TemplatePlacementEngine
12. Gerar planograma final           →   🔗 PlanogramWriter (app) grava em modelos do pacote
13. Ajustes pós-geração              →   🔧 VisualReorderService / ExposureRedistributeService
14. Classificação do ajuste          →   🔧 AlterationClassifier
```

---

## Passo a Passo Detalhado

---

### Passo 1 — Criar a base do planograma
**Documentado:** Cliente, loja/cluster, estrutura mercadológica, módulos, prateleiras, dimensões, fluxo de leitura.

**Implementado:** ✅ Completo — inteiramente no **📦 Pacote**.

O stepper de criação coleta os dados em 6 etapas via `StoreGondolaRequest`:

| Etapa | Campos coletados |
|---|---|
| Step 1 — Informações básicas | `gondolaName`, `location`, `side`, `scaleFactor`, `flow`, `status`, `mode` (manual/template/automatic) |
| Step 2 — Configuração do módulo | `height`, `width`, `numModules` |
| Step 3 — Base | `baseHeight`, `baseWidth`, `baseDepth` |
| Step 4 — Cremalheira | `rackWidth`, `holeHeight`, `holeWidth`, `holeSpacing` |
| Step 5 — Prateleiras padrão | `shelfHeight`, `shelfWidth`, `shelfDepth`, `numShelves`, `productType` |
| Step 6 — Workflow | `autoStartWorkflow`, `assignToCurrentUser`, `assignedUserId`, `notes` |

Parâmetros de geração automática chegam juntos (flat): `strategy`, `start_date`, `end_date`, `min_facings`, `hot_zone_priority`, `secondary_criteria`, etc.

`GondolaController::store()` delega para `GondolaService::createGondolaWithStructure()` que cria gôndola + seções + prateleiras em transação.

Quando `mode = automatic`: geração é disparada imediatamente via `AutoGenerationRunner::run()` ainda no mesmo request.

**📦 Arquivos do Pacote:**
- `src/Http/Controllers/Editor/GondolaController.php` → `store()`
- `src/Http/Requests/Tenant/Plannerate/Editor/StoreGondolaRequest.php`
- `src/Services/Plannerate/GondolaService.php` → `createGondolaWithStructure()`
- `src/Services/Plannerate/ShelfStructureService.php`
- `src/Models/Editor/Gondola.php`, `Section.php`, `Shelf.php`, `Planogram.php`

**🔧 Arquivos do App (ponto de entrada para geração):**
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/AutoGenerationRunner.php` → chamado pelo `store()` quando `mode = automatic`

---

### Passo 2 — Buscar o template correspondente
**Documentado:** Template identificado por `estrutura mercadológica + quantidade de módulos`. Deve trazer zonas, regras por slot, tipo de exposição e critérios de ordenação.

**Implementado:** ✅ Completo — **🔧 App** (pipeline e modelos de template são do app).

Dois modos de operação:

**Modo Template** (quando `template_id` está preenchido na gôndola):
- Busca `PlanogramSubtemplate` onde `num_modules <= nº de seções`.
- Carrega `PlanogramTemplateSlot` com todas as regras pré-configuradas.
- Pool de candidatos restrito às categorias dos slots via `resolveTemplateScopeCategoryIds()`.

**Modo Automático** (quando `template_id = null`):
- O sistema **sintetiza** o template do zero — não existe um template pré-configurado.
- Pipeline interno da síntese:
  1. `CategoryRoleInferrer` → infere papel de cada subcategoria (Estrela, QuickWin, etc.)
  2. `SlotPlanBuilder` → monta o plano de slots com base no mix e espaço físico
  3. `AutoTemplateSynthesizer` → persiste como `PlanogramSubtemplate`
  4. O template sintetizado entra no mesmo `TemplatePlacementEngine`

> ⚠️ **O modo automático (síntese) não está descrito no doc original.**

**🔧 Arquivos do App:**
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/AutoGenerationRunner.php` → dispara geração e vincula template
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Synthesis/AutoTemplateSynthesisOrchestrator.php`
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Synthesis/CategoryRoleInferrer.php`
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Synthesis/SlotPlanBuilder.php`
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Synthesis/AutoTemplateSynthesizer.php`
- `packages/callcocam/laravel-raptor-plannerate/src/Models/PlanogramTemplate.php`
- `packages/callcocam/laravel-raptor-plannerate/src/Models/PlanogramSubtemplate.php`
- `packages/callcocam/laravel-raptor-plannerate/src/Models/PlanogramTemplateSlot.php`

---

### Passo 3 — Validações automáticas de produtos
**Documentado:** Produto ativo, pertence ao sortimento da loja/cluster, pertence à categoria, tem dimensões, cabe fisicamente, não está bloqueado.

**Implementado:** 🔗 **Ambos** — modelos e queries no Pacote; lógica de seleção no App.

| Critério do Doc | Status | Onde |
|---|---|---|
| Produto ativo (não draft) | ✅ | 📦 `fetchProductsByCategoryIds()` filtra `status !== 'draft'` |
| Pertence à categoria | ✅ | 📦 CTE recursiva em `getProductsFromCategory()` |
| Tem dimensões cadastradas | ✅ | 🔧 `requireDimensions` flag no modo auto; engine rejeita `MissingDimensions` no modo template |
| Não está bloqueado | ✅ | 🔧 `planogram_product_rules` (blocked por produto, marca, subcategoria) |
| Cabe fisicamente | ✅ | 🔧 Engine rejeita com `HeightExceedsShelf` (vão livre da prateleira) / `NoHorizontalSpace` |
| Pertence ao sortimento da loja/cluster | ✅ | 🔧 `resolveStoreIdForAssortment()` + `whereExists product_store` em `ProductSelectionService` (loja direta ou herdada do cluster; planograma sem loja/cluster = sem filtro, compatibilidade legado) |

**📦 Arquivos do Pacote:**
- `src/Models/Editor/Product.php`
- `src/Models/Editor/Category.php`

**🔧 Arquivos do App:**
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/ProductSelectionService.php` → `selectAndRankProducts()`
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/AutoPlanogramService.php` → `loadProductRules()`
- `app/Models/PlanogramProductRule.php`

---

### Passo 4 — Cálculo de papel (BCG)
**Documentado:** Classifica cada **produto** com papéis estratégicos: Alto valor, Peso morto, Incentivo lucro, Incentivo valor, Incentivo margem.

**Implementado:** ⚠️ Dois sistemas separados — **📦 Pacote** para análise visual do editor; **🔧 App** para o pipeline de geração.

**BCG visual no editor** (📦 Pacote — `BcgAnalysisService`):
- Calcula matriz BCG clássica por produto: Estrela, Vaca Leiteira, Interrogação, Abacaxi.
- Usa dois períodos: atual (market share) + anterior (taxa de crescimento).
- **Não é chamado no pipeline de geração automática** — serve para análise e exibição no editor.

**Score de produto no pipeline** (🔧 App — `CompositeScorer`):
- Calcula score numérico (0.0–1.0) composto por: `giro_norm`, `margem_norm`, `doh_norm`, `strategic`.
- Resultado ordena o pool — não gera um "papel nomeado" por produto.

**Papel de categoria no modo automático** (🔧 App — `CategoryRoleInferrer`):
- `StarCategory`, `QuickWin`, `BudgetItem`, `NicheItem` — usado na síntese do template.

> ⚠️ **Gap:** Os papéis nomeados por produto do doc (peso morto, incentivo margem etc.) não existem no pipeline de geração. O `BcgAnalysisService` do pacote calcula isso, mas está desconectado do pipeline automático.

**📦 Arquivos do Pacote:**
- `src/Services/Plannerate/BcgAnalysisService.php` → análise BCG visual (editor)

**🔧 Arquivos do App:**
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Scoring/CompositeScorer.php` → score numérico por produto
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Synthesis/CategoryRoleInferrer.php` → papel por categoria
- `app/Models/ScoringWeights.php`
- `app/Models/ProductStrategicFlag.php`
- `packages/callcocam/laravel-raptor-plannerate/src/Enums/CategoryRole.php`

---

### Passo 5 — Análise de sortimento (ABC)
**Documentado:** Define o que fica e o que sai — produtos prioritários (A), intermediários (B), baixa prioridade (C), candidatos a retirada.

**Implementado:** ✅ Funcional — 🔗 **Ambos**. Pacote calcula; App consome e repassa ao engine.

- `AbcAnalysisService` classifica A/B/C por média ponderada de quantidade + valor + margem.
- Cortes configuráveis: `abcCutoffA` (padrão 80%) e `abcCutoffB` (padrão 85%).
- `ProductSelectionService` chama o serviço do pacote e monta o `abcClassMap`.
- O `abcClassMap` chega ao `TemplatePlacementEngine` para influenciar ordenação visual.
- Modo cache: usa `product_analyses` existentes se `useExistingAnalysis = true`.
- Modo on-the-fly: recalcula direto das vendas do período informado.

> ⚠️ **Nuance:** Curva C **entra no pool** — não é excluída na seleção. O `space_fallback` pode remover C durante o placement se faltar espaço, mas não há corte de sortimento pré-geração.

**📦 Arquivos do Pacote:**
- `src/Services/Plannerate/AbcAnalysisService.php` → cálculo ABC

**🔧 Arquivos do App:**
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/ProductSelectionService.php` → `getAbcAnalyses()`, `computeAbcOnTheFly()`
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/DTO/PlacementSettings.php` → transporta `abcClassMap`

---

### Passo 6 — Calcular estoque alvo
**Documentado:** Quanto espaço cada produto precisa — frentes sugeridas, cobertura, redução de ruptura.

**Implementado:** ✅ Completo — 🔗 **Ambos**. Pacote calcula; App consome.

- `TargetStockService::calculateByAbcResults()` calcula `estoque_alvo` e `estoque_seguranca`.
  - Cobertura em dias por classe: A=2 dias, B=5 dias, C=7 dias (configurável).
  - Nível de serviço por classe: A=70%, B=80%, C=90% (configurável).
- `targetStockMap` [product_id → float] chega ao `PlacementSettings`.
- Flag `use_target_stock` no slot e em `planogram_gondola_slot_overrides` controla se o estoque alvo influencia o número de frentes calculado.

**📦 Arquivos do Pacote:**
- `src/Services/Plannerate/TargetStockService.php` → cálculo de estoque alvo

**🔧 Arquivos do App:**
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/ProductSelectionService.php` → chama `TargetStockService` e monta `targetStockMap`
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/DTO/PlacementSettings.php` → transporta `targetStockMap`

---

### Passo 7 — Frente mínima + ajuste de falta/sobra de espaço
**Documentado:** `min_facings` garante presença visual; falta → reduzir frentes / remover produtos; sobra → expandir prioritários.

**Implementado:** ✅ Completo — **🔧 App**.

- `min_facings` e `max_facings` nos slots do template.
- `SpaceFallback` enum: `ReduceFacings`, `RemoveLowestPriority`, `RemoveDeadWeight`, `RemoveCurvC`, `PreserveMandatory`.
- `FacingExpansion` enum: `NoExpansion`, `ExpandHighPriority`, `ExpandHighStock`, `ExpandHighMargin`, `ExpandHighSales`.
- `FacingMinimumRule` na validação pós-placement verifica violações.
- `planogram_gondola_slot_overrides` sobrescreve `min_facings`, `max_facings`, `space_fallback` e `facing_expansion` por categoria naquela gôndola.

**🔧 Arquivos do App:**
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Placement/GreedyShelfPlacer.php`
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Validation/Rules/FacingMinimumRule.php`
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/AutoPlanogramService.php` → `loadGondolaSlotOverrides()`
- `packages/callcocam/laravel-raptor-plannerate/src/Enums/SpaceFallback.php`
- `packages/callcocam/laravel-raptor-plannerate/src/Enums/FacingExpansion.php`
- `app/Models/GondolaSlotOverride.php`

---

### Passo 8 — Estratégia por zona
**Documentado:** Zona quente → maior margem/giro/alto valor; zona fria → complementares, menor prioridade, embalagens maiores.

**Implementado:** ✅ Completo — **🔧 App**.

- `ShelfZoneResolver::resolve()` mapeia posição física → zona:
  - **Zona quente** (hot): Eye + Hand → prateleiras centrais de melhor acesso visual.
  - **Zona fria** (cold): High + Low → prateleiras no topo e no chão.
- `ZonePriority` enum: `Margin`, `Giro`, `AbcClass`, `None`.
- `hotZonePriority` / `coldZonePriority` configurados no `TemplatePlacementEngine` a partir do template.
- `zoneMetricsMap` [product_id → {giro, margem}] construído a partir do `CompositeScorer`.

**🔧 Arquivos do App:**
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/ShelfZoneResolver.php`
- `packages/callcocam/laravel-raptor-plannerate/src/Enums/ShelfLevel.php`
- `packages/callcocam/laravel-raptor-plannerate/src/Enums/ZonePriority.php`
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Placement/TemplatePlacementEngine.php`

---

### Passo 9 — Tipo de exposição
**Documentado:** Vertical (coluna por grupo), horizontal (faixa lateral), combinada.

**Implementado:** ✅ Funcional — **🔧 App**.

- `BrandExposure` enum: `Vertical`, `Horizontal`, `None`.
- `FlavorExposure` enum: `Vertical`, `Horizontal`, `None`.
- Configurados por slot do template (`brand_exposure`, `flavor_exposure`).
- `ExposureRedistributeService` redistribui quando o usuário altera a configuração pós-geração.

> ⚠️ "Exposição combinada" não é enum próprio — é resultado de regras diferentes em cada slot/módulo.

**🔧 Arquivos do App:**
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Placement/ExposureRedistributeService.php`
- `packages/callcocam/laravel-raptor-plannerate/src/Enums/BrandExposure.php`
- `packages/callcocam/laravel-raptor-plannerate/src/Enums/FlavorExposure.php`

---

### Passo 10 — Ordenação visual hierárquica
**Documentado:** Critérios em cascata: marca, tipo, embalagem, tamanho, preço, versão, atributo.

**Implementado:** ✅ Completo — **🔧 App**.

- `ProductOrderingService::applyCriteriaCascade()` aplica lista de critérios do menor ao maior peso.
- O `TemplatePlacementEngine` delega a ordenação ao mesmo `ProductOrderingService` usado por
  `VisualReorderService` e `ExposureRedistributeService` — geração e reordenação produzem a mesma ordem.
- `visual_criteria` em `PlanogramTemplateSlot` (JSON: `[{key, direction, packaging_order?}]`).

| Critério | Status |
|---|---|
| marca | ✅ |
| preco | ✅ |
| tamanho | ✅ |
| score_abc | ✅ |
| margem | ✅ |
| tipo | ✅ |
| embalagem | ✅ (ordem customizada via `packaging_order`) |
| sabor | ✅ |
| atributo | ✅ (`sortiment_attribute`) |

- Ordenação legada (slots sem `visual_criteria`): size → price → brand.

**🔧 Arquivos do App:**
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/ProductOrderingService.php`
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/ProductSizeResolver.php`
- `packages/callcocam/laravel-raptor-plannerate/src/Enums/PriceOrder.php`
- `packages/callcocam/laravel-raptor-plannerate/src/Enums/SizeOrder.php`

---

### Passo 11 — Fluxo de leitura
**Documentado:** Esq→Dir, Dir→Esq, entrada→saída, saída→entrada.

**Implementado:** ✅ Completo — **🔧 App**.

- `FlowDirection` enum lido da gôndola.
- `TemplatePlacementEngine` espelha posições físicas quando `FlowDirection::RightToLeft`.

**🔧 Arquivos do App:**
- `packages/callcocam/laravel-raptor-plannerate/src/Enums/FlowDirection.php`
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Placement/TemplatePlacementEngine.php`

---

### Passo 12 — Gerar o planograma final
**Documentado:** Produtos alocados, rejeitados, frentes, posição por módulo/prateleira, zona, organização visual.

**Implementado:** ✅ Completo — 🔗 **Ambos**. App grava; Pacote fornece os modelos e o editor.

- `PlanogramWriter::write()` persiste `Segment` + `Layer` no banco (dentro de transação).
- `RejectedProductsWriter::write()` registra em `planogram_rejected_products` com `PlacementFailureReason`.
- `PlanogramOutput` contém: `placedSegments`, `rejectedProducts`, `slotAnalysis`, `suggestions`, `validationReport`, `explanationReport`, `modulesMismatch`.
- `GondolaController::edit()` carrega o payload do editor via `GondolaPayloadService` (Pacote).
- `SaveChangesController` persiste edições manuais feitas no editor (Pacote).

**📦 Arquivos do Pacote:**
- `src/Services/Plannerate/GondolaPayloadService.php` → payload do editor
- `src/Http/Controllers/Editor/SaveChangesController.php` → salvar edições manuais
- `src/Models/Editor/Segment.php`, `Layer.php`

**🔧 Arquivos do App:**
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Placement/PlanogramWriter.php`
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Placement/RejectedProductsWriter.php`
- `packages/callcocam/laravel-raptor-plannerate/src/Models/PlanogramRejectedProduct.php`
- `packages/callcocam/laravel-raptor-plannerate/src/Enums/PlacementFailureReason.php`

---

### Passo 13 — Validação pós-geração
**Documentado:** Não descrito — o doc original pula direto para ajustes.

**Implementado:** ✅ Existe — **🔧 App** (funcionalidade além do doc).

`PlanogramValidator` executa 7 regras:

| Regra | O que verifica |
|---|---|
| `FacingMinimumRule` | Todo produto alocado respeita `min_facings` |
| `SectionCapacityRule` | Nenhuma seção ultrapassa 100% da largura |
| `EmptyShelfRule` | Não há prateleiras completamente vazias |
| `AdjacencyRule` | Categorias incompatíveis não ficam adjacentes |
| `BlockIntegrityRule` | Blocos verticais íntegros entre prateleiras |
| `ShelfLevelRule` | Produtos pesados não estão em prateleiras altas |
| `UnplacedProductsRule` | Taxa de não alocados dentro do limite aceitável |

**🔧 Arquivos do App:**
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Validation/PlanogramValidator.php`
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Validation/Rules/` (7 arquivos)
- `app/Models/AdjacencyRule.php`
- `app/Models/ShelfLevelPreference.php`

---

### Passo 14 — Ajustes pós-geração
**Documentado:** Três comportamentos: Reordenar (só visual), Redistribuir (muda estrutura), Regerar (muda parâmetros).

**Implementado:** ✅ Completo — **🔧 App** (com classificação automática além do doc).

| Tipo | O que faz | Serviço |
|---|---|---|
| **Reordenar** | Mantém produtos e frentes, reorganiza posições | `VisualReorderService` |
| **Redistribuir** | Muda tipo de exposição, mantém produtos | `ExposureRedistributeService` |
| **Regerar** | Recalcula tudo do zero com novos parâmetros | `AutoGenerationRunner::run()` |
| **Classificar** | Detecta automaticamente qual tipo é necessário | `AlterationClassifier` |

**🔧 Arquivos do App:**
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Placement/VisualReorderService.php`
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Placement/ExposureRedistributeService.php`
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/AlterationClassifier.php`
- `packages/callcocam/laravel-raptor-plannerate/src/Enums/AlterationLevel.php`

---

## Funcionalidades Implementadas Não Documentadas

### 1. Modo Automático com Síntese de Template (🔧 App)
Gera planograma sem template pré-configurado. Sintetiza o template dinamicamente a partir do mix.
- `packages/callcocam/laravel-raptor-plannerate/src/AutoPlanogram/Synthesis/`

### 2. Slot Overrides por Gôndola (🔧 App)
Configurações por categoria que sobrescrevem o template slot para aquela gôndola específica.
- Tabela: `planogram_gondola_slot_overrides`
- `app/Models/GondolaSlotOverride.php`

### 3. Regras Mandatory/Blocked por Tenant (🔧 App)
Produtos, marcas e subcategorias marcados como obrigatórios ou bloqueados.
- Tabela: `planogram_product_rules`
- `app/Models/PlanogramProductRule.php`

### 4. Limite de Share por SKU/Marca/Subcategoria (🔧 App)
Impede que um único produto, marca ou subcategoria domine o slot.
- `max_share_per_sku`, `max_share_per_brand`, `max_share_per_subcategory`

### 5. BCG Visual no Editor (📦 Pacote)
`BcgAnalysisService` calcula Estrela / Vaca Leiteira / Interrogação / Abacaxi para exibição no editor.
- Não está conectado ao pipeline de geração automática.
- `src/Services/Plannerate/BcgAnalysisService.php`

---

## Resumo dos Gaps (revisão 2026-06-10)

| # | Gap | Status |
|---|---|---|
| 1 | **Filtro de sortimento por loja/cluster** | ✅ Fechado — `resolveStoreIdForAssortment()` + `whereExists product_store` em `ProductSelectionService` |
| 2 | **Conectar BCG do pacote ao pipeline** | ✅ Atendido via Análise de Papel — `PaperAnalysisService` (pacote) alimenta `paperRole` (`leader/anchor/rising/lagging`) no pipeline: componente do `CompositeScorer` e fallback `remove_dog` |
| 3 | **Critérios de ordenação incompletos** | ✅ Fechado — `tipo`, `embalagem` (com `packaging_order`), `sabor` e `atributo` em `ProductOrderingService`, compartilhado entre geração e reordenação |
| 4 | **Exclusão de curva C no sortimento** | ✅ Fechado — flag `excludeClassC` em `AutoGenerateConfigDTO` + filtro em `ProductSelectionService` (preservada no modo template via `withOverrides`) |
| 5 | **Atualizar doc original** | ✅ Fechado — `FLUXO-PLANOGRAMA-AUTOMATICO.md` reescrito (modo automático, síntese, overrides, mandatory/blocked, overflow pass, enums reais) |
