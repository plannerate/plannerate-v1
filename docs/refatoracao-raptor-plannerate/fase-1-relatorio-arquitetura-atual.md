# Fase 1 — Relatório de Arquitetura Atual do Pacote `laravel-raptor-plannerate`

> Data: 2026-06-11
> Escopo: estudo completo do pacote `packages/callcocam/laravel-raptor-plannerate` (somente leitura — nenhum código alterado).
> Próxima fase: Fase 2 — estudo do `app/Services/AutoPlanogram` e análise de dependências cruzadas.

---

## 1. Visão geral

| Métrica | Valor |
|---|---|
| PHP (src/) | 80 arquivos |
| Frontend (resources/js/) | 158 arquivos, **~34.500 linhas** |
| Migrations | 11 (em `database/migrations/clients/`) |
| Rotas | 3 arquivos (`editor.php`, `export.php`, `plannerate.php`) |
| Testes dentro do pacote | **0** (cobertura vive em `tests/` do projeto raiz) |
| Instalação | path repository → symlink em `vendor/callcocam/laravel-raptor-plannerate` |

O pacote contém **todo o editor visual de planograma** (frontend Vue + API backend), os **serviços de análise** (ABC, Estoque Alvo, Papel/BCG), **exportação** (PDF, PNG, CSV, QR Code, link público de compartilhamento) e os **models espelho** das entidades físicas (`gondolas → sections → shelves → segments → layers`).

**Já existe uma refatoração anterior documentada** em `packages/.../refatoração/` (2026-05-21), que reduziu o `usePlanogramEditor` de 2.700 → 1.197 linhas e criou a organização atual de composables por pastas (core/operations/interactions/geometry/analysis/...). A nova reconstrução parte desse estado, não do caos original.

---

## 2. composer.json do pacote

- **Nome:** `callcocam/laravel-raptor-plannerate` (`dev-main`, proprietário)
- **Namespace PSR-4:** `Callcocam\LaravelRaptorPlannerate\` → `src/`
- **Dependências:** `php ^8.2`, `spatie/laravel-package-tools`, `illuminate/contracts ^12|^13`, `inertiajs/inertia-laravel ^3`
- **Provider auto-discovery:** `LaravelRaptorPlannerateServiceProvider`
- ⚠️ Não declara dependências que de fato usa em runtime: `spatie/laravel-multitenancy` (middleware `NeedsTenant`), classes do `App\` (ver §9), `simplesoftwareio/simple-qrcode` (QRCodeService) etc. Funciona porque roda dentro do app — mas o contrato está implícito.

## 3. Service Provider

`LaravelRaptorPlannerateServiceProvider` (spatie/laravel-package-tools):

- `hasConfigFile('plannerate')` — defaults de criação de gôndola (dimensões, furos, prateleiras).
- `hasCommand(SyncPlannerateMigrationsCommand)` — `plannerate:migrations:sync` copia migrations do pacote para `database/migrations/clients` (migrations **não** são carregadas direto; são sincronizadas por cópia).
- Policies: `Gondola → GondolaPolicy`, `Planogram → PlanogramPolicy` (ambas usam `App\Policies\Concerns\ChecksRbacPermission` + `App\Support\Authorization\PermissionName`).
- Registra 3 grupos de rotas (ver §5). `registerEditorPageRoutes()` está **vazio** (método morto).
- **Não registra views, assets nem publishables** — o frontend é consumido direto de `vendor/.../resources/js` via aliases do Vite/tsconfig do projeto raiz (ver §7.1).

## 4. Models (src/Models/Editor) → tabelas

Todos com `HasUlids`, `SoftDeletes`, `BelongsToTenant` (do **app**) e `UsesPlannerateTenantConnection` (do pacote). Nenhum `$table` explícito — nomes derivados.

| Model | Tabela | Observações |
|---|---|---|
| `Planogram` | `planograms` | status/workflow, gondolas |
| `Gondola` | `gondolas` | `booted::deleting` faz cascade manual (sections→shelves→segments→layers, análises, workflow, rejeitados — usa models do app) |
| `Section` | `sections` | módulo físico |
| `Shelf` | `shelves` | `shelf_position` em cm |
| `Segment` | `segments` | coluna de produto na prateleira |
| `Layer` | `layers` | camada (produto × quantidade) |
| `Product` | `products` | espelho leitura do produto do app |
| `Category` | `categories` | trait `HasCategory` |
| `Sale` | `sales` | vendas brutas |
| `MonthlySalesSummary` | `monthly_sales_summaries` | agregado mensal |
| `GondolaAnalysis` | `gondola_analyses` | resultado persistido de análise ABC/estoque/papel |
| `SegmentNote` | `segment_notes` | notas de repositor (rota pública) |
| `Store`, `User`, `MercadologicoReorganizeLog` | — | apoio |

⚠️ **Duplicação estrutural:** o app tem seus próprios `App\Models\Gondola`, `Planogram`, `Sale` etc. apontando para as mesmas tabelas. Há dois models para a mesma tabela em vários casos (ex.: `EditorPlanogramController` do app sobrescreve `findGondolaOrFail` para devolver `App\Models\Gondola` em vez do model do pacote).

### Migrations (11)
`gondolas`, `layers`, `monthly_sales_summaries`, `planograms`, `products`, `sales`, `sections`, `segments`, `shelves`, `gondola_analyses`, `segment_notes`.

## 5. Rotas e Controllers (contratos públicos)

### 5.1 `routes/editor.php` — API do editor (middleware `web, auth, NeedsTenant, SetPermissionTeamContext`)

Grupo `api.` **sem** `tenant.client.redirect` (acessível a clientes/visualizadores):
- `POST api/editor/gondolas/{g}/analysis/abc|target-stock|paper`, `DELETE .../analysis` — `GondolaAnalysisController`
- `GET api/editor/gondolas/{g}/analysis/abc/export`, `.../stock/export` — `AnalysisExportController` (CSV)
- `GET|POST api/editor/segments/{s}/notes` — `SegmentNoteController`

Grupo `api.` **com** `tenant.client.redirect` (edição):
- Produtos: `GET api/products/details/{ean}`; `POST products/update-image`; `POST products/{p}/upload-image`; `DELETE products/{p}/delete-image`
- Gôndolas: `POST editor/planograms/{p}/gondolas`; `PUT|DELETE editor/gondolas/{g}`; `GET .../sections`; `GET plannograma/{p}/editor/gondolas/{g}/products`; `POST .../update-images`
- Categorias: `GET editor/categories`, `GET editor/{categoryId}/categories`
- Seções: `GET|POST|PUT|DELETE editor/sections...`; `POST editor/sections/{s}/transfer`
- Planogramas: `GET editor/planograms`, `GET editor/planograms/{p}/gondolas`
- Prateleiras: `POST editor/sections/{s}/shelves`; `PUT|DELETE editor/shelves/{id}`
- Segmentos: `PUT editor/segments/{id}`; Layers: `PUT|DELETE editor/layers/{id}`
- **`POST editor/gondolas/{g}/save-changes`** — `SaveChangesController` (invokable) → coração do auto-save delta
- Dimensões: `POST plannograma/{p}/products/{p}/dimensions`; Vendas: `GET plannerate/products/{p}/sales/summary`

⚠️ Typos no contrato: prefixo `plannograma` (sic) em 2 rotas.

### 5.2 `routes/export.php`
- `GET export/gondola/{g}/view` — preview de impressão (PDF via browser)
- `GET export/gondola/{g}/qr-code`, `.../section/{s}/qr-code`, `.../report`
- **`GET gondola/{gondolaId}/share` — rota PÚBLICA sem auth** (repositores/fornecedores) — `GondolaShareController`

### 5.3 `routes/plannerate.php`
- `GET tenant/gondola/{g}` e `.../section/{s}` — visualização tenant (`GondolaTenantController`)
- `GET /gondolas/{g}/analysis/abc|target-stock` — versão GET das análises

### 5.4 Services backend
- `GondolaService` (criar gôndola com estrutura completa a partir do stepper), `SectionService`, `ShelfService`, `SegmentService`, `LayerService`, `ProductService`, `ShelfStructureService`
- **`PlanogramChangeService`** — `processChanges()` aplica o array de deltas do frontend transacionalmente (dispatch por `type` para os services acima)
- **`GondolaPayloadService.buildEditorPayload()`** — monta o JSON gigante do editor (gôndola + sections + shelves + segments + layers + products + template_slots + generation_overrides)
- `AbcAnalysisService` (873 linhas), `TargetStockService`, `PaperAnalysisService` — análises por categoria/produto/EAN com pesos e cortes configuráveis
- `AnalysisExportService` (CSV), `GondolaPrintService`, `QRCodeService`
- Repositories (`src/Repositories/Plannerate/*`) — camada fina sobre os models
- Jobs: `ProcessProductImagesByEansJob`, `DOProcessProductImageJob` (busca/processa imagens de produto, DigitalOcean Spaces); Events: `GondolaProductImagesUpdated` (broadcast → Echo), `LayerRemovedEvent`

### 5.5 Código morto no backend
- `src/Form/GondolaModalForm.php` + `src/Form/Fields/MultiSelectField.php` — referenciam `Callcocam\LaravelRaptor\Support\Form\...`, **namespace que não existe** no projeto (fatal se instanciado). Nenhum uso encontrado. **Não migrar.**
- `LaravelRaptorPlannerateServiceProvider::registerEditorPageRoutes()` — vazio.

---

## 6. Frontend — inventário completo do editor (`resources/js/components/plannerate`)

### 6.1 Arquitetura de estado (reatividade)

**Não usa Pinia.** O estado é gerenciado por **composables com refs module-level (padrão singleton)**:

| Arquivo | Papel |
|---|---|
| `core/useGondolaState.ts` | Refs globais: `currentGondola`, estado de drag (`draggingShelfId`, `draggingSegmentShelfId`, `draggingShelfOffset`), `scaleFactor`, painéis, `showGrid`, `showZoneIndicators`, `eanSearchQuery`, `selectedTemplateCategoryId`, `rejectedProducts` |
| `core/usePlanogramEditor.ts` (1.197 l) | **Hub central** — orquestra todas as operações; cada mutação passa por `commitOptimistic` |
| `core/usePlanogramSelection.ts` | Dono único da seleção (única + múltipla via Ctrl+Click); `deleteSelected()` faz soft-delete otimista |
| `core/usePlanogramChanges.ts` | **Sistema delta/diff**: Map de mudanças pendentes por `entityType_entityId` com merge automático; **auto-save com debounce de 3s** (ou imediato com ≥10 mudanças); toggle de auto-save no localStorage; envia via `router.post` para `save-changes`; callbacks pós-save; `lastSaveHadRemovals` dispara reload de produtos rejeitados |
| `core/usePlanogramHistory.ts` | **Undo/redo** por pilha de snapshots tipados, persistida em localStorage |
| `operations/useSnapshotManager.ts` (806 l) | `commitOptimistic` (aplica mudança + captura before/after + grava histórico + agenda save) e `applySnapshot` (undo/redo por tipo: shelf/segment/section/layer/product/gondola, incluindo transfer e copy) |
| `core/useReactivityHelpers.ts` | Força reatividade por substituição de arrays (`section.shelves = [...]`, `currentGondola.value.sections = [...]`) — necessário porque o estado é um objeto gigante mutado em profundidade |
| `core/useLookupHelpers.ts` | Busca na árvore (`findShelfById`, `findSegmentById`, `findSegmentByLayerId`...) — varredura O(n) a cada chamada |

**Fluxo de toda edição:** componente → `editor.xxx()` → `commitOptimistic({apply, historySnapshot, change})` → mutação otimista local + snapshot p/ undo + delta p/ auto-save → backend `SaveChangesController` → `PlanogramChangeService` aplica.

### 6.2 Drag & Drop (HTML5 nativo, sem biblioteca)

| Interação | Origem → Destino | Mecanismo |
|---|---|---|
| **Produto → prateleira** | `sidebar/products/Card.vue` → `Shelf.vue` | `dataTransfer` com MIME customizado `application/x-product` (JSON do produto); só arrastável se `has_dimensions` |
| **Múltiplos produtos → prateleira** | seleção múltipla (Ctrl+Click) + drag | `application/x-products-multiple` + array JSON; só publicados entram |
| **Segmento → outra prateleira (mover)** | `Segment.vue` → `Shelf.vue` | `application/x-segment-id`; **Ctrl = copiar** (`application/x-is-copy`), overlay "Solte aqui / Ctrl para copiar" |
| **Segmento ↔ segmento (mesma prateleira)** | `Segment.vue` → `Segment.vue` | swap de posições (`swapSegmentPositions`) — drop zones do próprio segmento |
| **Prateleira ↕ dentro da seção** | `Shelf.vue` (base física) → `Section.vue` | drag com threshold de 2px (mousedown+mousemove), offset do clique preservado, **snap ao furo mais próximo** da cremalheira (`findNearestHole`), clamp nos limites |
| **Prateleira → outra seção** | idem, drop em outra `Section.vue` | `moveShelfToSection` com snap + transferência |
| **Produto rejeitado → prateleira** | `RejectedProductsDrawer.vue` | coloca a partir da lista de rejeitados (`placeFromRejected`) |

Estado de drag compartilhado por refs globais (`draggingShelfId`, `draggingSegmentShelfId`) — é assim que um drop target sabe se o segmento vem da mesma prateleira.

### 6.3 Atalhos de teclado (`interactions/usePlanogramKeyboard.ts`, 975 l)

Listener global único com ref-count. Por tipo selecionado:
- **Layer/Segmento:** `←/→` ±facing (com validação de largura via `validateShelfWidth`; `Shift` ignora validação); `↑/↓` ±camadas verticais; **digitar números 0-9** define quantidade (buffer com debounce 800ms + display visual); `Ctrl+←/→` troca posição com vizinho
- **Prateleira:** `Ctrl+↑/↓` move por furos; `Ctrl+←/→` move para seção adjacente; **`Ctrl+D` duplica prateleira completa (com produtos)**; `Ctrl+I` inverte ordem dos segmentos
- **Seção:** `Ctrl+←/→` reordena; **`Ctrl+D` abre modal de duplicação** (estrutura ou completa); `Ctrl+I` inverte prateleiras
- **Globais:** `Delete/Backspace` (com modal de confirmação opcional por tipo, preferência "não perguntar de novo" em localStorage); `Ctrl+Z` / `Ctrl+Shift+Z` / `Ctrl+Y`; `Ctrl+S`

### 6.4 Duplicar / copiar
- **Duplicar seção** (`DuplicateSectionDialog.vue` + `duplicateSection()`): modal com escolha "somente estrutura" × "completa (com produtos)"; gera ULIDs novos no cliente para toda a árvore, calcula `ordering` de inserção, registra como mudanças delta
- **Duplicar prateleira** (`Ctrl+D`): clona com segments/layers, posiciona abaixo da original (se couber)
- **Copiar segmento**: drag com Ctrl entre prateleiras
- IDs novos são **ULIDs gerados no frontend** (`ulid()`), backend cria com o mesmo ID via delta

### 6.5 Componentes do canvas (`editor/`)
`Canvas.vue` → `Sections.vue` → `Section.vue` (cremalheiras + furos + dblclick adiciona prateleira no furo) → `Shelves.vue` → `Shelf.vue` (área + base arrastável + zonas térmicas) → `Segment.vue` → `Layer.vue` (render do produto com imagem repetida por facing). Apoio: `Cremalheira.vue`, `AbcBadge.vue`, `PaperRoleBadge.vue`, `StockIndicator.vue` (indicador de estoque alvo por segmento), `RejectedProductsDrawer.vue` (drawer de rejeitados da geração automática, 407 l).

Geometria em `composables/geometry/`: `useSectionHoles` (furos da cremalheira + snap), `useShelfLayout` (posicionamento/zona/alinhamento), `useShelfAreaCalculation`, `useShelfZone` (zona quente/fria/neutra).

### 6.6 Modais e diálogos (inventário completo)

| Componente | Função |
|---|---|
| `editor/ConfirmDeleteDialog.vue` | confirma exclusão de section/shelf/layer (com "não perguntar de novo") |
| `editor/DuplicateSectionDialog.vue` | escolha estrutura × completa |
| `header/ConfirmDeleteGondolaDialog.vue` | excluir gôndola |
| `header/AutoGenerateModal.vue` | geração via template (auto-trigger por flash `auto_generate`) |
| `header/AutomaticGenerateModal.vue` | geração automática (ABC/score) |
| `header/TemplateGenerateModal.vue` | seleção de template p/ geração |
| `header/MapRegionSelectorModal.vue` | seleção de região no mapa da loja |
| `header/ShareQRCodeModal.vue` | QR Code + link público de compartilhamento |
| `header/partials/TransferSectionDialog.vue` (1.019 l!) | transferir seção para outra gôndola/planograma |
| `header/Performance.vue` + 3 tabs | modal de performance (ABC × Estoque Alvo × Papel) |
| `analysis/AbcParamsModal.vue` / `TargetStockParamsModal.vue` / `PaperParamsModal.vue` | parâmetros das análises (pesos, cortes, níveis de serviço, períodos) |
| `form/AddModuleSheet.vue` | sheet p/ adicionar módulo (seção) |
| `form/GondolaCreateStepper.vue` (800 l) + steps 1-6 + StepGeneration | wizard de criação de gôndola (básico, módulos, base, cremalheira, prateleiras, workflow, modo de geração) |
| `form/GondolaEditForm.vue` / `SectionShelfBulkUpdate.vue` | edição de gôndola, atualização em massa de seções/prateleiras |
| `print/PdfPreview.vue` + partials | preview/seleção de módulos p/ PDF (com `ProductDetailModal`) |

### 6.7 Sidebars
- **`sidebar/products/PanelLeft.vue`** — painel de produtos: busca, filtros, categoria em cascata (`CategorySelect.vue`), stats, paginação (`useProductsPanel`), cards arrastáveis; expõe `reloadProductsList`/`removeUsedProduct` via provide/inject por **function-refs passadas por evento** (padrão frágil)
- **`sidebar/properties/PanelRight.vue`** — propriedades contextuais da seleção: `SectionDetails`, `ShelfDetails`, `SegmentDetails`, `ProductDetails` (+ `ProductImageUpload`, `ProductDimensionsEditor`, `ProductSalesSummary`, `NoSelection`)
- **`sidebar/PanelLeftGeneration.vue`** — modos template/automático: Regerar/Redistribuir/Reordenar + overrides por categoria
- **`sidebar/CategoryConfigPanel.vue`** (627 l) — configuração por categoria com highlight bidirecional categoria ↔ prateleira

### 6.8 Header/Toolbar
`Header.vue` (título, status, tenant, usuários, voltar, atualizar imagens), `Toolbar.vue` (791 l): navegação entre gôndolas, zoom ±, grid, indicadores de zona térmica, alinhamento (left/right/center/justify), fluxo (LTR/RTL), undo/redo, save manual + indicador de mudanças, busca por EAN, filtro por categoria de template (`TemplateGroupingDropdown`), geração (template/automático), performance, exportações, excluir gôndola. `ToolbarDrawer.vue` = versão mobile (<xl). `DropdownActions.vue`, `DropdownPerformance.vue`, `Indicador.vue`.

### 6.9 Análises (frontend)
`analysis/`: `Abc.vue`, `AbcResultsList.vue` (575 l), `TargetStockResultsList.vue`, `PaperResultsList.vue`, painéis de seleção, `AnalysisPeriodSelector`, `TableHeadAnalysis`. Composables: `useAbcClassification` (Map por EAN), `useTargetStockAnalysis`, `usePaperAnalysis`, `usePerformanceIndicators`, `useAnalysisFilters`, `useAnalysisExport`, `useEanAnalysisStore`.

### 6.10 Impressão/Export
`print/`: `PdfPreview.vue`, `PdfModulePage.vue`, `PdfModuleSelector.vue` + partials (`PdfShelf`, `PdfSegment`, `PdfLayer`, `PdfSection`, `PdfCremalheira`, `PdfGondolaCanvas`, `PdfAbcBadge`, `PdfFlowIndicator`, `PdfPageFooter`, `ProductDetailModal`, `ProductDetailModalShare`). Composables `export/`: `usePdfGenerator` (jsPDF), `useCanvasCapture` (html2canvas-pro). Export PNG do canvas no editor.

### 6.11 Tempo real
`Planogram.vue` usa `useEcho` (`@laravel/echo-vue`) no canal privado do usuário para `.plannerate.gondola.product-images.updated` — toast + reload (com proteção se houver mudanças não salvas).

### 6.12 Páginas raiz do editor
- `Planogram.vue` — editor manual completo (PanelLeft + Canvas + PanelRight + keyboard + modais)
- `PlanogramAuto.vue` — variante p/ modos template/automático (PanelLeftGeneration no lugar do PanelLeft, provides no-op)
- Montados por `resources/js/pages/tenant/editor/Plannerate.vue` (app) conforme `generation_mode`

---

## 7. Integração com o projeto raiz

### 7.1 Build de assets
O pacote **não compila assets próprios**. O Vite do projeto raiz resolve aliases para dentro do vendor (symlink):
- `@/components/plannerate`, `@/composables/plannerate`, `@/types/planogram`, `@plannerate` → `vendor/callcocam/laravel-raptor-plannerate/resources/js/...`
- tsconfig inclui `vendor/.../resources/js/**` **e** `packages/.../resources/js/**`

Consequência: o frontend do pacote importa livremente código do app (`@/components/ui/*` shadcn, `@/composables/useT`, `@/actions/*` wayfinder) — acoplamento bidirecional total no frontend.

### 7.2 Quem usa o pacote (app → pacote): 98 arquivos
Destaques: `EditorPlanogramController`, `AutoPlanogramController`, `GondolaSlotOverrideController`, `App\Models\{Gondola,Planogram,Sale}`, `AutoPlanogram/*` (writer, engines, DTOs usam models do pacote), listeners/eventos, actions wayfinder geradas em `resources/js/actions/Callcocam/...`, e as páginas Vue (`Plannerate.vue`, `GondolaShare.vue`, `Kanban.vue`, `pdfPrintview.vue` etc.).

### 7.3 O que o pacote consome do app (pacote → app): ~25 arquivos PHP
- **Multi-tenancy/RBAC:** `App\Models\Traits\BelongsToTenant`, `App\Models\Tenant`, `App\Models\User`, `App\Http\Middleware\SetPermissionTeamContext`, `App\Support\Authorization\PermissionName`, `App\Policies\Concerns\ChecksRbacPermission`, `App\Support\Modules\{ModuleSlug,TenantModuleService}`
- **AutoPlanogram (!):** `GondolaController` do pacote chama `App\Services\AutoPlanogram\AutoGenerationRunner` + `AutoGenerateConfigDTO` — a Fase 2 resolve isso trazendo o serviço para dentro
- **Templates:** `App\Models\{PlanogramTemplate,PlanogramTemplateSlot}` (payload do editor inclui `template_slot`)
- **Workflow:** `App\Models\{WorkflowGondolaExecution,WorkflowHistory,WorkflowPlanogramStep}`, `App\Enums\WorkflowExecutionStatus`
- **Outros:** `App\Models\{PlanogramRejectedProduct,EanReference,Address}`, `App\Jobs\ProcessEanReferenceImageJob`, `App\Services\ProductRepositoryImageResolver`, `App\Events\Tenant\ProductImageProcessed`, `App\Notifications\AppNotification`

No frontend: componentes ui (shadcn), `useT`, actions wayfinder, `vue-sonner`, `ulid`, `@vueuse/core`, `lucide-vue-next`, `@laravel/echo-vue`.

---

## 8. Inventário de funcionalidades (checklist de preservação)

### Editor manual
1. Criar gôndola via stepper 6 passos + modo de geração (manual/template/automático)
2. Editar/excluir gôndola; navegação entre gôndolas do planograma
3. Adicionar módulo (seção) via sheet; editar seção (PanelRight); excluir; duplicar (estrutura/completa); reordenar (Ctrl+arrows); transferir seção p/ outra gôndola; inverter prateleiras; bulk update de seções/prateleiras
4. Adicionar prateleira (dblclick no furo); mover por drag com snap a furos (mesma seção e entre seções); mover por teclado; duplicar (Ctrl+D); excluir; editar propriedades; inverter segmentos; tipos normal/gancho ("hook")
5. Adicionar produto (drag único, drag múltiplo, dblclick na shelf selecionada); facings por teclado/número digitado; camadas verticais (↑/↓); mover/copiar/trocar segmentos; excluir layer/segmento; validação de largura; produto sem dimensão não arrastável
6. Seleção única e múltipla (Ctrl+Click); painel de propriedades contextual; edição de dimensões do produto (individual e em massa); upload/troca de imagem de produto; resumo de vendas do produto
7. Undo/redo completo com snapshots tipados (persistido em localStorage); auto-save delta com debounce/threshold; save manual; toggle de auto-save
8. Zoom (1-10, persistido por gôndola), grid, indicadores de zona térmica, alinhamento, fluxo LTR/RTL
9. Busca por EAN (highlight); filtro/highlight por categoria de template (bidirecional com CategoryConfigPanel)
10. Imagens de produto em lote (job + Echo em tempo real)

### Geração assistida (UI no pacote, motor no app — Fase 2)
11. Geração por template / automática (modais), auto-trigger pós-criação
12. PanelLeftGeneration: Regerar / Redistribuir / Reordenar + overrides por categoria + aplicar ao template
13. Drawer de produtos rejeitados: listar, recolocar (drag/ação), trocar, excluir; reload automático pós-save com remoções

### Análises
14. ABC (pesos/cortes configuráveis, badges no canvas, lista, export CSV)
15. Estoque alvo (níveis de serviço/cobertura, indicador visual por segmento, export CSV)
16. Papel/BCG (crescimento × participação, badge no canvas)
17. Período de análise configurável; persistência em `gondola_analyses`

### Exportação/Compartilhamento
18. Impressão PDF (preview, seleção de módulos, detalhe de produto)
19. Export PNG do canvas; QR Code de gôndola/seção; relatório
20. **Link público de compartilhamento** (sem auth) com visão de repositor + notas de segmento

---

## 9. Avaliação crítica — o que pode melhorar na reconstrução

### Backend
1. **Models duplicados app × pacote** para as mesmas tabelas — definir um dono único por entidade (ou contratos/interfaces), eliminando os espelhos.
2. **Dependências do app não declaradas/abstraídas** — formalizar via interfaces (auth/permissões, tenant, workflow, notificações) ou trazer para o pacote o que é do domínio planograma.
3. **Cascade delete manual no `booted()` de Gondola** acoplado a models do app — mover para um service de deleção com contrato claro.
4. **Migrations por cópia** (`migrations:sync`) — avaliar `loadMigrationsFrom` direto ou manter sync, mas documentado.
5. **Código morto:** `src/Form/*` (namespace inexistente), `registerEditorPageRoutes()` vazio.
6. Typos em rotas (`plannograma`) — manter por compatibilidade ou corrigir com documentação explícita no plano (mudança de contrato).
7. `AbcAnalysisService` com 873 linhas e responsabilidades múltiplas (query + cálculo + filtros + formatos) — separar.
8. **Zero testes no pacote** — a reconstrução deve nascer com suíte Pest própria (ou no projeto, mas organizada por domínio do pacote).

### Frontend
9. **Reatividade manual frágil**: `useReactivityHelpers` força reatividade re-espalhando arrays em 3 níveis a cada mutação. Causa: objeto gigante mutado em profundidade. Melhoria: estado normalizado (mapas por id) ou store estruturada (Pinia) com getters derivados — elimina a classe inteira de bugs "Vue não detectou".
10. **Lookups O(n) repetidos** (`findShelfById` etc. varrem a árvore toda a cada tecla) — índices por id resolvem.
11. **Singleton por module-refs**: dois editores na mesma página compartilhariam estado; refs globais dificultam teste. Uma store com escopo explícito (por gôndola) é mais segura.
12. **provide/inject de funções via eventos** (`reloadProductsList`, `removeUsedProduct` registradas por `@reload-function`) — substituir por store/composable direto.
13. **Duplicação `Planogram.vue` × `PlanogramAuto.vue`** (~350 linhas quase iguais) — unificar com slots/props.
14. **Tipagem fraca nos pontos críticos**: `any` em snapshots, operations e `record?: any` — o sistema de undo/redo é o lugar que mais precisa de tipos.
15. **Componentes gigantes**: `TransferSectionDialog` (1.019 l), `GondolaCreateStepper` (800 l), `Toolbar` (791 l), `CategoryConfigPanel` (627 l) — dividir.
16. **Drag & drop disperso**: lógica espalhada entre `Segment.vue` (inline), `useShelfDragDrop`, `useShelfDrag`, `Section.vue` (inline) com MIME types mágicos — centralizar num módulo de DnD com constantes e tipos.
17. **Snapshot de undo incompleto em alguns tipos** (`beforeState: null` para shelf adds, descrições hardcoded em PT no código em vez de `useT`).
18. **Duplicação de validação** (`validateShelfWidth` chamada em múltiplos pontos com mensagens montadas localmente).
19. `console.warn/error` como tratamento de erro em fluxos de usuário — padronizar toasts/i18n.
20. Conversões camel/snake manuais (`useSectionFields`/`useShelfFields`/`useGondolaFields`) — padronizar contrato de payload.

### Documentação interna existente
- `refatoração/` (análise de composables 2026-05-21, fases 00-07) e `docs/` (ABC, BCG, estoque alvo, fluxo automático) — insumos valiosos; a reconstrução deve absorver e substituir.

---

## 10. Riscos identificados para as próximas fases

1. **O frontend do pacote e do app são um único build** — qualquer mudança de alias/estrutura quebra os dois; o plano da Fase 3 precisa decidir se o frontend continua "fonte no pacote, build no app".
2. **Wayfinder**: actions geradas em `resources/js/actions/Callcocam/...` no app dependem dos FQCNs dos controllers do pacote — renomear namespace/classe quebra o frontend silenciosamente.
3. **Rota pública de share e rotas sem `tenant.client.redirect`** têm semântica de segurança específica — preservar exatamente.
4. **ULIDs gerados no cliente** fazem parte do contrato do save-changes (backend cria com id do front).
5. **Snapshot/undo e auto-save delta são o coração do editor** — qualquer reconstrução precisa de testes de equivalência comportamental antes de trocar a implementação.
