# Fase 3 — Plano de Refatoração Passo a Passo: `laravel-raptor-plannerate`

> Data: 2026-06-11
> Pré-requisitos: Fase 1 e Fase 2 aprovadas (relatórios nesta mesma pasta).
> **Status: AGUARDANDO APROVAÇÃO. Nenhum código será escrito antes do OK explícito.**

---

## 0. Decisões assumidas (confirmar na aprovação)

| # | Decisão | Justificativa | Alternativa rejeitada |
|---|---|---|---|
| D1 | **Manter o namespace raiz** `Callcocam\LaravelRaptorPlannerate\` | 98 arquivos do app + actions wayfinder dependem dos FQCNs; trocar namespace = churn massivo sem ganho funcional | renomear para `Callcocam\Plannerate\` |
| D2 | **Reconstrução incremental in-place na branch isolada** (módulo a módulo, testes verdes a cada etapa) | 34,5k linhas de frontend num build único com o app; versão paralela dobraria a superfície e quebraria os aliases | greenfield paralelo + switch final |
| D3 | **Frontend continua "fonte no pacote, build no app"** (aliases Vite/tsconfig preservados) | é como funciona hoje; mudar o modelo de build é risco sem ganho para o objetivo | pacote com build próprio (lib mode) |
| D4 | **Estado do editor: manter composables singleton, MAS com estado normalizado** (mapas indexados por id + helpers tipados) — sem Pinia | elimina `useReactivityHelpers` e lookups O(n) com o menor delta conceitual; Pinia seria reescrita total do fluxo undo/snapshot com alto risco de regressão | migrar tudo para Pinia |
| D5 | **Controllers de templates/geração/regras/overrides MOVEM para o pacote** com rotas idênticas (URIs e nomes preservados) | são domínio planograma; Fase 2 §3.4 | mantê-los no app só trocando imports |
| D6 | **Models do domínio planograma: dono único = pacote.** App mantém apenas re-export thin (classe vazia estendendo a do pacote) onde houver acoplamento externo difícil de cortar | encerra a duplicação app×pacote sem quebrar policies/navegação | conviver com os espelhos |
| D7 | **Typos de rota (`plannograma`) preservados** nesta refatoração | contrato público; corrigir seria mudança de contrato — pode ser feito depois com rota-alias | corrigir agora |
| D8 | **Testes do AutoPlanograma permanecem em `tests/` do projeto** (só atualizam imports); novos testes do pacote também ficam lá, organizados em `tests/*/Plannerate/` | o pacote não tem runner próprio; o projeto já roda tudo via `docker compose exec php php artisan test` | suíte Pest dentro do pacote |

---

## 1. Visão geral da nova arquitetura

O pacote passa a ser **dono completo do domínio planograma**: editor (frontend+backend), templates, geração automática (AutoPlanograma), análises, exportação e regras. O app fica com: tenancy/RBAC/navegação, Settings (páginas), produtos/importação, categorias, workflow e tudo que não é planograma.

```
┌─────────────────────────── APP (Laravel) ───────────────────────────┐
│ Tenancy (BelongsToTenant, TenantScope)   RBAC (PermissionName)      │
│ Category (hierarquia)   Workflow   Settings pages   Produtos/Import │
└───────────────┬──────────────────────────────────────────────────────┘
                │ contratos: traits de tenant, Category, permissões, traduções
┌───────────────▼────────── PACOTE laravel-raptor-plannerate ──────────┐
│ Domain/        models + enums do planograma (dono único)            │
│ Editor/        CRUD físico, save-changes (delta), payload           │
│ AutoPlanogram/ pipeline completo (scoring→synthesis→placement→write)│
│ Templates/     CRUD de templates/slots, import/export, review       │
│ Analysis/      ABC, Estoque Alvo, Papel + export CSV               │
│ Export/        PDF, PNG, QR, share público                          │
│ resources/js/  editor Vue completo + wizard de templates            │
└──────────────────────────────────────────────────────────────────────┘
```

Princípios da reconstrução:
1. **Contratos congelados:** tabelas, colunas, URIs, nomes de rota, payloads (editor payload, save-changes delta, capacity_report/explanation_report, eventos broadcast) — idênticos.
2. **Organização por domínio** dentro de convenções Laravel reconhecíveis.
3. **Sem dependência escondida:** tudo que o pacote consome do app fica listado num único lugar (`docs/CONTRATOS.md` do pacote) e, onde couber, atrás de interface.
4. **Frontend com estado normalizado, DnD centralizado, componentes ≤ ~300 linhas, tipos estritos** nos fluxos críticos (snapshot/delta).

## 2. Estrutura de pastas proposta

### 2.1 Backend (`src/`)

```
src/
├── LaravelRaptorPlannerateServiceProvider.php   (config, comandos, rotas, policies, bindings DI)
├── Concerns/                  UsesPlannerateTenantDatabase, BelongsToConnection
├── Enums/                     ← 15 enums vindos de app/Enums (PlacementFailureReason, ShelfLevel,
│                                ZonePriority, CategoryRole, FlowDirection, SpaceFallback, ...)
├── Models/
│   ├── (físicos)             Planogram, Gondola, Section, Shelf, Segment, Layer, Product,
│   │                          Sale, MonthlySalesSummary, GondolaAnalysis, SegmentNote, Category*
│   ├── (templates/geração)   PlanogramTemplate, PlanogramSubtemplate, PlanogramTemplateSlot,
│   │                          PlanogramRejectedProduct, PlanogramProductRule, GondolaSlotOverride,
│   │                          ScoringWeights, ShelfLevelPreference, AdjacencyRule
│   └── Concerns/              HasCategory, cascades (DeletesGondolaGraph extraído do booted())
├── Policies/                  GondolaPolicy, PlanogramPolicy, PlanogramTemplatePolicy
├── Events/  Jobs/             (como hoje) + listener HandleLayerRemovedForRejectedProducts
├── Http/
│   ├── Controllers/
│   │   ├── Editor/            Gondola, Section, Shelf, Segment, Layer, SaveChanges, Category,
│   │   │                      PlanogramApi, ProductDimension, ProductSales, SegmentNote, Plannerate
│   │   ├── Generation/        AutoPlanogramController, GondolaSlotOverrideController,
│   │   │                      PlanogramProductRuleController
│   │   ├── Templates/         PlanogramTemplateController, TemplateSlotController
│   │   ├── Analysis/          GondolaAnalysisController, AnalysisExportController
│   │   ├── Export/            GondolaExportController, GondolaPdfPreviewController,
│   │   │                      GondolaShareController, GondolaTenantController
│   │   └── Api/               ProductDetailsController, ProductImageController
│   └── Requests/              (reorganizados por contexto, classes preservadas)
├── Services/
│   ├── Editor/                GondolaService, SectionService, ShelfService, SegmentService,
│   │                          LayerService, ProductService, ShelfStructureService,
│   │                          PlanogramChangeService, GondolaPayloadService
│   ├── Analysis/              AbcAnalysisService (dividido: Query + Calculator + Formatter),
│   │                          TargetStockService, PaperAnalysisService, AnalysisExportService
│   ├── Export/                GondolaPrintService, QRCodeService
│   └── (Repositories/ absorvidos pelos services — camada fina sem valor próprio)
└── AutoPlanogram/             ← app/Services/AutoPlanogram inteiro, subpastas 1:1
    ├── AutoGenerationRunner, AutoPlanogramService, ProductSelectionService, ...
    ├── DTO/  Scoring/  Placement/  Synthesis/  Template/  Validation/
```

Removidos (código morto confirmado): `src/Form/*`, `registerEditorPageRoutes()`.

### 2.2 Rotas (`routes/`)

```
routes/
├── editor.php       (como hoje — API do editor)
├── generation.php   ← rotas hoje em routes/tenant.php do app: auto-generate, rejected-products,
│                      reorder/redistribute, regenerate-auto, swap-product, generation-overrides,
│                      planogram-product-rules  (mesmas URIs/nomes)
├── templates.php    ← rotas planogram-templates/* (mesmas URIs/nomes)
├── export.php       (como hoje, incl. share público)
└── plannerate.php   (como hoje)
```

Registradas no provider com os mesmos middlewares atuais de cada grupo.

### 2.3 Frontend (`resources/js/`)

```
resources/js/
├── types/                     planogram.ts (+ tipos de snapshot/delta estritos, sem `any`)
├── stores/                    ← NOVO nome para o estado (continua composable singleton):
│   ├── editorState.ts         estado normalizado: gondola + Maps por id (sections, shelves,
│   │                          segments, layers) + índices derivados — substitui useGondolaState
│   │                          + useLookupHelpers + useReactivityHelpers
│   ├── selectionState.ts      seleção única/múltipla (contrato atual preservado)
│   └── uiState.ts             painéis, zoom, grid, zonas, busca EAN, categoria destacada
├── composables/
│   ├── core/                  usePlanogramEditor (fachada fina), usePlanogramChanges (delta/
│   │                          auto-save), usePlanogramHistory (undo/redo), useSnapshotManager
│   ├── dnd/                   ← NOVO módulo central de drag & drop: constantes MIME tipadas,
│   │                          useProductDrag, useSegmentDrag, useShelfDrag, useDropTargets
│   ├── operations/            section/shelf/segment ops (como hoje, tipados)
│   ├── keyboard/              usePlanogramKeyboard dividido por contexto (layer/shelf/section/global)
│   ├── geometry/  analysis/  products/  export/  fields/  shared/   (como hoje)
├── components/plannerate/
│   ├── PlanogramEditor.vue    ← unifica Planogram.vue + PlanogramAuto.vue (prop `mode`)
│   ├── Canvas.vue  editor/  header/  sidebar/  analysis/  print/  form/   (mesma divisão,
│   │                          componentes >400 linhas divididos em partials)
│   └── planogram-templates/   ← wizard de templates vindo de resources/js do app (D5)
└── libs/                      validation.ts, wayfinderPath.ts
```

## 3. Mapa de equivalência (antigo → novo)

### 3.1 Backend

| Antigo | Novo |
|---|---|
| `app/Services/AutoPlanogram/**` (59 arquivos) | `src/AutoPlanogram/**` — subpastas e nomes de classe 1:1, namespace `Callcocam\LaravelRaptorPlannerate\AutoPlanogram\...` |
| `app/Providers/AutoPlanogramServiceProvider` | bindings fundidos no provider do pacote |
| `app/Enums/{15}` | `src/Enums/{15}` (mesmos valores/labels) |
| `app/Models/{PlanogramTemplate, PlanogramSubtemplate, PlanogramTemplateSlot, PlanogramRejectedProduct, PlanogramProductRule, GondolaSlotOverride, ScoringWeights, ShelfLevelPreference, AdjacencyRule}` | `src/Models/{idem}` (mesmas tabelas/casts/fillable); app re-exporta via subclasse vazia enquanto houver consumidores externos (removidas na Fase 5) |
| `app/Http/Controllers/{AutoPlanogramController, GondolaSlotOverrideController, PlanogramProductRuleController}` | `src/Http/Controllers/Generation/{idem}` |
| `app/Http/Controllers/Tenant/{PlanogramTemplateController, TemplateSlotController}` | `src/Http/Controllers/Templates/{idem}` |
| rotas correspondentes em `routes/tenant.php` do app | `routes/generation.php` + `routes/templates.php` do pacote — URIs e nomes idênticos |
| `app/Listeners/Tenant/HandleLayerRemovedForRejectedProducts` | `src/Listeners/{idem}` (evento já é do pacote) |
| `src/Models/Editor/*` | `src/Models/*` (sobe um nível; FQCN antigo mantido como alias `class_alias` ou subclasse deprecated até a Fase 5) |
| `src/Repositories/Plannerate/*` | absorvidos pelos services correspondentes |
| `src/Services/Plannerate/*` | `src/Services/Editor/*` e `src/Services/Analysis/*` |
| `src/Form/*` | **removido** (morto) |
| `Gondola::booted()::deleting` cascade | `src/Models/Concerns/DeletesGondolaGraph` (mesma semântica) |

### 3.2 Frontend

| Antigo | Novo |
|---|---|
| `composables/plannerate/core/useGondolaState.ts` + `useLookupHelpers` + `useReactivityHelpers` | `stores/editorState.ts` (normalizado; lookups O(1); sem re-spread manual) |
| `core/usePlanogramSelection.ts` | `stores/selectionState.ts` (API pública idêntica) |
| `core/usePlanogramEditor.ts` (1.197 l) | fachada fina + operações nos módulos `operations/` |
| `interactions/useShelfDragDrop, useShelfDrag` + DnD inline em `Segment.vue`/`Section.vue` | `composables/dnd/*` (constantes MIME únicas, handlers tipados; componentes só ligam eventos) |
| `interactions/usePlanogramKeyboard.ts` (975 l) | `keyboard/{useLayerKeys, useShelfKeys, useSectionKeys, useGlobalKeys, useNumberBuffer}` — mesmos atalhos |
| `Planogram.vue` + `PlanogramAuto.vue` | `PlanogramEditor.vue` com prop `mode: 'manual' | 'generated'` |
| provide/inject de funções (`reloadProductsList`, `removeUsedProduct`) | ações no `productsPanel` composable/estado — sem function-refs por evento |
| `TransferSectionDialog.vue` (1.019 l), `GondolaCreateStepper.vue` (800 l), `Toolbar.vue` (791 l), `CategoryConfigPanel.vue` (627 l) | mesmos componentes divididos em partials (<300 l cada), comportamento idêntico |
| `resources/js/components/planogram-templates/*` (app) | `resources/js/components/plannerate/planogram-templates/*` (pacote) + alias atualizado |
| demais componentes/composables | 1:1, com tipos e docblocks PT-BR |

### 3.3 O que NÃO muda (contratos congelados)
Tabelas/colunas/FKs; URIs e nomes de todas as rotas; formato do payload do editor (`GondolaPayloadService`); formato do delta de `save-changes` (tipos de change, merge por `entityType_entityId`, ULIDs gerados no cliente); flash `capacity_report`/`explanation_report`; evento broadcast `.plannerate.gondola.product-images.updated`; chaves de tradução `plannerate.*`; chaves de localStorage; aliases `@plannerate`/`@/components/plannerate`/etc.

## 4. Passo a passo da implementação

> Branch única: `refactor/raptor-plannerate-v2` criada a partir de `dev`. Cada etapa termina com:
> `docker compose exec php vendor/bin/pint --dirty --format agent` → testes da etapa → suíte de regressão → commit.
> Frontend: `VITE_ENABLE_WAYFINDER=false npm run build` + smoke test no browser.

| Etapa | Conteúdo | Verificação |
|---|---|---|
| **0. Baseline** | branch nova; rodar suíte completa e registrar resultado (incl. crash pré-existente do CompositeScorerTest); congelar checklist §5 | suíte baseline documentada |
| **1. Esqueleto backend** | novo provider (rotas, policies, bindings DI fundidos), `src/Enums`, `src/Concerns`; remoção do código morto | testes de boot/rotas (`route:list` igual ao baseline) |
| **2. Models** | mover models Editor para `src/Models` (com aliases p/ FQCN antigo); trazer os 9 models do app (re-export no app); extrair `DeletesGondolaGraph` | `PlannerateEditorModelConnectionTest` + novos testes de tabela/casts/relacionamentos |
| **3. Services Editor** | reescrever `Services/Editor/*` absorvendo Repositories; `PlanogramChangeService` com testes de cada tipo de delta | novos Feature tests do save-changes (um por ChangeType) |
| **4. Services Analysis/Export** | `Services/Analysis/*` (ABC dividido), `Services/Export/*` | testes de paridade: mesmos inputs → mesmos outputs do baseline (fixtures) |
| **5. AutoPlanograma** | mover `app/Services/AutoPlanogram` → `src/AutoPlanogram` (namespace novo); atualizar imports dos 41 testes | **41 testes passam inalterados** (exceto imports) |
| **6. Controllers + rotas** | reorganizar controllers do pacote; trazer os 5 controllers do app; criar `routes/generation.php`/`templates.php`; remover rotas duplicadas do `routes/tenant.php` | `route:list` idêntico (URI+nome+middleware); Feature tests de endpoints |
| **7. Wayfinder/actions** | regenerar actions (`docker compose exec -u root php php artisan wayfinder:generate --with-form`); mover actions manuais para os novos caminhos; atualizar imports nos .vue | build verde; grep sem referências aos caminhos antigos |
| **8. Frontend: estado** | `stores/editorState` normalizado + `selectionState` + `uiState`; fachada `usePlanogramEditor` mantendo API pública; adaptar `useSnapshotManager`/`usePlanogramHistory`/`usePlanogramChanges` aos novos tipos | testes Vitest? — não há runner JS no projeto: validação via **Pest browser tests** (§6.3) + build + smoke manual |
| **9. Frontend: DnD + teclado** | módulo `dnd/` + `keyboard/` divididos; componentes ligados aos novos handlers | browser tests de drag/teclado + checklist manual §5 |
| **10. Frontend: componentes** | unificar `PlanogramEditor.vue`; dividir componentes gigantes; mover `planogram-templates/` para o pacote | build + browser smoke de todas as páginas |
| **11. Integração final** | atualizar imports restantes no app (controllers/commands/listeners); `composer dump-autoload`; rodar TUDO | suíte completa verde + checklist §5 completo |

Cada etapa é **independente e commitável**; se algo quebrar, o rollback é por etapa, não da refatoração inteira.

## 5. Plano de preservação de funcionalidades (checklist de aceite)

Executar integralmente nas etapas 9–11 (e após o merge). Itens numerados conforme inventário da Fase 1 §8:

**Editor (manual):**
- [ ] 1. Stepper de criação (7 passos) cria gôndola completa; modos manual/template/automático
- [ ] 2. Editar/excluir gôndola; navegar entre gôndolas
- [ ] 3. Seção: adicionar (sheet), editar, excluir, **duplicar estrutura/completa**, reordenar (Ctrl+←/→), **transferir para outra gôndola**, inverter prateleiras (Ctrl+I), bulk update
- [ ] 4. Prateleira: dblclick adiciona no furo; **drag com snap aos furos** (mesma seção e entre seções); Ctrl+setas; **Ctrl+D duplica com produtos**; excluir; editar; inverter segmentos; tipo gancho
- [ ] 5. Produto: **drag único/múltiplo**, dblclick; facings ←/→; **digitar número define quantidade**; camadas ↑/↓; **mover/copiar (Ctrl) segmento entre prateleiras**; **swap na mesma prateleira**; validação de largura; sem dimensão não arrasta
- [ ] 6. Seleção única/múltipla (Ctrl+Click); PanelRight contextual; dimensões individual/massa; upload de imagem; resumo de vendas
- [ ] 7. **Undo/redo** de cada tipo de operação; **auto-save 3s/threshold 10**; save manual Ctrl+S; toggle auto-save persiste
- [ ] 8. Zoom persiste por gôndola; grid; zonas térmicas; alinhamento; fluxo RTL
- [ ] 9. Busca EAN destaca; highlight bidirecional categoria↔prateleira
- [ ] 10. Atualizar imagens em lote → toast em tempo real (Echo) → reload protegido se houver mudanças
**Geração:**
- [ ] 11. Gerar por template e automático; auto-trigger pós-criação; backfill template_id
- [ ] 12. Regerar/Redistribuir/Reordenar; overrides por categoria (salvar/resetar/aplicar ao template)
- [ ] 13. Rejeitados: drawer lista/recoloca/troca/exclui; reload automático pós-remoções
- [ ] 14-17. ABC / Estoque alvo / Papel: cálculo, badges, listas, params, períodos, export CSV, persistência
**Export/Share:**
- [ ] 18. PDF preview/seleção de módulos/detalhe; 19. PNG, QR gôndola/seção, relatório; 20. **link público sem auth** + notas de segmento
**Templates (movidos):**
- [ ] CRUD template/subtemplate/slots, review, import/export XLSX, promote, clone, defaults de módulo (zonas/fluxo), critérios visuais drag-and-drop, limites de participação, regras mandatory/blocked

## 6. Plano de testes (Pest)

### 6.1 Regressão existente (rede de segurança)
- 41 arquivos `tests/Unit/Services/AutoPlanogram/*` + `tests/Feature/AutoPlanogram*` — passam com imports atualizados (etapa 5). Exceção documentada: `CompositeScorerTest` (crash pré-existente, ver memória do projeto).
- `PlannerateEditorModelConnectionTest`, `PlannerateProductImageControllerTest`, `QRCodeServiceTest`, `GondolaSlotOverrideTest` etc.

### 6.2 Novos testes backend (escritos ANTES de reescrever cada módulo)
- **Save-changes:** 1 Feature test por `ChangeType` (12) — delta aplicado corretamente, incluindo criação com ULID vindo do cliente, soft-delete e merge
- **Payload do editor:** snapshot test do `buildEditorPayload` (estrutura de chaves) contra fixture do baseline
- **Rotas:** teste que compara `route:list` (URI+nome+métodos+middleware) com manifesto congelado no baseline
- **Análises:** paridade input→output com fixtures geradas no baseline (ABC, estoque alvo, papel)
- **Models:** tabela, conexão tenant, casts, soft-deletes, cascade da gôndola

### 6.3 Browser tests (Pest 4) — novos, para o frontend crítico
- abrir editor, arrastar produto à prateleira, verificar segmento criado e auto-save disparado
- duplicar seção (completa) e desfazer (Ctrl+Z)
- mover prateleira entre seções com snap
- digitar quantidade num layer selecionado
- smoke de todas as páginas do domínio (editor, templates, share público) sem erros JS

## 7. Riscos e pontos de atenção

1. **Undo/redo + auto-save é o coração** — qualquer divergência sutil corrompe dados do usuário. Mitigação: tipos estritos de snapshot, testes por ChangeType antes da reescrita, browser tests de undo.
2. **Wayfinder regenerado apaga actions manuais** — etapa 7 concentra isso; conferir actions manuais (`reorderGondola`, overrides com helpers locais) após cada `wayfinder:generate`.
3. **Aliases Vite/tsconfig apontam para `vendor/` (symlink)** — mudanças de pasta dentro de `resources/js` do pacote exigem atualizar aliases no mesmo commit.
4. **`class_alias`/subclasses de compatibilidade** para FQCNs antigos dos models — devem emitir deprecation e ser removidos só na Fase 5.
5. **Rotas movidas do app para o pacote** podem colidir se registradas em duplicidade durante a transição — a etapa 6 remove do app no mesmo commit em que registra no pacote.
6. **Echo/broadcast e rota pública de share** têm semântica de segurança específica — não tocar em middleware/canais.
7. **Crash pré-existente do `CompositeScorerTest`** — não confundir com regressão; rodar suíte AutoPlanogram pulando `Scoring/` quando necessário.
8. **Migrations não se movem** — as já aplicadas ficam no app como histórico; o pacote ganha as novas no diretório `database/migrations/clients` para instalações futuras (sync). Nunca `migrate:fresh`.
9. **Tradução:** chaves novas seguem `plannerate.*` em `lang/pt_BR`; nenhuma string hardcoded em Vue/PHP (regra do projeto).

---

## 8. PARADA OBRIGATÓRIA

Este documento encerra a Fase 3. **A implementação (Fase 4) só começa após aprovação explícita deste plano** — incluindo as decisões D1–D8 da seção 0. Mudanças solicitadas serão incorporadas aqui antes de qualquer código.
