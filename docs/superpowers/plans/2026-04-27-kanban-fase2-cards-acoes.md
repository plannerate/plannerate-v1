# Kanban de Workflow — Fase 2: Cards e Ações

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transformar o board estrutural da Fase 1 em um kanban operacional com cards ricos, modal de detalhes, ações rápidas autorizadas por policy e drag-and-drop controlado entre etapas.

**Status atualizado em 2026-04-27:** Implementado. A fase foi expandida durante a execução para cobrir início de execução, regras por responsável permitido, histórico, links contextuais, confirmação customizada de ações, sincronização de dados reais e restrições de movimento.

**Architecture:** O backend já possui endpoints JSON em `WorkflowExecutionController` e a UI deve consumi-los com `useHttp`, como `FormKanbanSettings.vue` já faz. `useKanban.ts` passa a centralizar estado de ações, loading, drag-and-drop e modal; `KanbanBoard` e `KanbanColumn` só repassam props/eventos; `KanbanCard` e `KanbanCardDetail` ficam responsáveis pela apresentação. O board é recarregado após cada ação usando `router.reload({ only: ['board'] })` para manter a fonte de verdade no backend.

**Tech Stack:** Laravel 13, Inertia v3, Vue 3, TypeScript, Tailwind v4, shadcn-vue, Pest 4, Wayfinder, Laravel Sail.

---

## File Map

| Ação | Arquivo |
|---|---|
| Modificar | `tests/Feature/Tenant/WorkflowPlanogramSettingsTest.php` |
| Modificar | `app/Services/WorkflowKanbanService.php` |
| Criar | `resources/js/components/kanban/KanbanCard.vue` |
| Criar | `resources/js/components/kanban/KanbanCardDetail.vue` |
| Modificar | `resources/js/components/kanban/KanbanColumn.vue` |
| Modificar | `resources/js/components/kanban/KanbanBoard.vue` |
| Modificar | `resources/js/composables/useKanban.ts` |
| Modificar | `resources/js/pages/tenant/planograms/Kanban.vue` |
| Criar | `resources/js/components/kanban/KanbanActionConfirmDialog.vue` |
| Modificar | `app/Policies/WorkflowExecutionPolicy.php` |
| Criar | `app/Console/Commands/SyncWorkflowKanbanData.php` |
| Criar | `tests/Unit/SyncWorkflowKanbanDataCommandTest.php` |

---

## Task 1: Backend board payload com nomes de planograma e etapa

**Files:**
- Modify: `tests/Feature/Tenant/WorkflowPlanogramSettingsTest.php`
- Modify: `app/Services/WorkflowKanbanService.php`

- [ ] **Step 1: Adicionar assertion RED no teste existente do board**

No teste `kanban board hides skipped steps for a planogram`, depois de criar a execução e antes do `assertInertia`, garantir que o board serializado inclua os campos usados pelo card.

Substituir o trecho final do teste por:

```php
$response = $this->get(route('tenant.kanban.index', [
    'subdomain' => $context['subdomain'],
    'planogram_id' => $planogram->id,
]));

$response
    ->assertOk()
    ->assertInertia(fn (Assert $page) => $page
        ->component('tenant/planograms/Kanban')
        ->has('board', 1)
        ->where('board.0.executions.0.gondola_name', 'Gondola A')
        ->where('board.0.executions.0.planogram_name', $planogram->name)
        ->where('board.0.executions.0.step_name', $step->name)
    );
```

- [ ] **Step 2: Rodar o teste para confirmar falha**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Tenant/WorkflowPlanogramSettingsTest.php
```

Expected: falha porque `planogram_name` e `step_name` ainda não são serializados.

- [ ] **Step 3: Atualizar `WorkflowKanbanService::buildBoardForPlanogram()`**

Alterar eager loading:

```php
->with(['template', 'executions.gondola.planogram', 'executions.currentResponsible'])
```

Alterar o array de `step` para incluir description:

```php
'description' => $step->description,
```

Alterar o array de cada execution para incluir:

```php
'planogram_name' => $exec->gondola?->planogram?->name,
'step_name' => $step->name,
```

O bloco de execution deve ficar com esta forma:

```php
'executions' => $step->executions->map(fn (WorkflowGondolaExecution $exec) => [
    'id' => $exec->id,
    'gondola_id' => $exec->gondola_id,
    'gondola_name' => $exec->gondola?->name,
    'gondola_location' => $exec->gondola?->location,
    'planogram_name' => $exec->gondola?->planogram?->name,
    'step_name' => $step->name,
    'status' => $exec->status?->value,
    'assigned_to_user' => $exec->currentResponsible ? [
        'id' => $exec->currentResponsible->id,
        'name' => $exec->currentResponsible->name,
    ] : null,
    'started_at' => $exec->started_at?->toIso8601String(),
    'sla_date' => $exec->sla_date?->toIso8601String(),
])->values()->all(),
```

- [ ] **Step 4: Rodar teste backend**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Tenant/WorkflowPlanogramSettingsTest.php
```

Expected: todos os testes do arquivo passam.

---

## Task 2: Criar `KanbanCard.vue`

**Files:**
- Create: `resources/js/components/kanban/KanbanCard.vue`

- [ ] **Step 1: Criar componente de card**

```vue
<script setup lang="ts">
import { CalendarClock, CheckCircle2, GripVertical, Pause, Play, User } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Execution } from '@/components/kanban/types';

const props = defineProps<{
    execution: Execution;
    isDragging: boolean;
    statusClass: string;
    statusLabel: string;
    formattedSlaDate: string;
    isOverdue: boolean;
    isBusy: boolean;
}>();

const emit = defineEmits<{
    dragstart: [execution: Execution];
    details: [execution: Execution];
    pause: [execution: Execution];
    resume: [execution: Execution];
    complete: [execution: Execution];
}>();

const canPause = computed(() => props.execution.status === 'active');
const canResume = computed(() => props.execution.status === 'paused');
const canComplete = computed(() => !['completed', 'cancelled'].includes(props.execution.status));
</script>

<template>
    <article
        draggable="true"
        class="group rounded-lg border border-border bg-background p-3 text-sm shadow-sm transition hover:border-primary/40 hover:shadow-md"
        :class="{ 'opacity-50 ring-2 ring-primary/30': isDragging }"
        @dragstart="emit('dragstart', execution)"
    >
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <GripVertical class="size-4 shrink-0 text-muted-foreground opacity-50" />
                    <p class="truncate font-medium text-foreground">
                        {{ execution.gondola_name ?? 'Gondola sem nome' }}
                    </p>
                </div>
                <p class="mt-1 truncate text-xs text-muted-foreground">
                    {{ execution.gondola_location ?? 'Sem local informado' }}
                </p>
            </div>

            <Badge :class="statusClass">
                {{ statusLabel }}
            </Badge>
        </div>

        <div class="mt-3 space-y-1.5 text-xs text-muted-foreground">
            <p class="truncate">
                Planograma:
                <span class="font-medium text-foreground">{{ execution.planogram_name ?? '-' }}</span>
            </p>
            <p class="truncate">
                Etapa:
                <span class="font-medium text-foreground">{{ execution.step_name ?? '-' }}</span>
            </p>
            <p class="flex items-center gap-1.5" :class="{ 'text-destructive': isOverdue }">
                <CalendarClock class="size-3.5" />
                SLA: {{ formattedSlaDate }}
            </p>
            <p class="flex items-center gap-1.5">
                <User class="size-3.5" />
                {{ execution.assigned_to_user?.name ?? 'Sem responsavel' }}
            </p>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-1.5">
            <Button size="sm" variant="outline" class="h-7 px-2 text-xs" @click="emit('details', execution)">
                Detalhes
            </Button>

            <Button
                v-if="canPause"
                size="sm"
                variant="ghost"
                class="h-7 px-2 text-xs"
                :disabled="isBusy"
                @click="emit('pause', execution)"
            >
                <Pause class="mr-1 size-3.5" />
                Pausar
            </Button>

            <Button
                v-if="canResume"
                size="sm"
                variant="ghost"
                class="h-7 px-2 text-xs"
                :disabled="isBusy"
                @click="emit('resume', execution)"
            >
                <Play class="mr-1 size-3.5" />
                Retomar
            </Button>

            <Button
                v-if="canComplete"
                size="sm"
                variant="ghost"
                class="h-7 px-2 text-xs"
                :disabled="isBusy"
                @click="emit('complete', execution)"
            >
                <CheckCircle2 class="mr-1 size-3.5" />
                Concluir
            </Button>
        </div>
    </article>
</template>
```

- [ ] **Step 2: Rodar lints do arquivo**

```bash
./vendor/bin/sail npm run lint:check -- resources/js/components/kanban/KanbanCard.vue
```

Expected: sem erros. Se o script não aceitar path, usar `./vendor/bin/sail npm run lint:check`.

---

## Task 3: Criar `KanbanCardDetail.vue`

**Files:**
- Create: `resources/js/components/kanban/KanbanCardDetail.vue`

- [ ] **Step 1: Criar modal de detalhes**

```vue
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ExecutionDetails } from '@/components/kanban/types';

const props = defineProps<{
    open: boolean;
    loading: boolean;
    payload: ExecutionDetails | null;
    error: string | null;
    assigning: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    assign: [userId: string];
}>();

const selectedUserId = ref('');

const execution = computed(() => props.payload?.execution ?? null);
const allowedUsers = computed(() => props.payload?.allowed_users ?? []);

watch(
    () => props.payload,
    (payload) => {
        selectedUserId.value = payload?.execution.assigned_to_user?.id ?? '';
    },
    { immediate: true },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Detalhes da execução</DialogTitle>
                <DialogDescription>
                    Consulte a gôndola, etapa atual e responsável permitido para esta etapa.
                </DialogDescription>
            </DialogHeader>

            <div v-if="loading" class="space-y-3 py-4">
                <div class="h-4 w-3/4 animate-pulse rounded bg-muted" />
                <div class="h-4 w-1/2 animate-pulse rounded bg-muted" />
                <div class="h-9 w-full animate-pulse rounded bg-muted" />
            </div>

            <div v-else-if="error" class="rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                {{ error }}
            </div>

            <div v-else-if="execution" class="space-y-4 py-2">
                <div class="grid gap-3 text-sm">
                    <div>
                        <p class="text-xs text-muted-foreground">Gôndola</p>
                        <p class="font-medium text-foreground">{{ execution.gondola?.name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Local</p>
                        <p class="font-medium text-foreground">{{ execution.gondola?.location ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Etapa</p>
                        <p class="font-medium text-foreground">{{ execution.step?.name ?? '-' }}</p>
                        <p v-if="execution.step?.description" class="text-xs text-muted-foreground">
                            {{ execution.step.description }}
                        </p>
                    </div>
                </div>

                <div class="space-y-1">
                    <label for="kanban-assignee" class="text-xs font-medium text-foreground">
                        Responsável
                    </label>
                    <select
                        id="kanban-assignee"
                        v-model="selectedUserId"
                        class="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="">Selecione um responsável</option>
                        <option v-for="user in allowedUsers" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">Fechar</Button>
                <Button
                    :disabled="!selectedUserId || assigning || loading"
                    @click="emit('assign', selectedUserId)"
                >
                    Confirmar responsável
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
```

- [ ] **Step 2: Rodar lints do arquivo**

```bash
./vendor/bin/sail npm run lint:check -- resources/js/components/kanban/KanbanCardDetail.vue
```

Expected: sem erros. Se o script não aceitar path, usar `./vendor/bin/sail npm run lint:check`.

---

## Task 4: Evoluir `useKanban.ts` com ações JSON e drag state

**Files:**
- Modify: `resources/js/composables/useKanban.ts`

- [ ] **Step 1: Atualizar assinatura e imports**

Substituir imports por:

```typescript
import { router, useHttp } from '@inertiajs/vue3';
import { computed, ref, toValue, type MaybeRefOrGetter } from 'vue';
import WorkflowExecutionController from '@/actions/App/Http/Controllers/Tenant/WorkflowExecutionController';
import type { BoardColumn, Execution, ExecutionDetails } from '@/components/kanban/types';
```

Alterar assinatura:

```typescript
export function useKanban(
    board: MaybeRefOrGetter<BoardColumn[] | null>,
    subdomain: MaybeRefOrGetter<string>,
) {
```

- [ ] **Step 2: Adicionar estados**

Logo após `showCompleted`, adicionar:

```typescript
const http = useHttp();
const actionHttp = useHttp<Record<string, string | null>>({});
const assignHttp = useHttp<{ user_id: string }>({ user_id: '' });

const draggingExecutionId = ref<string | null>(null);
const draggingFromStepId = ref<string | null>(null);
const dragOverStepId = ref<string | null>(null);
const busyExecutionId = ref<string | null>(null);

const detailOpen = ref(false);
const detailLoading = ref(false);
const detailError = ref<string | null>(null);
const detailPayload = ref<ExecutionDetails | null>(null);
const assigning = ref(false);
```

- [ ] **Step 3: Adicionar helpers de reload e URL**

```typescript
function normalizedUrl(url: string): string {
    return url.replace(/^\/\/[^/]+/, '');
}

function reloadBoard(): void {
    router.reload({
        only: ['board'],
        preserveScroll: true,
    });
}
```

- [ ] **Step 4: Adicionar ações**

```typescript
async function submitExecutionAction(
    execution: Execution,
    action: 'pause' | 'resume' | 'complete',
): Promise<void> {
    busyExecutionId.value = execution.id;

    try {
        const route = WorkflowExecutionController[action]({
            subdomain: toValue(subdomain),
            execution: execution.id,
        });

        await actionHttp.submit({
            ...route,
            url: normalizedUrl(route.url),
        });

        reloadBoard();
    } finally {
        busyExecutionId.value = null;
    }
}

async function pauseExecution(execution: Execution): Promise<void> {
    await submitExecutionAction(execution, 'pause');
}

async function resumeExecution(execution: Execution): Promise<void> {
    await submitExecutionAction(execution, 'resume');
}

async function completeExecution(execution: Execution): Promise<void> {
    await submitExecutionAction(execution, 'complete');
}
```

- [ ] **Step 5: Adicionar detalhes e assign**

```typescript
async function openExecutionDetails(execution: Execution): Promise<void> {
    detailOpen.value = true;
    detailLoading.value = true;
    detailError.value = null;
    detailPayload.value = null;

    try {
        const route = WorkflowExecutionController.details({
            subdomain: toValue(subdomain),
            execution: execution.id,
        });

        detailPayload.value = await http.submit({
            ...route,
            url: normalizedUrl(route.url),
        }) as ExecutionDetails;
    } catch (error) {
        console.error(error);
        detailError.value = 'Não foi possível carregar os detalhes da execução.';
    } finally {
        detailLoading.value = false;
    }
}

async function assignFromDetails(userId: string): Promise<void> {
    const executionId = detailPayload.value?.execution.id;

    if (!executionId) {
        return;
    }

    assigning.value = true;
    assignHttp.user_id = userId;

    try {
        const route = WorkflowExecutionController.assign({
            subdomain: toValue(subdomain),
            execution: executionId,
        });

        await assignHttp.submit({
            ...route,
            url: normalizedUrl(route.url),
        });

        detailOpen.value = false;
        reloadBoard();
    } catch (error) {
        console.error(error);
        detailError.value = 'Não foi possível atribuir o responsável.';
    } finally {
        assigning.value = false;
    }
}
```

- [ ] **Step 6: Adicionar drag-and-drop**

```typescript
function onDragStart(execution: Execution, stepId: string): void {
    draggingExecutionId.value = execution.id;
    draggingFromStepId.value = stepId;
}

function onDragOver(stepId: string): void {
    dragOverStepId.value = stepId;
}

function onDragLeave(stepId: string): void {
    if (dragOverStepId.value === stepId) {
        dragOverStepId.value = null;
    }
}

async function onDrop(targetStepId: string): Promise<void> {
    const executionId = draggingExecutionId.value;
    const fromStepId = draggingFromStepId.value;

    draggingExecutionId.value = null;
    draggingFromStepId.value = null;
    dragOverStepId.value = null;

    if (!executionId || !fromStepId || fromStepId === targetStepId) {
        return;
    }

    busyExecutionId.value = executionId;
    actionHttp.target_step_id = targetStepId;

    try {
        const route = WorkflowExecutionController.move({
            subdomain: toValue(subdomain),
            execution: executionId,
        });

        await actionHttp.submit({
            ...route,
            url: normalizedUrl(route.url),
        });

        reloadBoard();
    } finally {
        actionHttp.target_step_id = null;
        busyExecutionId.value = null;
    }
}
```

- [ ] **Step 7: Atualizar retorno do composable**

Adicionar ao objeto retornado:

```typescript
draggingExecutionId,
dragOverStepId,
busyExecutionId,
detailOpen,
detailLoading,
detailError,
detailPayload,
assigning,
pauseExecution,
resumeExecution,
completeExecution,
openExecutionDetails,
assignFromDetails,
onDragStart,
onDragOver,
onDragLeave,
onDrop,
```

- [ ] **Step 8: Rodar typecheck**

```bash
./vendor/bin/sail npm run types:check
```

Expected: sem erros de TypeScript.

---

## Task 5: Atualizar `KanbanColumn.vue` para cards reais

**Files:**
- Modify: `resources/js/components/kanban/KanbanColumn.vue`

- [ ] **Step 1: Atualizar imports e props**

Trocar imports por:

```typescript
import { computed, ref } from 'vue';
import { Kanban } from 'lucide-vue-next';
import KanbanCard from '@/components/kanban/KanbanCard.vue';
import type { BoardColumn, Execution } from '@/components/kanban/types';
```

Trocar props:

```typescript
const props = defineProps<{
    column: BoardColumn;
    isDragOver: boolean;
    draggingExecutionId: string | null;
    busyExecutionId: string | null;
    statusClass: (status: string) => string;
    statusLabel: (status: string) => string;
    formatDate: (iso: string | null) => string;
    isOverdue: (execution: Execution) => boolean;
}>();
```

Adicionar emits:

```typescript
const emit = defineEmits<{
    dragstart: [execution: Execution, stepId: string];
    dragover: [stepId: string];
    dragleave: [stepId: string];
    drop: [stepId: string];
    details: [execution: Execution];
    pause: [execution: Execution];
    resume: [execution: Execution];
    complete: [execution: Execution];
}>();
```

- [ ] **Step 2: Atualizar wrapper da coluna para drop zone**

No `<div>` raiz:

```vue
@dragover.prevent="emit('dragover', column.step.id)"
@dragleave="emit('dragleave', column.step.id)"
@drop.prevent="emit('drop', column.step.id)"
:class="{ 'ring-2 ring-primary/30': isDragOver }"
```

- [ ] **Step 3: Trocar card simples por `KanbanCard`**

Substituir o `<div v-for="execution ...">` por:

```vue
<KanbanCard
    v-for="execution in visibleExecutions"
    :key="execution.id"
    :execution="execution"
    :is-dragging="draggingExecutionId === execution.id"
    :is-busy="busyExecutionId === execution.id"
    :status-class="statusClass(execution.status)"
    :status-label="statusLabel(execution.status)"
    :formatted-sla-date="formatDate(execution.sla_date)"
    :is-overdue="isOverdue(execution)"
    @dragstart="emit('dragstart', $event, column.step.id)"
    @details="emit('details', $event)"
    @pause="emit('pause', $event)"
    @resume="emit('resume', $event)"
    @complete="emit('complete', $event)"
/>
```

- [ ] **Step 4: Rodar typecheck**

```bash
./vendor/bin/sail npm run types:check
```

Expected: sem erros.

---

## Task 6: Atualizar `KanbanBoard.vue` para repassar eventos

**Files:**
- Modify: `resources/js/components/kanban/KanbanBoard.vue`

- [ ] **Step 1: Atualizar types e props**

```vue
<script setup lang="ts">
import KanbanColumn from '@/components/kanban/KanbanColumn.vue';
import type { BoardColumn, Execution } from '@/components/kanban/types';

defineProps<{
    board: BoardColumn[];
    draggingExecutionId: string | null;
    dragOverStepId: string | null;
    busyExecutionId: string | null;
    statusClass: (status: string) => string;
    statusLabel: (status: string) => string;
    formatDate: (iso: string | null) => string;
    isOverdue: (execution: Execution) => boolean;
}>();

const emit = defineEmits<{
    dragstart: [execution: Execution, stepId: string];
    dragover: [stepId: string];
    dragleave: [stepId: string];
    drop: [stepId: string];
    details: [execution: Execution];
    pause: [execution: Execution];
    resume: [execution: Execution];
    complete: [execution: Execution];
}>();
</script>
```

- [ ] **Step 2: Atualizar template**

```vue
<template>
    <div class="flex-1 overflow-x-auto overflow-y-hidden">
        <div class="flex h-full gap-3 px-4 py-3" style="min-width: max-content">
            <KanbanColumn
                v-for="column in board"
                :key="column.step.id"
                :column="column"
                :is-drag-over="dragOverStepId === column.step.id"
                :dragging-execution-id="draggingExecutionId"
                :busy-execution-id="busyExecutionId"
                :status-class="statusClass"
                :status-label="statusLabel"
                :format-date="formatDate"
                :is-overdue="isOverdue"
                @dragstart="emit('dragstart', $event, column.step.id)"
                @dragover="emit('dragover', $event)"
                @dragleave="emit('dragleave', $event)"
                @drop="emit('drop', $event)"
                @details="emit('details', $event)"
                @pause="emit('pause', $event)"
                @resume="emit('resume', $event)"
                @complete="emit('complete', $event)"
            />
        </div>
    </div>
</template>
```

- [ ] **Step 3: Rodar typecheck**

```bash
./vendor/bin/sail npm run types:check
```

Expected: sem erros.

---

## Task 7: Conectar página `Kanban.vue` ao composable e modal

**Files:**
- Modify: `resources/js/pages/tenant/planograms/Kanban.vue`

- [ ] **Step 1: Importar modal**

Adicionar import:

```typescript
import KanbanCardDetail from '@/components/kanban/KanbanCardDetail.vue';
```

- [ ] **Step 2: Atualizar destructuring do composable**

Substituir:

```typescript
const { onlyOverdue, showCompleted, filteredBoard } = useKanban(() => props.board);
```

Por:

```typescript
const {
    onlyOverdue,
    showCompleted,
    filteredBoard,
    draggingExecutionId,
    dragOverStepId,
    busyExecutionId,
    detailOpen,
    detailLoading,
    detailError,
    detailPayload,
    assigning,
    isOverdue,
    formatDate,
    statusColors,
    statusLabel,
    pauseExecution,
    resumeExecution,
    completeExecution,
    openExecutionDetails,
    assignFromDetails,
    onDragStart,
    onDragOver,
    onDragLeave,
    onDrop,
} = useKanban(() => props.board, () => props.subdomain);

function statusClass(status: string): string {
    return statusColors[status] ?? 'bg-muted text-muted-foreground';
}
```

- [ ] **Step 3: Atualizar `KanbanBoard` no template**

Substituir:

```vue
<KanbanBoard v-else :board="filteredBoard" />
```

Por:

```vue
<KanbanBoard
    v-else
    :board="filteredBoard"
    :dragging-execution-id="draggingExecutionId"
    :drag-over-step-id="dragOverStepId"
    :busy-execution-id="busyExecutionId"
    :status-class="statusClass"
    :status-label="statusLabel"
    :format-date="formatDate"
    :is-overdue="isOverdue"
    @dragstart="onDragStart"
    @dragover="onDragOver"
    @dragleave="onDragLeave"
    @drop="onDrop"
    @details="openExecutionDetails"
    @pause="pauseExecution"
    @resume="resumeExecution"
    @complete="completeExecution"
/>
```

- [ ] **Step 4: Adicionar modal abaixo do board**

Antes de fechar o `<AppLayout>`:

```vue
<KanbanCardDetail
    v-model:open="detailOpen"
    :loading="detailLoading"
    :payload="detailPayload"
    :error="detailError"
    :assigning="assigning"
    @assign="assignFromDetails"
/>
```

- [ ] **Step 5: Rodar typecheck**

```bash
./vendor/bin/sail npm run types:check
```

Expected: sem erros.

---

## Task 8: Verificação final

**Files:**
- All modified files above

- [ ] **Step 1: Formatar PHP**

```bash
./vendor/bin/sail pint --dirty --format agent
```

Expected: `result` passed ou fixed.

- [ ] **Step 2: Rodar testes backend**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Tenant/WorkflowPlanogramSettingsTest.php
./vendor/bin/sail artisan test --compact --filter=WorkflowKanbanControllerTest
```

Expected: todos passam.

- [ ] **Step 3: Rodar verificações frontend**

```bash
./vendor/bin/sail npm run types:check
./vendor/bin/sail npm run lint:check
```

Expected: ambos passam.

- [ ] **Step 4: Verificação manual no browser**

1. Abrir `/kanban`.
2. Selecionar um planograma com gôndolas.
3. Confirmar que cards aparecem nas colunas.
4. Clicar em `Detalhes` e confirmar que o modal abre com gôndola, etapa e responsáveis.
5. Atribuir responsável permitido e confirmar que o board recarrega.
6. Pausar, retomar e concluir uma execução.
7. Arrastar um card para outra coluna e confirmar que ele muda de etapa.
8. Usar `Apenas atrasadas`, `Mostrar concluídas` e busca por gôndola dentro da coluna.

Expected: sem erro visual, sem erro no console e sem responses 422 inesperadas.

---

## Self-Review

- Spec coverage: cobre `KanbanCard.vue`, `KanbanCardDetail.vue`, drag-and-drop, ações rápidas, detalhes, assign e payload backend `planogram_name`/`step_name`.
- TDD: começa com assertion backend RED para o contrato do payload; frontend é validado por typecheck/lint e teste manual porque não há suíte Vue configurada.
- Sail: todos os comandos usam `./vendor/bin/sail`.
- Banco: nenhum passo usa `migrate:fresh`, `migrate:refresh`, `db:wipe` ou comando destrutivo.

---

## Atualização de execução — 2026-04-27

### Entregue além do plano original

- [x] `SyncWorkflowKanbanData` criado para gerar dados reais de Kanban sem comandos destrutivos de banco.
- [x] Comando evoluído para modo interativo com seleção de tenant e planograma, incluindo opção de processar todos.
- [x] `WorkflowExecutionPolicy` passou a centralizar regras de início, pausa, retomada, conclusão, abandono e movimentação.
- [x] Cards e modal usam flags vindas do backend: `can_start`, `can_pause`, `can_resume`, `can_complete`, `can_abandon` e `can_move`.
- [x] Ação `Iniciar` só aparece para execução `pending` quando o usuário logado está nos executantes permitidos da etapa.
- [x] Ação `Abandonar` só aparece para execução `active`.
- [x] Ação `Concluir` só aparece na última etapa ativa do workflow.
- [x] Ações de workflow registram histórico em `workflow_histories` com notas quando informadas.
- [x] Modal compacto de detalhes mostra executantes permitidos, destaca o usuário logado, exibe responsável atual, usuário que iniciou, histórico e notas.
- [x] Modal customizado de confirmação criado para iniciar, pausar, retomar, concluir e abandonar.
- [x] Links contextuais no card e modal:
  - se o usuário logado iniciou a execução ativa, exibe link para abrir editor de gôndola;
  - se outro usuário iniciou, exibe link para visualizar PDF.
- [x] Movimento por drag-and-drop limitado a execuções `active`.
- [x] Movimento bloqueia etapa de destino com `is_skipped = true` e mantém feedback visual para o usuário soltar na próxima etapa disponível.
- [x] Listagem de planogramas passa a enviar para o Kanban com `planogram_id` quando o módulo Kanban está ativo; caso contrário mantém rota de gôndolas.
- [x] Filtros do Kanban alinhados visualmente pela base dos campos.

### Decisões finais tomadas

- O usuário logado é o responsável ao iniciar uma execução; não há seleção manual de responsável no início.
- A UI não decide permissão sozinha: o frontend apenas reflete flags calculadas por policy no backend.
- `move` valida o planograma da gôndola, não apenas a etapa atual da execução, para evitar mover cards para etapas de outro planograma.
- Etapas com `is_skipped = true` não aparecem como colunas do board e também são bloqueadas no endpoint de `move`.
- As notas continuam centralizadas em `actionNotes` e são reutilizadas pelo modal de confirmação.

### Verificações executadas

- `./vendor/bin/sail artisan test --compact tests/Feature/Tenant/WorkflowPlanogramSettingsTest.php`
- `./vendor/bin/sail artisan test --compact --filter='execution move requires active status and blocks skipped target steps'`
- `./vendor/bin/sail php vendor/bin/pint --dirty --format agent`
- `./vendor/bin/sail npx eslint --fix resources/js/composables/useKanban.ts resources/js/components/kanban/KanbanCard.vue resources/js/pages/tenant/planograms/Kanban.vue resources/js/components/kanban/types.ts`
- `./vendor/bin/sail npx eslint --fix resources/js/components/ListFiltersBar.vue resources/js/components/kanban/KanbanFilters.vue`

Observação: `./vendor/bin/sail npm run types:check` foi executado e falhou por erros preexistentes fora dos arquivos do Kanban alterados nesta fase.

---

## Sugestões de melhorias — próxima fase

### Alta prioridade

- [ ] Exibir toast padronizado para negações de movimento e erros de ação, substituindo ou complementando o alerta inline atual.
- [ ] Criar um composable específico para regras de drag-and-drop se `useKanban.ts` continuar crescendo.
- [ ] Revisar textos hardcoded do Kanban e mover para `lang/pt_BR/app.php`.
- [ ] Melhorar o card de histórico do modal para uma timeline compacta, com opção de expandir para ver detalhes completos da ação, notas, etapa anterior, etapa destino, usuário executor e responsável envolvido.
- [ ] Criar testes específicos para o frontend do Kanban com browser/Pest, cobrindo abrir modal, confirmar ações, links contextuais e drag-and-drop.
- [ ] Adicionar refresh parcial depois de ações para também atualizar contadores globais, quando existirem.
- [ ] Melhorar acessibilidade do drag-and-drop com alternativa por menu: "Mover para próxima etapa disponível".

### Média prioridade

- [ ] Adicionar indicador visual de próxima etapa disponível quando o usuário tenta soltar em etapa pulada.
- [ ] Permitir filtro por responsável atual e por status da execução no Kanban.
- [ ] Persistir preferência de `onlyOverdue` e `showCompleted` por usuário.
- [ ] Criar painel resumido por planograma: total pendente, em andamento, atrasado, concluído e cancelado.

### Baixa prioridade

- [ ] Padronizar "gôndola" com acento nos textos de UI.
- [ ] Adicionar skeleton mais rico no carregamento do modal de detalhes.
- [ ] Investigar os erros globais de `types:check` fora do escopo do Kanban para recuperar essa verificação como gate confiável.
