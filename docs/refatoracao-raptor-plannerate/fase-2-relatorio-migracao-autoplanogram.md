# Fase 2 — Relatório de Migração: `app/Services/AutoPlanogram` → pacote

> Data: 2026-06-11
> Escopo: estudo do AutoPlanograma + análise de acoplamento (somente leitura — nenhum código alterado).
> Pré-requisito: Fase 1 aprovada (`fase-1-relatorio-arquitetura-atual.md`).

---

## 1. Panorama do AutoPlanograma

**59 arquivos PHP, ~10.350 linhas**, organizados em 6 submódulos + raiz:

```
app/Services/AutoPlanogram/
├── (raiz) AutoGenerationRunner, AutoPlanogramService, AutoGenerationResult,
│          ProductSelectionService, ProductOrderingService, AlterationClassifier,
│          ProductWidthResolver, ProductSizeResolver, ShelfZoneResolver, ScoredProductMapper
├── DTO/         15 DTOs (PlacementSettings 708 l, PlanogramInput/Output, PlacedSegment/Layer,
│                ScoredProduct, RankedProductDTO, AutoGenerateConfigDTO, ShelfLayoutDTO,
│                CategoryAbcSummary, ProductBlock, OrderedBlock, SlotPlanEntry, ValidationReport...)
├── Scoring/     CompositeScorer, SalesMetricsRepository, ScoringWeightsValue, ProductScorerInterface
├── Placement/   TemplatePlacementEngine (1.338 l), GreedyShelfPlacer (652 l, modo legado),
│                ShelfLevelStrategy, PlanogramWriter(+Interface), RejectedProductsWriter,
│                VisualReorderService, ExposureRedistributeService, PlacementEngineInterface
├── Synthesis/   AutoTemplateSynthesisOrchestrator, AutoTemplateSynthesizer, SlotPlanBuilder (697 l),
│                CategoryRoleInferrer
├── Template/    TemplateSlotService, SlotReviewAnalysisService (738 l), SlotSuggestionGenerator,
│                TemplateImportService / TemplateExportService (XLSX), TemplateImportReport
└── Validation/  PlanogramValidator + ValidationResult + 7 Rules (BlockIntegrity, Adjacency,
                 ShelfLevel, FacingMinimum, SectionCapacity, EmptyShelf, UnplacedProducts)
```

### Pipeline (modo template)
`AutoGenerationRunner.run()` → `ProductSelectionService.selectAndRankProducts()` (pool por categoria dos slots + ABC + estoque alvo + papel) → `PlacementSettings.fromConfigDto().with*()` → `AutoPlanogramService.generate()` → `CompositeScorer.scoreOrNeutral()` → `TemplatePlacementEngine.place()` (slots, zonas, critérios visuais, regras mandatory/blocked, limites de participação, overrides por gôndola, fluxo RTL, explicações) → `PlanogramValidator` → transação: `PlanogramWriter` + `RejectedProductsWriter`.

### Pipeline (modo automático)
Igual, mas antes do placement: `ensureShelvesExist()` (fallback via `ShelfStructureService` **do pacote**) → `AutoTemplateSynthesisOrchestrator` (síntese de template N×M a partir do mix: `SlotPlanBuilder` + `CategoryRoleInferrer` + `AutoTemplateSynthesizer`) → placement com o template sintetizado → `pruneEmptySlots()`. Ao final, a gôndola é vinculada ao template sintetizado (`generation_mode = 'template'`).

### Operações pós-geração (sem regerar)
- `VisualReorderService` — reordena segmentos (Reorder)
- `ExposureRedistributeService` — redistribui exposição (Redistribute)
- `AlterationClassifier` — classifica campos alterados em Reorder/Redistribute/Regenerate (espelho TS em `resources/js/components/planogram-templates/alteration-classifier.ts`)

### Bindings (DI) — `app/Providers/AutoPlanogramServiceProvider.php`
- `ProductScorerInterface → CompositeScorer`
- `PlacementEngineInterface → GreedyShelfPlacer` (motor legado, ainda usado em testes)
- `PlanogramWriterInterface → PlanogramWriter`
- `ProductWidthResolver` singleton (defaultWidth 10.0, maxPlausible 60.0)
- `PlanogramValidator` singleton com as 7 regras na ordem

⚠️ Este provider precisa **migrar para o ServiceProvider do pacote** (ou um provider dedicado registrado por ele).

---

## 2. Acoplamento — AutoPlanograma → PACOTE (já depende dele; vira dependência interna)

| Dependência | Usos | Após migração |
|---|---|---|
| `Models\Editor\{Section, Product, Shelf, Segment, Gondola, Planogram, Layer}` | 25 imports | interna ao pacote — sem mudança |
| `Concerns\UsesPlannerateTenantDatabase` | 4 | interna |
| `Services\Plannerate\{AbcAnalysisService, TargetStockService, PaperAnalysisService, ShelfStructureService}` | 4 | interna |
| `Http\Requests\...\AutoGeneratePlanogramRequest` (usado pelo controller) | 1 | interna |

**Conclusão:** metade do acoplamento "externo" do AutoPlanograma já é com o próprio pacote. A migração **elimina** essas fronteiras.

## 3. Acoplamento — AutoPlanograma → APP (decisão por item)

### 3.1 Models de domínio planograma → **ENTRAM no pacote**

| Model (tabela) | Usado também fora do AutoPlanogram? | Decisão |
|---|---|---|
| `PlanogramTemplate` (`planogram_templates`) | controllers de template, sidebar nav, GondolaPayloadService do pacote | **pacote** (é domínio planograma) |
| `PlanogramSubtemplate` (`planogram_subtemplates`) | controllers de template/override | **pacote** |
| `PlanogramTemplateSlot` (`planogram_template_slots`) | idem | **pacote** |
| `PlanogramRejectedProduct` (`planogram_rejected_products`) | listener LayerRemoved, comandos, Gondola::deleting do pacote | **pacote** |
| `GondolaSlotOverride` (`planogram_gondola_slot_overrides`) | só controller próprio | **pacote** |
| `PlanogramProductRule` (`planogram_product_rules`) | só controller próprio | **pacote** |
| `ScoringWeights` (`scoring_weights`) | Settings controllers | **pacote** (config do motor; controllers de settings podem continuar no app importando do pacote) |
| `ShelfLevelPreference` (`shelf_level_preferences`) | Settings controllers | **pacote** |
| `AdjacencyRule` (`adjacency_rules`) | Settings controllers | **pacote** |

⚠️ Esses models usam `BelongsToTenant`, `UsesTenantConnection` e `TenantScope` do **app** — tratados em §3.3.

### 3.2 Enums → **ENTRAM no pacote** (15)
`PlacementFailureReason`, `ShelfLevel`, `ZonePriority`, `SpaceFallback`, `FlowDirection`, `CategoryRole`, `ValidationSeverity`, `SizeOrder`, `PriceOrder`, `FlavorExposure`, `FacingExpansion`, `BrandExposure`, `AlterationLevel`, `AdjacencyRuleType`, `ProductRuleType`. Nenhum tem uso significativo fora do domínio planograma (verificar `CategoryRole` que também é cast em `App\Models\Category.role` — ver §3.3).

### 3.3 Infra do app → **FICAM no app**, consumidos pelo pacote (contrato explícito)

| Dependência | Uso | Tratamento proposto |
|---|---|---|
| `App\Models\Traits\BelongsToTenant` + `UsesTenantConnection` + `App\Models\Scopes\TenantScope` | todos os models tenant | manter no app; o pacote já depende disso hoje (models Editor usam `BelongsToTenant`). Alternativa limpa: o pacote define os traits e o app reexporta — decidir na Fase 3 |
| `App\Models\Category` | hierarquia mercadológica (`getDescendantIds`), role | fica no app (entidade compartilhada com produtos/importação); pacote consome. O pacote já tem `Models\Editor\Category` espelho — unificar na Fase 3 |
| `App\Models\Product` (uso pontual em Validation/SlotReview) | leitura | trocar pelo `Models\Editor\Product` do pacote na migração |
| `App\Models\Sale` | SalesMetricsRepository | usar o `Models\Editor\Sale` do pacote |
| `app('currentTenant')` | Runner | mantém (Spatie) |
| `__('app.messages.*')` traduções | mensagens de erro | mover chaves para namespace do pacote ou manter em `lang/pt_BR` do app — decidir na Fase 3 |
| `phpoffice/phpspreadsheet` | Template Import/Export XLSX | declarar no composer.json do pacote |

### 3.4 Quem consome o AutoPlanograma (app → AutoPlanogram) — atualizar imports

| Consumidor | O que usa | Após migração |
|---|---|---|
| `AutoPlanogramController` (app) | Runner, ConfigDTO, Reorder/Redistribute services | **mover para o pacote** (rotas `api/gondolas/*` juntas) ou manter no app com imports novos — recomendação: **mover** (todas as 11 rotas são domínio do editor) |
| `GondolaController` (pacote) | `AutoGenerationRunner` + `AutoGenerateConfigDTO` | vira dependência interna ✅ |
| `Tenant\PlanogramTemplateController` + `TemplateSlotController` (app) | Template/* services, models | **mover para o pacote** junto com as ~25 rotas `planogram-templates/*` (CRUD de template é domínio do pacote) — ou manter no app; recomendação: mover |
| `GondolaSlotOverrideController`, `PlanogramProductRuleController` (app) | models + rotas | **mover para o pacote** |
| `Settings\{ScoringWeights,AdjacencyMatrix,PlanogramSettings,ShelfLevelPreferences}Controller` | models de config | **manter no app** (são páginas de Settings do app), importando models do pacote |
| `AutoPlanogramServiceProvider` | bindings DI | fundir no provider do pacote |
| Comandos `ResetPlanogramDataCommand`, `CleanGondolaRelationshipsCommand` | models | manter no app, atualizar imports |
| Listener `HandleLayerRemovedForRejectedProducts` | `PlanogramRejectedProduct` + `LayerRemovedEvent` (pacote) | **mover para o pacote** (evento já é de lá) |
| ~41 arquivos de teste (`tests/Unit/Services/AutoPlanogram/*`, `tests/Feature/AutoPlanogram*`) | tudo | atualizar namespaces; decidir na Fase 3 se ficam em `tests/` do app ou no pacote |

### 3.5 Frontend acoplado (a migrar junto ou manter — decidir na Fase 3)
- `resources/js/components/planogram-templates/` (19 arquivos: SlotEditorModal, VisualCriteriaEditor, alteration-classifier.ts, types.ts, validation.ts...) — wizard de templates, espelha contratos do AutoPlanograma
- `resources/js/pages/tenant/planogram-templates/` (6 páginas)
- `resources/js/components/PlanogramCapacityBanner.vue` — exibe capacity_report/explanation_report
- Actions wayfinder de `AutoPlanogramController` (mantidas manualmente)

Recomendação: se os controllers de template forem para o pacote, esses componentes vão junto (`resources/js/components/planogram-templates` do pacote), mantendo os aliases.

---

## 4. Estrutura proposta dentro do pacote (preliminar — detalhada na Fase 3)

```
packages/callcocam/laravel-raptor-plannerate/src/
├── AutoPlanogram/              ← app/Services/AutoPlanogram inteiro (DTO, Scoring,
│   │                              Placement, Synthesis, Template, Validation)
│   └── (namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\...)
├── Models/                     ← + PlanogramTemplate, PlanogramSubtemplate, PlanogramTemplateSlot,
│                                  PlanogramRejectedProduct, GondolaSlotOverride, PlanogramProductRule,
│                                  ScoringWeights, ShelfLevelPreference, AdjacencyRule
├── Enums/                      ← os 15 enums do domínio
├── Http/Controllers/           ← + AutoPlanogramController, PlanogramTemplateController,
│                                  TemplateSlotController, GondolaSlotOverrideController,
│                                  PlanogramProductRuleController
└── Providers (bindings do AutoPlanogramServiceProvider fundidos)
routes/                          ← + rotas de geração e de templates (hoje em routes/tenant.php)
database/migrations/clients/     ← + ~30 migrations de templates/regras/overrides (cópia, manter histórico)
resources/js/components/planogram-templates/  ← wizard de templates (se aprovado)
```

## 5. Mapa antes → depois (resumo por arquivo raiz)

| Antes (`App\Services\AutoPlanogram\`) | Depois (`Callcocam\LaravelRaptorPlannerate\AutoPlanogram\`) |
|---|---|
| `AutoGenerationRunner` | `AutoGenerationRunner` (sem mudança de lógica; imports internos) |
| `AutoPlanogramService` | `AutoPlanogramService` |
| `ProductSelectionService` | `ProductSelectionService` (já usa services do pacote — vira interno) |
| `ProductOrderingService`, `AlterationClassifier`, resolvers | idem 1:1 |
| `DTO/*` (15) | `AutoPlanogram\DTO\*` 1:1 |
| `Scoring/*`, `Placement/*`, `Synthesis/*`, `Template/*`, `Validation/*` | 1:1, subpastas preservadas |
| `App\Providers\AutoPlanogramServiceProvider` | fundido no `LaravelRaptorPlannerateServiceProvider` |
| `App\Models\Planogram{Template,Subtemplate,TemplateSlot,RejectedProduct,ProductRule}`, `GondolaSlotOverride`, `ScoringWeights`, `ShelfLevelPreference`, `AdjacencyRule` | `...\Models\*` (mesmas tabelas, mesmos casts) |
| `App\Enums\{15 enums}` | `...\Enums\*` |
| Rotas em `routes/tenant.php` (gondolas/auto-generate, rejected-products, reorder/redistribute, overrides, product-rules, planogram-templates/*) | `routes/` do pacote, **mesmas URIs e nomes** |

**Garantia de não-perda:** nenhuma tabela, coluna, URI ou nome de rota muda. Só muda namespace PHP e local do arquivo. Os ~41 arquivos de teste existentes são a rede de segurança — devem passar inalterados (apenas com imports atualizados).

## 6. Dependências "escondidas" encontradas (nada fica para trás)

1. **`AutoGeneratePlanogramRequest` já mora no pacote** — o form request do endpoint de geração está em `packages/.../Http/Requests/Tenant/Plannerate/`, separado do controller que o usa (app). A migração reunifica.
2. **Espelho TS do `AlterationClassifier`** em `resources/js/components/planogram-templates/alteration-classifier.ts` — qualquer mudança nas constantes PHP exige atualizar o espelho. Documentar como contrato duplo.
3. **`TemplateSlotService` (Template/) usa `PlanogramRejectedProduct`** — validação cruzada slot×rejeitados.
4. **`SlotReviewAnalysisService` consulta `App\Models\Product` e `Category` direto** (hierarquia) — na migração, trocar para os models do pacote/contrato de categoria.
5. **Frontend do editor consome flash `capacity_report`/`explanation_report`** produzido pelo `AutoPlanogramController` — formato é contrato implícito com `PlanogramCapacityBanner.vue` e `RejectedProductsDrawer.vue`.
6. **Wayfinder actions manuais** (`reorderGondola`, `redistributeGondola` etc.) em `resources/js/actions/App/Http/Controllers/AutoPlanogramController.ts` — se o controller mudar de FQCN, o caminho do arquivo de actions muda (`actions/Callcocam/...`); atualizar imports nos componentes.
7. **`GreedyShelfPlacer`** (motor legado, modo automático antigo) ainda é o binding de `PlacementEngineInterface` e tem testes — migrar junto, não descartar.
8. **Traduções** `app.messages.no_products_found` e afins usadas dentro do serviço.
9. **`ScoringWeights::first()`** sem tenant scope explícito no Runner — comportamento atual preservado (tabela tenant-scoped por conexão).

## 7. Riscos específicos da migração

1. **Namespace nos testes:** 41 arquivos; risco baixo (find/replace), mas o `CompositeScorerTest` já crasha por problema pré-existente (exit 2) — não confundir com regressão da migração.
2. **Wayfinder/actions:** mudança de FQCN dos controllers altera caminhos dos arquivos TS gerados; os manuais precisam ser movidos em sincronia.
3. **Ordem de boot de providers:** os bindings DI precisam estar registrados antes de qualquer resolução nos controllers — fundir no provider do pacote resolve.
4. **Migrations:** as ~30 migrations de templates já rodaram nos tenants; **não se movem fisicamente** — novas instalações usam o sync do pacote; as existentes ficam como histórico no app. Nenhum `migrate:fresh`.
5. **Models duplicados (app × pacote)** para `gondolas/planograms/etc.`: a Fase 3 define o dono único; nesta fase apenas registramos que o AutoPlanograma usará os do pacote.

---

## 8. Próximo passo

**Fase 3** — documento de plano de refatoração passo a passo (nova arquitetura completa do pacote com AutoPlanograma integrado, árvore de pastas, mapa de equivalência total, ordem de implementação em etapas verificáveis, checklist de preservação, plano de testes, riscos). Ao final dela: **PARAR e aguardar aprovação** antes de qualquer código.
