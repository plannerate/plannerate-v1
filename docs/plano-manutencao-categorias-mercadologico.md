# Plano — Manutenção do Mercadológico (Árvore de Categorias)

> **Status:** planejado · nada implementado ainda
> **Branch:** `feat/manutencao-categorias-mercadologico`
> **Criado em:** 2026-07-08
> **Base:** `app/Models/Category.php`

Documento vivo. Marcar os checkboxes conforme cada etapa for concluída e validada.

---

## 1. Objetivo

Criar uma tela de **manutenção do mercadológico** (categorias) com:

1. **Árvore hierárquica** de categorias (raiz → folhas), com expandir/colapsar.
2. **Drag & drop de categoria → categoria**: arrastar um nó reparenta a subárvore
   inteira. Filhos e modelos relacionados (`Product`, `Planogram`) acompanham
   automaticamente porque referenciam a categoria por `category_id` (FK), não por
   caminho textual.
3. **Modais de produtos por categoria** (várias abertas ao mesmo tempo, uma por
   categoria) para inspecionar os produtos vinculados.
4. **Mover produtos** de uma categoria para outra (seleção múltipla → destino),
   inclusive arrastando entre duas modais abertas.

A tela fica **a princípio no landlord**, operando sobre um tenant escolhido
(`tenants/{tenant}/mercadologico`), seguindo o precedente do
`WorkflowTemplateController`. A camada de serviço é escrita de forma reutilizável
para, no futuro, expor a mesma funcionalidade dentro do tenant sem reescrever a
lógica.

---

## 2. Descobertas técnicas (estado atual do código)

| Item | Achado | Impacto no plano |
|------|--------|------------------|
| `Category` | Modelo **tenant** (`BelongsToTenant`, `UsesTenantConnection`, `HasUlids`, `SoftDeletes`). Auto-referência via `category_id`; `children()`/`parent()` com late static binding. | Página landlord precisa entrar no contexto do tenant (`makeCurrent`) para ler/gravar. |
| Campos denormalizados | `nivel`, `level_name`, `hierarchy_position`, `full_path` (texto `A > B > C`), `hierarchy_path` (JSON array de nomes). | **Mover um nó exige recalcular esses campos no nó e em TODOS os descendentes.** É o cerne da complexidade. |
| `Product` | Usa trait `HasCategory` → `category_id` aponta para a categoria-folha. | Mover produto = trocar `product.category_id`. Subárvore movida não altera FK dos produtos. |
| `Product` cache | `HasCategory` faz `cache()->remember("hierarchy_path:{id}")` e `"mercadologico_cascading:{id}")` por 7200s, keyed pelo `category_id`. | **Mover categoria invalida esses caches** para os IDs da subárvore. Precisa flush. |
| `Planogram` | `belongsTo(Category::class)` via `category_id`. | FK não muda ao reparentar; nada a fazer além de recomputar denormalização da categoria. |
| Precedente landlord→tenant | `Landlord\WorkflowTemplateController` usa `runInTenantContext($tenant, fn)` que faz `makeCurrent()` + restaura conexão no `finally`. Rotas `tenants/{tenant}/kanban/...`. Autoriza com `$this->authorize('update', $tenant)`. | **Copiar exatamente esse padrão** (extrair o helper para um trait/concern compartilhado). |
| Drag & drop | Projeto **não tem lib de drag** (sem vuedraggable/sortablejs). O Kanban usa **HTML5 DnD nativo** (`draggable`, `@dragstart/@dragover/@drop`) + composable de estado (`useKanbanMove`). | **Reusar DnD nativo.** Não adicionar dependência (CLAUDE.md proíbe sem aprovação). |
| UI disponível | `reka-ui` + componentes em `components/ui/`: `dialog`, `sheet`, `scroll-area`, `collapsible`, `checkbox`, `badge`, `card`, `button`. Sem componente de árvore. | Árvore recursiva construída à mão; modais via `dialog`; produtos via `scroll-area` + `checkbox`. |
| Cascata existente | `CategoryController@cascadeChildren` / `@cascadePath` já servem filhos/caminho por nível (lazy). | Reaproveitável como referência; a árvore nova pode carregar lazy por nó grande. |
| Recompute de path | `CategoryHierarchyImportService::resolveHierarchy` já monta `full_path`/`hierarchy_position`/`nivel` na importação. | Extrair a fórmula para um serviço único reutilizado por importação **e** por move. |
| Níveis | `HasCategory::HIERARCHY_LEVELS` (1..8: Segmento varejista → Atributo). UI mercadológica limita a 7 (`CategoryController::MERCADOLOGICO_UI_LEVELS`). | Validar profundidade máxima ao mover (não deixar subárvore ultrapassar o limite). |

---

## 3. Decisões de arquitetura

1. **Localização:** landlord, rota `tenants/{tenant}/mercadologico`. Controller
   `Landlord\CategoryTreeController`. Toda leitura/escrita dentro de
   `runInTenantContext`.
2. **Autorização:** `$this->authorize('update', $tenant)` (mesma regra do
   WorkflowTemplate). A `CategoryPolicy` existente é tenant-scoped; no landlord a
   porta de entrada é a policy do `Tenant`.
3. **Drag & drop nativo** (padrão Kanban). Estado em composable
   `useCategoryTree` / `useCategoryDrag`.
4. **Reparent é uma operação atômica** (`DB::transaction`) que:
   - valida (sem ciclos, sem exceder profundidade, destino ≠ próprio nó/descendente);
   - atualiza `category_id` do nó arrastado;
   - **recomputa denormalização** (`nivel`, `level_name`, `hierarchy_position`,
     `full_path`, `hierarchy_path`) no nó + todos os descendentes;
   - invalida caches de `HasCategory` para os IDs afetados.
5. **Mover produtos** é uma operação separada e simples: atualiza
   `product.category_id` em lote (validando que o destino é uma categoria válida,
   idealmente folha) + flush de cache dos produtos movidos.
6. **Serviço reutilizável:** a lógica de recompute vive em
   `CategoryHierarchyService` (novo), consumido pelo move **e** refatorando o
   import para usá-lo (opcional, fase posterior — evitar regressão no importador).
7. **Respostas Inertia:** `Inertia::render` para a página; mutações retornam
   `back()` com `Inertia::flash('toast', ...)` (padrão do projeto).

---

## 4. Modelo de dados / regras de recompute

Ao reparentar o nó `N` sob o novo pai `P` (ou raiz, se `P = null`):

```
novo_nivel(N)      = P ? P.nivel + 1 : 1
level_name(N)      = rótulo do nível por posição (HIERARCHY_LEVELS[novo_nivel])
hierarchy_position(N) = novo_nivel
full_path(N)       = (P ? P.full_path + ' > ' : '') + N.name
hierarchy_path(N)  = (P ? P.hierarchy_path : []) concat [N.name]
```

E recursivamente para cada descendente `D` (BFS/DFS a partir de `N`), usando o pai
já recomputado. Reaproveitar `Category::getDescendantIds()` para coletar a
subárvore num único conjunto de IDs e processar em ordem topológica (nível a
nível), gravando em lote.

**Validações do move:**
- Destino não pode ser o próprio nó nem um de seus descendentes (evita ciclo).
- `profundidade(N) + altura(subárvore de N) ≤ 7` (limite mercadológico UI).
- Nó e destino existem e pertencem ao tenant.

**Invalidação de cache** (para cada `id` na subárvore movida):
`cache()->forget("hierarchy_path:{id}")` e `cache()->forget("mercadologico_cascading:{id}")`.
Como o cache de produto é keyed pelo `category_id` do produto (folhas), basta
esquecer as chaves de todos os IDs da subárvore.

---

## 5. Backend — arquivos a criar/alterar

### 5.1 Rotas — `routes/landlord.php`
```php
Route::get('tenants/{tenant}/mercadologico', [Landlord\CategoryTreeController::class, 'index'])
    ->name('landlord.tenants.mercadologico.index');
// Lazy: filhos de um nó (parent=null => raízes). Alimenta o expand da árvore.
Route::get('tenants/{tenant}/mercadologico/children', [Landlord\CategoryTreeController::class, 'children'])
    ->name('landlord.tenants.mercadologico.children');
Route::get('tenants/{tenant}/mercadologico/{category}/products', [Landlord\CategoryTreeController::class, 'products'])
    ->name('landlord.tenants.mercadologico.products');
Route::post('tenants/{tenant}/mercadologico/{category}/move', [Landlord\CategoryTreeController::class, 'move'])
    ->name('landlord.tenants.mercadologico.move');
Route::post('tenants/{tenant}/mercadologico/move-products', [Landlord\CategoryTreeController::class, 'moveProducts'])
    ->name('landlord.tenants.mercadologico.move-products');
```

### 5.2 Concern compartilhado — `app/Http/Controllers/Concerns/RunsInTenantContext.php` (novo)
Extrair `runInTenantContext()` + `resolveTenantConnectionName()` do
`WorkflowTemplateController` (que hoje os tem privados) para reuso. Depois fazer o
WorkflowTemplateController passar a usar o trait (refactor sem mudança de
comportamento).

### 5.3 Controller — `app/Http/Controllers/Landlord/CategoryTreeController.php` (novo)
- `index(Request, Tenant)` → `runInTenantContext` carrega **só as raízes** (nível 1,
  ver 5.5) e renderiza `landlord/tenants/mercadologico/Index` com
  `{ tenant, roots, filters }`.
- `children(Request, Tenant)` → JSON dos filhos diretos de `?parent_id=` (ou raízes
  se vazio), cada nó com `children_count`/`products_count`. Alimenta o lazy expand.
  Usar `useHttp` (Inertia v3) no front — é polling de dados sem navegação.
- `products(Request, Tenant, string $category)` → JSON/partial dos produtos da
  categoria (paginado ou completo com busca) para a modal. Usar `useHttp` no front
  ou prop deferida; decisão em 7.2.
- `move(MoveCategoryRequest, Tenant, string $category)` → chama
  `CategoryTreeService::move(...)`, retorna `back()` + toast.
- `moveProducts(MoveProductsRequest, Tenant)` → chama
  `CategoryTreeService::moveProducts(...)`, retorna `back()` + toast.
- Autorização `update` no `Tenant` em todos.

### 5.4 Form Requests (novos, em `app/Http/Requests/Landlord/`)
- `MoveCategoryRequest`: `target_category_id` (`nullable`, existe no tenant),
  `authorize()` = `update` no tenant.
- `MoveProductsRequest`: `product_ids` (`array`, `required`), `product_ids.*`
  (`string`), `target_category_id` (`required`, existe).

> ⚠️ Validar existência **dentro do contexto do tenant**. Regras `exists:` usam a
> conexão default; usar `Rule::exists` com conexão `tenant` **após** `makeCurrent`,
> ou validar manualmente no serviço. Preferir validação no serviço (mais seguro no
> cenário multi-DB).

### 5.5 Serviço — `app/Services/Categories/CategoryTreeService.php` (novo)
- `nodesForParent(?string $parentId): array` — filhos diretos de `$parentId` (ou
  raízes se `null`), cada um como `{ id, name, level_name, nivel, status,
  is_placeholder, children_count, products_count }`. Usado pelo `index` (raízes) e
  pelo `children` (expand lazy). Evitar N+1: `withCount(['children'])` +
  contagem de produtos agregada por `category_id`.
- `move(string $categoryId, ?string $targetId): void` — transação: valida →
  atualiza `category_id` → recomputa denormalização da subárvore → flush cache.
- `moveProducts(array $productIds, string $targetId): int` — valida destino →
  `Product::whereIn('id', ...)->update(['category_id' => $targetId])` → flush cache
  dos produtos → retorna qtd movida.

### 5.6 Serviço de hierarquia — `app/Services/Categories/CategoryHierarchyService.php` (novo)
Fórmula da §4 isolada e testável: `recomputeSubtree(Category $root): void`.
(Opcional/fase 6) refatorar `CategoryHierarchyImportService` para usá-lo.

### 5.7 Traduções — `lang/pt_BR/app/landlord/...` (ver §7 do CLAUDE sobre split)
Chaves `app.landlord.mercadologico.*` (título, colunas, mensagens de toast,
confirmações, erros de validação de move).

---

## 6. Frontend — arquivos a criar

Diretório: `resources/js/pages/landlord/mercadologico/`

| Arquivo | Papel |
|---------|-------|
| `Index.vue` | Página. `AppLayout` + breadcrumbs + header. Recebe props `{ tenant, roots }`. Orquestra árvore + modais abertas. |
| `components/CategoryTree.vue` | Árvore raiz; renderiza lista de `CategoryTreeNode` a partir de `roots`. |
| `components/CategoryTreeNode.vue` | Nó **recursivo** com **expand lazy**: ao abrir pela 1ª vez, busca filhos via `children` (`useHttp`), com skeleton enquanto carrega; `collapsible` p/ estado aberto/fechado; `:draggable`, handlers `@dragstart/@dragover/@drop`, botão "ver produtos" que abre modal. |
| `components/CategoryProductsModal.vue` | Modal (`dialog`) por categoria: lista produtos (`scroll-area` + `checkbox`), busca, seleção múltipla, botão "mover para…". Suporta ser alvo de drop de produtos. |
| `components/MoveProductsBar.vue` | (opcional) barra/painel de destino ao mover produtos entre modais. |

Composables (`resources/js/pages/landlord/mercadologico/composables/` ou
`resources/js/composables/`):
| Composable | Papel |
|------------|-------|
| `useCategoryTree.ts` | Estado da árvore lazy: mapa `id → { node, children, loaded, expanded }`, `loadChildren(parentId)` (via `useHttp` no endpoint `children`), `expand/collapse`, e `refreshNode(id)` p/ recarregar filhos de um nó após um move. |
| `useCategoryDrag.ts` | Estado do drag de categoria (nó arrastado, alvo, `canDrop` = alvo não é o próprio nó nem descendente já carregado — validação final fica no backend). Ao soltar: confirma (contagem de impacto) → `router.post(move.url(...))` com `preserveScroll`, `onSuccess` recarrega os nós de **origem** e **destino** via `refreshNode`. |
| `useOpenProductModals.ts` | Gerencia N modais abertas (uma por categoria), cada uma com seus produtos e seleção. |

**Padrões obrigatórios (CLAUDE.md):**
- `defineProps<{...}>()` com interfaces TS explícitas.
- `useT()` para todas as strings — zero texto hardcoded.
- Mutações via `router` do Inertia (`router.post(...)`), **nunca** axios/fetch.
  Exceção: carregar produtos da modal sob demanda pode usar `useHttp` (Inertia v3).
- Dimensões de produto, se exibidas: ordem **Altura, Largura, Profundidade**.
- Wayfinder: **não** rodar `wayfinder:generate` (memória do projeto). Escrever o
  arquivo de actions manualmente OU usar `route()` nomeada. Confirmar em 7.3.

**UX do drag de categoria:**
- Nó arrastável destaca-se; alvos válidos realçam no `dragover`; alvos inválidos
  (próprio/descendente) mostram cursor "not-allowed".
- Ao soltar: modal de confirmação com **contagem de impacto** ("Mover *X* e seus
  *N* descendentes / *M* produtos para *Y*?") antes do `router.post` (decisão §7.3).
  A contagem vem de `children_count`/`products_count` já carregados no nó (ou de uma
  chamada rápida ao backend se for preciso o total recursivo).
- `preserveScroll: true`; `onSuccess` recarrega os filhos do nó de **origem** e do
  nó de **destino** via `refreshNode` (não recarrega a árvore inteira — é lazy) + toast.

**UX mover produtos (várias modais):**
- Abrir modal da categoria A e da categoria B simultaneamente.
- Selecionar produtos em A (checkbox) → arrastar seleção para B **ou** botão
  "mover para…" com autocomplete de categoria.
- `router.post(moveProducts.url(), { product_ids, target_category_id })`.
- On success: remover itens movidos da modal de origem, refletir na de destino,
  atualizar `products_count` na árvore, toast.

---

## 7. Decisões (confirmadas em 2026-07-08)

1. **Localização:** ✅ **Landlord + seletor de tenant** (`tenants/{tenant}/mercadologico`,
   padrão `WorkflowTemplateController`).
2. **Carga da árvore:** ✅ **Lazy por nó.** O `index` carrega só as raízes (nível 1)
   com `children_count`/`products_count`; ao expandir um nó, busca os filhos via
   endpoint dedicado. Ver §5.1/§5.5/§6 (desenho já ajustado para lazy).
3. **Confirmação ao mover categoria:** ✅ **Sim, com contagem de impacto** — modal
   "Mover *X* e seus *N* descendentes / *M* produtos para *Y*?" antes do `router.post`.
4. **Mover produto:** ✅ **Qualquer nível, com aviso.** Permite soltar em qualquer
   categoria; alerta (não bloqueia) se o destino não for folha.

Decisões menores mantidas como recomendação (revisar na implementação):
- **Produtos na modal:** paginado com busca (categorias-folha podem ter muitos SKUs).
- **Wayfinder:** não gerar; usar `route()` nomeada no front (rotas landlord já nomeadas).
- **Placeholder/trashed:** mostrar `is_placeholder` marcado visualmente; ocultar
  trashed por padrão com toggle.

---

## 8. Testes (Pest — `docker compose exec php php artisan test`)

Feature tests em `tests/Feature/Landlord/`:
- `CategoryTreeIndexTest` — landlord autorizado vê a árvore do tenant; não
  autorizado → 403.
- `MoveCategoryTest`:
  - reparent simples atualiza `category_id`;
  - recomputa `full_path`/`nivel`/`hierarchy_path` do nó **e descendentes**;
  - bloqueia mover para descendente (ciclo) → erro de validação;
  - bloqueia exceder profundidade 7;
  - produtos/planogramas da subárvore continuam vinculados (FK intacta);
  - cache de `HasCategory` invalidado.
- `MoveProductsTest` — move N produtos entre categorias; contagem correta;
  destino inválido → erro.

Unit test:
- `CategoryHierarchyServiceTest` — `recomputeSubtree` produz path/nível corretos em
  árvore de 3+ níveis.

> **Gotchas de teste multi-tenant** (memória do projeto): chamar
> `$tenant->makeCurrent()` antes de cada request simulada; `assertDatabaseHas` com
> 3º arg `'tenant'`; validar isolado (suíte completa é flaky pré-existente).

---

## 9. Fases de implementação (checklist)

### Fase 0 — Base
- [x] Criar branch `feat/manutencao-categorias-mercadologico`
- [x] Escrever este plano
- [ ] Confirmar decisões em aberto (§7) com o usuário

### Fase 1 — Backend núcleo ✅ (implementada; testes verdes)
- [x] `CategoryHierarchyService` + teste unitário
- [x] `CategoryTreeService` (`nodesForParent` lazy, `move`, `moveProducts`)
- [x] Concern `RunsInTenantContext` (extraído do WorkflowTemplateController, que passou a usá-lo)
- [x] Form Requests `MoveCategoryRequest`, `MoveProductsRequest`
- [x] `Landlord\CategoryTreeController`
- [x] Rotas em `routes/landlord.php`
- [x] Traduções `app.landlord.mercadologico.*` (`lang/pt_BR/app/landlord/mercadologico.php`)
- [x] Feature tests (index, children, move, move-descendant, move-products) + unit (8) — **13 verdes, 54 assertions**
- [x] `vendor/bin/pint --dirty`

#### Notas de implementação (Fase 1) — gotchas descobertos
- **Teste de contexto tenant com sqlite `:memory:`:** chamar `$tenant->makeCurrent()`
  dispara o `SwitchTenantDatabaseTask`, que **purga** a conexão `tenant` e apaga o
  schema `:memory:` recém-migrado. Solução (mesmo padrão do teste de Kanban): bindar
  o tenant direto com `app()->instance('currentTenant', $tenant)` — assim o
  `makeCurrent()` do controller vira no-op (Spatie pula quando `isCurrent()`) e os
  dados sobrevivem. Consequência: o caminho real de *switch* de banco não é
  exercitado em teste (só em pgsql/produção).
- **Autorização nos testes:** com o tenant vinculado como corrente, o contexto deixa
  de ser "landlord" e `allowByContext` passa a exigir RBAC (team-scoped). Como estes
  testes focam o comportamento do mercadológico (a linha `authorize('update', $tenant)`
  já é coberta por WorkflowTemplate/Impersonation), desabilitei RBAC neles
  (`permission.rbac_enabled = false`). Um teste dedicado de 403 pode ser somado depois.
- **Render do `index` sem frontend buildado:** usar `$this->withoutVite()` +
  `->component('...', false)` (2º arg pula a checagem de existência do arquivo Vue),
  pois o componente só entra no manifest do Vite após a Fase 2/build.
- **Pint:** o fixer `fully_qualified_strict_types` importou classes citadas em `@see`
  nos docblocks dos serviços (inclusive um `use` de controller só para documentação) —
  inofensivo, é só referência de doc.

### Fase 2 — Frontend árvore + drag ✅ (implementada; lint + build verdes)
> **Reestruturação importante:** os componentes/composables/tipos foram extraídos
> para **`resources/js/components/mercadologico/`** (reutilizáveis, com URLs
> **injetáveis** via `MercadologicoUrls`), e a página landlord virou fina. Assim
> uma futura **página de tenant** só monta `<MercadologicoManager :urls :roots>`.
- [x] `components/mercadologico/CategoryTree.vue` + `CategoryTreeNode.vue` (recursivo, expand **lazy** c/ skeleton)
- [x] `components/mercadologico/useCategoryTree.ts` (estado lazy, `ensureLoaded`/`refresh` via `useHttp`)
- [x] `components/mercadologico/useCategoryDrag.ts` (DnD nativo, guard de ciclo/self/pai)
- [x] `components/mercadologico/MercadologicoManager.vue` (orquestrador reutilizável) — confirmação com contagem de impacto + `router.post` (preserveState) + `refresh` origem/destino
- [x] `pages/landlord/tenants/mercadologico/Index.vue` (fina) + `routes.ts` (factory `landlordMercadologicoUrls`)
- [x] Link de navegação em `pages/landlord/tenants/Index.vue`

### Fase 3 — Modais de produtos ✅ (implementada)
- [x] `components/mercadologico/CategoryProductsModal.vue` (lista paginada, busca debounced, seleção múltipla)
- [x] N modais simultâneas (estado `openModals` no `MercadologicoManager`)
- [x] Endpoint/carregamento de produtos por categoria (`products` + `useHttp`)
- [x] Mover produtos entre categorias (botão "mover para…" com destinos = outras modais abertas)
- [x] Atualização otimista de contagens (`adjustProductsCount`) + remount da modal de destino

### Fase 4 — Validação
- [x] Build via `VITE_ENABLE_WAYFINDER=false npm run build` (✓ built; página no manifest)
- [x] Lint dos arquivos novos (eslint --fix, verde)
- [x] Testes backend isolados (13 verdes)
- [ ] **Validação manual no browser** (tenant de teste `alberti`) — pendente do usuário
- [ ] Typecheck global (`vue-tsc`) — não roda neste ambiente (OOM); coberto pelo build

### Fase 5 — Pós-merge (opcional)
- [ ] Refatorar `CategoryHierarchyImportService` para usar `CategoryHierarchyService`
- [ ] Avaliar expor a mesma tela dentro do tenant

---

## 10. Riscos & atenção

- **Recompute em massa:** subárvores grandes → muitos UPDATEs. Fazer em transação e
  em lote (`upsert`/`update` por nível). Considerar job em background se passar de
  alguns milhares de nós.
- **Cache stale:** esquecer de invalidar `hierarchy_path:*` / `mercadologico_cascading:*`
  deixa produtos exibindo caminho antigo. Cobrir em teste.
- **Contexto de tenant no landlord:** toda query fora de `runInTenantContext` cai no
  banco errado. Regras `exists:` de FormRequest rodam **fora** do contexto — validar
  no serviço.
- **Profundidade:** import usa até 8 níveis, UI limita a 7. Alinhar o limite do move
  com `MERCADOLOGICO_UI_LEVELS`.
- **Dependência de drag:** não instalar lib nova; usar DnD nativo (padrão Kanban).
- **Wayfinder:** não rodar o gerador (memória); usar `route()` nomeada.
