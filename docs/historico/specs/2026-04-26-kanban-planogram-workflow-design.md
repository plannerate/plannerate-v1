# Kanban de Workflow de Planogramas

**Data:** 2026-04-26
**Status:** Implementado em fases; atualizado com refinamentos de 2026-04-27

## Contexto

O sistema já tem infraestrutura completa de workflow (templates, steps por planograma, execuções por gôndola, histórico), mas a interface kanban estava dividida entre uma página funcional abandonada (`pages/tenant/kanban/Index.vue`) e uma placeholder em branco (`pages/tenant/planograms/Kanban.vue`). O objetivo é consolidar tudo em `planograms/Kanban.vue`, componentizar em `resources/js/components/kanban/`, e entregar a experiência visual do `exemplo.html`.

## Abordagem

Migrar & componentizar (Option A): aproveita a lógica de negócio já testada nos controllers/services e extrai tudo em componentes reutilizáveis.

---

## Arquitetura

### O que muda

| Item | Ação |
|---|---|
| `WorkflowKanbanController` | Mantido — muda render target + adiciona stores/users nos props |
| `WorkflowExecutionController` | Mantido sem mudança |
| `WorkflowPlanogramStepController` | Mantido sem mudança |
| `WorkflowKanbanService` | Mantido — adiciona `planogram_name` e `step_name` na execução |
| `PlanogramController::kanban()` | Simplificado — redireciona para `kanban.index` |
| `pages/tenant/kanban/Index.vue` | Removido — lógica migra para componentes |
| `pages/tenant/planograms/Kanban.vue` | Nova home do kanban |
| `resources/js/components/kanban/` | Nova pasta com todos os componentes |

### Fluxo de dados

```
WorkflowKanbanController (index / show)
  → Inertia render: tenant/planograms/Kanban
    props: planograms, stores, users, board, selected_planogram, subdomain

pages/tenant/planograms/Kanban.vue  (thin page)
  ↳ useKanban composable
  ↳ KanbanFilters.vue
  ↳ KanbanBoard.vue
      ↳ KanbanColumn.vue
          ↳ KanbanCard.vue
  ↳ KanbanCardDetail.vue  (Dialog)
```

---

## Backend

### Arquivos críticos

- `app/Http/Controllers/Tenant/WorkflowKanbanController.php`
- `app/Http/Controllers/Tenant/PlanogramController.php`
- `app/Services/WorkflowKanbanService.php`
- `routes/web.php` (tenant section)

### Mudanças

**Rotas** — unificar `kanban.index` + `kanban.show` em uma única rota GET com query params:
```php
// Antes:
Route::get('kanban', [WorkflowKanbanController::class, 'index'])->name('kanban.index');
Route::get('kanban/{planogram}', [WorkflowKanbanController::class, 'show'])->name('kanban.show');

// Depois:
Route::get('kanban', [WorkflowKanbanController::class, 'index'])->name('kanban.index');
// kanban.show removida
```

**`WorkflowKanbanController::index(Request $request)`** — unifica as duas actions:
- Render target: `tenant/kanban/Index` → `tenant/planograms/Kanban`
- Lê `$request->planogram_id` (opcional) para carregar o board
- Props adicionados: `stores`, `users`, `filters` (valores atuais para preencher o form)
```php
'stores'   => Store::query()->orderBy('name')->get(['id', 'name']),
'users'    => User::query()->orderBy('name')->get(['id', 'name']),
'filters'  => $request->only(['planogram_id', 'store_id', 'user_id', 'status']),
```
- Remove método `show()` (lógica migra para `index()`)

**`PlanogramController::kanban()`** — redireciona:
```php
public function kanban(): RedirectResponse
{
    return redirect()->route('kanban.index');
}
```

**`WorkflowKanbanService::buildBoardForPlanogram()`** — Fase 2:
- Adicionar `planogram_name` e `step_name` na serialização de cada execução

---

## Frontend

### Arquivos críticos — Fase 1

- `resources/js/pages/tenant/planograms/Kanban.vue`
- `resources/js/pages/tenant/kanban/Index.vue` (remover)
- `resources/js/components/kanban/types.ts`
- `resources/js/composables/useKanban.ts`
- `resources/js/components/kanban/KanbanFilters.vue`
- `resources/js/components/kanban/KanbanBoard.vue`
- `resources/js/components/kanban/KanbanColumn.vue`

### Arquivos críticos — Fase 2

- `resources/js/components/kanban/KanbanCard.vue`
- `resources/js/components/kanban/KanbanCardDetail.vue`

### `types.ts`

```typescript
export type KanbanFilters = {
    planogram_id: string
    loja_id: string
    user_id: string
    assigned_to: string
    status: string
    only_overdue: boolean
    show_completed: boolean
}

export type AssignedUser = { id: string; name: string }

export type Execution = {
    id: string
    gondola_id: string
    gondola_name: string | null
    gondola_location: string | null
    planogram_name: string | null
    step_name: string | null
    status: 'pending' | 'active' | 'paused' | 'completed' | 'cancelled'
    assigned_to_user: AssignedUser | null
    started_at: string | null
    sla_date: string | null
}

export type BoardStep = {
    id: string
    name: string
    description: string | null
    color: string | null
    icon: string | null
    suggested_order: number
    is_required: boolean
    status: string
}

export type BoardColumn = {
    step: BoardStep
    executions: Execution[]
}

export type ExecutionDetails = {
    execution: {
        id: string
        status: Execution['status']
        gondola: { id: string; name: string | null; location: string | null } | null
        step: { id: string; name: string; description: string | null } | null
        assigned_to_user: AssignedUser | null
        started_at: string | null
        sla_date: string | null
    }
    allowed_users: AssignedUser[]
}
```

### `useKanban.ts` composable

Responsabilidades (Fase 1):
- `filteredBoard`: computed que aplica filtros client-side (`only_overdue`, `show_completed`) sobre `board` vindo dos props
- Drag state: `draggingExecutionId`, `draggingFromStepId`, `dragOverStepId` + handlers (Fase 2)
- Actions: `pauseExecution`, `resumeExecution`, `completeExecution`, `moveExecution` (Fase 2)
- Details modal state + `openExecutionDetails`, `assignFromDetails` (Fase 2)
- Utilitários: `statusColors`, `statusLabel`, `formatDate`, `isSlaOverdue`

Fase 1 mantém o composable simples: apenas os dois checkboxes client-side e os utilitários de display.

### Componentes

**`KanbanFilters.vue`** — wrapper fino sobre `ListFiltersBar.vue`
- Usa `<ListFiltersBar :action="kanban.index.url()" :clear-href="kanban.index.url()" search-name="gondola_search">` como base
- Slot: selects de Planograma, Loja, Status (os três que fazem sentido como filtros server-side)
- `store_id` e `planogram_id` como `<select name="...">` dentro do slot → form submit via GET
- Checkboxes "Apenas atrasadas" e "Mostrar concluídas" → client-side (computed sobre `board`)
- Reutiliza o estilo já padronizado (`bg-card`, `rounded-xl`, `border`) sem duplicar CSS

**`KanbanBoard.vue`**
- Props: `board: BoardColumn[]`, drag state do composable
- Layout: `flex gap-4 overflow-x-auto` com `min-width: max-content`
- Renderiza `<KanbanColumn>` para cada coluna
- Repassa eventos de drag/drop

**`KanbanColumn.vue`**
- Props: `column: BoardColumn`, `isDragOver: boolean`
- `border-top: 3px solid <color>` via style binding
- Header: nome da etapa, descrição truncada, badge de contagem
- Input local "Buscar gôndola" (ref `columnSearch`) — filtra `column.executions` por `gondola_name`
- Lista de `<KanbanCard>` com `v-for`
- Empty state: div dashed "Nenhuma gôndola encontrada"
- Emite: `dragover`, `dragleave`, `drop`

**`KanbanCard.vue`**
- Props: `execution: Execution`, `steps: BoardStep[]` (para mostrar step_name), `isDragging: boolean`
- `draggable="true"` + emite `dragstart`
- Layout seguindo o `exemplo.html`: nome gôndola, planograma, badge de status, step_name, botão Detalhes
- Ações rápidas: Pausar/Retomar/Concluir (baseado em `execution.status`)
- Emite: `details`, `pause`, `resume`, `complete`

**`KanbanCardDetail.vue`**
- Props: `open: boolean`, `loading: boolean`, `payload: ExecutionDetails | null`, `error: string | null`
- Emite: `update:open`, `assign`
- Usa `<Dialog>` do shadcn
- Info: gôndola, etapa, local
- Select de responsável com `allowed_users`
- Botões: Fechar / Confirmar responsável

**`pages/tenant/planograms/Kanban.vue`**
- Props Inertia: `planograms`, `stores`, `users`, `board`, `selected_planogram`, `filters`, `subdomain`
- Usa `useKanban(props)` — composable recebe props
- Template: `AppLayout` > `KanbanFilters` + dois checkboxes client-side + `KanbanBoard` + `KanbanCardDetail`
- Estado vazio quando board é null: estado compacto com ícone + "Selecione um planograma"
- Estado vazio quando board é []: "Sem etapas configuradas para este planograma"
- Visual: moderno e compacto, inspirado no `exemplo.html` mas com mais densidade

---

## Entrega em fases

### Fase 1 — Colunas e estrutura (este plano)
1. Backend: mudanças no `WorkflowKanbanController` e `PlanogramController`
2. `types.ts` + `useKanban.ts` (state de filtros + filteredBoard computed; sem drag ainda)
3. `KanbanFilters.vue` — todos os 7 filtros visuais
4. `KanbanBoard.vue` + `KanbanColumn.vue` com empty state e busca por gôndola
5. `Kanban.vue` page conectando tudo
6. Remover `kanban/Index.vue`

### Fase 2 — Cards e ações (próximo)
1. `KanbanCard.vue`: gondola name, planograma, status badge, SLA, ações rápidas, draggable
2. `KanbanCardDetail.vue`: modal de detalhes + assign de responsável
3. Drag-and-drop: handlers no `useKanban.ts` + `router.patch` para move
4. Backend: adicionar `planogram_name` e `step_name` em `buildBoardForPlanogram`

---

## Verificação

1. Acessar `/planograms/kanban` — redireciona para `/kanban`
2. Acessar `/kanban` — exibe `planograms/Kanban.vue` com barra de filtros visível
3. Selecionar planograma no filtro → form submit GET → colunas aparecem com as etapas
4. Selecionar loja → form submit GET → dropdown de planograma filtrado por loja
5. Checkboxes "Apenas atrasadas" / "Mostrar concluídas" → filtra client-side sem reload (Fase 2, quando houver cards)
6. Busca por gôndola dentro de coluna → filtra inline (Fase 2)
7. `FormKanbanSettings.vue` continua funcionando — `WorkflowPlanogramStepController` sem mudança
8. Rota `kanban.show` removida — sem quebra de links internos

---

## Estado atual após implementação — 2026-04-27

### Backend

- `WorkflowKanbanController::index()` renderiza `tenant/planograms/Kanban`, aceita `planogram_id` e `store_id`, sincroniza etapas do planograma selecionado e envia o board com flags de permissão calculadas por policy.
- `WorkflowExecutionController` expõe ações JSON para iniciar, mover, pausar, retomar, concluir, abandonar, detalhar, listar histórico e restaurar histórico.
- `WorkflowExecutionPolicy` centraliza regras por status, permissão RBAC, usuário permitido na etapa e última etapa ativa.
- `WorkflowKanbanService` monta colunas ignorando `is_skipped`, serializa dados de card e registra históricos de ações.
- `SyncWorkflowKanbanData` cria dados reais para todos ou alguns tenants/planogramas sem destruir dados existentes.

### Frontend

- `Kanban.vue` é a página única da experiência Kanban.
- `useKanban.ts` centraliza filtros client-side, detalhes, histórico, ações, notas, confirmação e drag-and-drop.
- `KanbanCard.vue` mostra ações conforme flags backend e links contextuais para editor/PDF.
- `KanbanCardDetail.vue` mostra dados compactos da execução, executantes permitidos, usuário responsável, usuário que iniciou, histórico e notas.
- `KanbanActionConfirmDialog.vue` confirma iniciar, pausar, retomar, concluir e abandonar.
- `KanbanFilters.vue` reutiliza `ListFiltersBar.vue`, com filtros alinhados visualmente e checkboxes client-side.
- `Index.vue` de planogramas direciona o botão de ação para Kanban com `planogram_id` quando o módulo Kanban está ativo; caso contrário mantém a rota de gôndolas.

### Regras funcionais consolidadas

- Apenas execuções `pending` podem ser iniciadas.
- Apenas usuários listados em `workflow_planogram_step_users` da etapa atual podem iniciar.
- Ao iniciar, o usuário logado vira responsável e iniciador da execução.
- Apenas execuções `active` podem ser pausadas, abandonadas ou movidas.
- Apenas execuções `paused` podem ser retomadas.
- Apenas execuções `active` na última etapa ativa podem ser concluídas.
- Movimento só aceita etapas do planograma da gôndola e bloqueia `target_step` com `is_skipped = true`.
- Toda ação relevante registra histórico em `workflow_histories`.

### Pendências e melhorias recomendadas

- Toast padronizado, composable de movimento, internacionalização inicial e timeline expansível de histórico foram implementados na primeira rodada de melhorias pós-Fase 2.
- Adicionar teste browser para o fluxo completo do Kanban, incluindo drag-and-drop.
- Adicionar ação acessível de "mover para próxima etapa disponível" para usuários sem drag-and-drop.
- Corrigir erros globais de `types:check` fora do escopo do Kanban para voltar a usar typecheck como verificação final obrigatória.
