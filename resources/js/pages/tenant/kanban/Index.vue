<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Clock, GripVertical, Kanban, MoreHorizontal, Pause, Play, SkipForward, User } from 'lucide-vue-next';
import WorkflowKanbanController from '@/actions/App/Http/Controllers/Tenant/WorkflowKanbanController';
import WorkflowExecutionController from '@/actions/App/Http/Controllers/Tenant/WorkflowExecutionController';
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import { dashboard } from '@/routes';

type Planogram = {
    id: string;
    name: string;
    store: string | null;
};

type AssignedUser = {
    id: string;
    name: string;
};

type Execution = {
    id: string;
    gondola_id: string;
    gondola_name: string | null;
    gondola_location: string | null;
    status: 'pending' | 'active' | 'paused' | 'completed' | 'cancelled';
    assigned_to_user: AssignedUser | null;
    started_at: string | null;
    sla_date: string | null;
};

type BoardColumn = {
    step: {
        id: string;
        name: string;
        color: string | null;
        icon: string | null;
        suggested_order: number;
        is_required: boolean;
        status: string;
    };
    executions: Execution[];
};

const props = defineProps<{
    subdomain: string;
    planograms: Planogram[];
    selected_planogram: Planogram | null;
    board: BoardColumn[] | null;
    can_initiate: boolean;
}>();

const { t } = useT();

const kanbanPath = WorkflowKanbanController.index.url(props.subdomain).replace(/^\/\/[^/]+/, '');

const pageMeta = useCrudPageMeta({
    headTitle: t('app.kanban.title'),
    title: t('app.kanban.title'),
    description: t('app.kanban.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.kanban.navigation'), href: kanbanPath },
    ],
});

const selectedId = ref<string>(props.selected_planogram?.id ?? '');

function onPlanogramChange(event: Event): void {
    const id = (event.target as HTMLSelectElement).value;
    selectedId.value = id;

    if (!id) {
        router.visit(WorkflowKanbanController.index.url(props.subdomain));
        return;
    }

    router.visit(WorkflowKanbanController.show.url({ subdomain: props.subdomain, planogram: id }));
}

// Drag state
const draggingExecutionId = ref<string | null>(null);
const draggingFromStepId = ref<string | null>(null);
const dragOverStepId = ref<string | null>(null);

function onDragStart(execution: Execution, stepId: string): void {
    draggingExecutionId.value = execution.id;
    draggingFromStepId.value = stepId;
}

function onDragOver(event: DragEvent, stepId: string): void {
    event.preventDefault();
    dragOverStepId.value = stepId;
}

function onDragLeave(): void {
    dragOverStepId.value = null;
}

function onDrop(event: DragEvent, targetStepId: string): void {
    event.preventDefault();
    dragOverStepId.value = null;

    if (!draggingExecutionId.value || draggingFromStepId.value === targetStepId) {
        draggingExecutionId.value = null;
        draggingFromStepId.value = null;
        return;
    }

    router.patch(
        WorkflowExecutionController.move.url({ subdomain: props.subdomain, execution: draggingExecutionId.value }),
        { target_step_id: targetStepId },
    );

    draggingExecutionId.value = null;
    draggingFromStepId.value = null;
}

function pauseExecution(executionId: string): void {
    router.patch(
        WorkflowExecutionController.pause.url({ subdomain: props.subdomain, execution: executionId }),
        {},
    );
}

function resumeExecution(executionId: string): void {
    router.patch(
        WorkflowExecutionController.resume.url({ subdomain: props.subdomain, execution: executionId }),
        {},
    );
}

function completeExecution(executionId: string): void {
    router.patch(
        WorkflowExecutionController.complete.url({ subdomain: props.subdomain, execution: executionId }),
        {},
    );
}

const statusColors: Record<string, string> = {
    pending: 'bg-muted text-muted-foreground',
    active: 'bg-primary/15 text-primary',
    paused: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    completed: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    cancelled: 'bg-destructive/15 text-destructive',
};

function statusLabel(status: string): string {
    return t(`app.kanban.executions.status.${status}`) ?? status;
}

function formatDate(iso: string | null): string {
    if (!iso) { return '—'; }
    return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit' });
}

const isSlaOverdue = computed(() => (slaDate: string | null): boolean => {
    if (!slaDate) { return false; }
    return new Date(slaDate) < new Date();
});
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />

        <template #header-actions>
        </template>

        <div class="flex flex-col gap-4 p-4">
            <!-- Seletor de planograma -->
            <div class="flex items-center gap-3">
                <label for="planogram-select" class="shrink-0 text-sm font-medium text-foreground">
                    Planograma:
                </label>
                <select
                    id="planogram-select"
                    :value="selectedId"
                    class="h-9 rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20 min-w-64"
                    @change="onPlanogramChange"
                >
                    <option value="">{{ t('app.kanban.select_planogram') }}</option>
                    <option v-for="p in props.planograms" :key="p.id" :value="p.id">
                        {{ p.name }}{{ p.store ? ` — ${p.store}` : '' }}
                    </option>
                </select>
            </div>

            <!-- Estado vazio: nenhum planograma selecionado -->
            <div
                v-if="!props.selected_planogram"
                class="flex flex-col items-center justify-center gap-3 py-24 text-muted-foreground"
            >
                <Kanban class="size-12 opacity-30" />
                <p class="text-sm">{{ t('app.kanban.select_planogram') }}</p>
            </div>

            <!-- Board vazio: planograma sem etapas -->
            <div
                v-else-if="!props.board || props.board.length === 0"
                class="flex flex-col items-center justify-center gap-3 py-24 text-muted-foreground"
            >
                <Kanban class="size-12 opacity-30" />
                <p class="text-sm">{{ t('app.kanban.empty_steps') }}</p>
            </div>

            <!-- Board com colunas -->
            <div v-else class="flex gap-4 overflow-x-auto pb-4">
                <div
                    v-for="column in props.board"
                    :key="column.step.id"
                    class="flex w-72 shrink-0 flex-col gap-3 rounded-xl border border-border bg-muted/30 p-3 transition"
                    :class="dragOverStepId === column.step.id ? 'border-primary/60 bg-primary/5 ring-1 ring-primary/30' : ''"
                    @dragover="onDragOver($event, column.step.id)"
                    @dragleave="onDragLeave"
                    @drop="onDrop($event, column.step.id)"
                >
                    <!-- Cabeçalho da coluna -->
                    <div class="flex items-center justify-between px-1">
                        <div class="flex items-center gap-2">
                            <span
                                v-if="column.step.color"
                                class="size-2.5 rounded-full shrink-0"
                                :style="{ backgroundColor: column.step.color }"
                            />
                            <span class="text-sm font-semibold text-foreground">{{ column.step.name }}</span>
                            <span class="rounded-full bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                {{ column.executions.length }}
                            </span>
                        </div>
                    </div>

                    <!-- Cards de execução -->
                    <div class="flex flex-col gap-2">
                        <div
                            v-for="exec in column.executions"
                            :key="exec.id"
                            draggable="true"
                            class="cursor-grab rounded-lg border border-border bg-card p-3 shadow-sm transition hover:border-primary/40 hover:shadow-md active:cursor-grabbing"
                            :class="draggingExecutionId === exec.id ? 'opacity-50 ring-1 ring-primary' : ''"
                            @dragstart="onDragStart(exec, column.step.id)"
                        >
                            <!-- Nome da gôndola -->
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-foreground">
                                        {{ exec.gondola_name ?? '—' }}
                                    </p>
                                    <p v-if="exec.gondola_location" class="truncate text-xs text-muted-foreground">
                                        {{ exec.gondola_location }}
                                    </p>
                                </div>
                                <GripVertical class="size-4 mt-0.5 shrink-0 text-muted-foreground/50" />
                            </div>

                            <!-- Status badge -->
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <span
                                    :class="['inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium', statusColors[exec.status] ?? statusColors.pending]"
                                >
                                    {{ statusLabel(exec.status) }}
                                </span>

                                <!-- SLA date -->
                                <span
                                    v-if="exec.sla_date"
                                    :class="['flex items-center gap-1 text-xs', isSlaOverdue(exec.sla_date) ? 'text-destructive' : 'text-muted-foreground']"
                                >
                                    <Clock class="size-3" />
                                    {{ formatDate(exec.sla_date) }}
                                </span>
                            </div>

                            <!-- Responsável -->
                            <div v-if="exec.assigned_to_user" class="mt-2 flex items-center gap-1 text-xs text-muted-foreground">
                                <User class="size-3 shrink-0" />
                                <span class="truncate">{{ exec.assigned_to_user.name }}</span>
                            </div>

                            <!-- Ações rápidas -->
                            <div class="mt-3 flex items-center justify-end gap-1">
                                <button
                                    v-if="exec.status === 'active'"
                                    type="button"
                                    class="rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                    :title="'Pausar'"
                                    @click="pauseExecution(exec.id)"
                                >
                                    <Pause class="size-3.5" />
                                </button>
                                <button
                                    v-if="exec.status === 'paused'"
                                    type="button"
                                    class="rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                    :title="'Retomar'"
                                    @click="resumeExecution(exec.id)"
                                >
                                    <Play class="size-3.5" />
                                </button>
                                <button
                                    v-if="['active', 'paused'].includes(exec.status)"
                                    type="button"
                                    class="rounded p-1 text-muted-foreground transition hover:bg-emerald-500/15 hover:text-emerald-600"
                                    :title="'Concluir'"
                                    @click="completeExecution(exec.id)"
                                >
                                    <SkipForward class="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Coluna vazia -->
                        <div
                            v-if="column.executions.length === 0"
                            class="flex h-20 items-center justify-center rounded-lg border border-dashed border-border/60 text-xs text-muted-foreground/50"
                        >
                            Sem itens
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
