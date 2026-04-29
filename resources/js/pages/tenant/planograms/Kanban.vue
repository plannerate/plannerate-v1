<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Kanban } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PlanogramController from '@/actions/App/Http/Controllers/Tenant/PlanogramController';
import KanbanActionConfirmDialog from '@/components/kanban/KanbanActionConfirmDialog.vue';
import KanbanBoard from '@/components/kanban/KanbanBoard.vue';
import KanbanCardDetail from '@/components/kanban/KanbanCardDetail.vue';
import KanbanFilters from '@/components/kanban/KanbanFilters.vue';
import type { Execution, KanbanExecutionAction, KanbanPageProps } from '@/components/kanban/types';
import KankanNavigationLinks from '@/components/KankanNavigationLinks.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useKanban } from '@/composables/useKanban';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { Auth } from '@/types/auth';

const props = defineProps<KanbanPageProps>();
const page = usePage();

const { t } = useT();
const currentUserId = (page.props.auth as Auth | undefined)?.user?.id ?? null;
const confirmOpen = ref(false);
const pendingAction = ref<KanbanExecutionAction | null>(null);
const pendingExecution = ref<Execution | null>(null);
const pendingFromDetail = ref(false);

const pageMeta = useCrudPageMeta({
    headTitle: t('app.kanban.title'),
    title: t('app.kanban.title'),
    description: t('app.kanban.description'),
    breadcrumbs: [
        { title: t('app.navigation.dashboard'), href: dashboard.url().replace(/^\/\/[^/]+/, '') },
        { title: t('app.kanban.navigation'), href: '#' },
    ],
});

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
    detailHistories,
    actionNotes,
    isOverdue,
    formatDate,
    statusColors,
    statusLabel,
    startExecution,
    startDetailExecution,
    pauseExecution,
    pauseDetailExecution,
    resumeExecution,
    resumeDetailExecution,
    completeExecution,
    completeDetailExecution,
    abandonExecution,
    abandonDetailExecution,
    openExecutionDetails,
    onDragStart,
    onDragOver,
    onDragLeave,
    onDrop,
} = useKanban(() => props.board, () => props.subdomain);

function statusClass(status: string): string {
    return statusColors[status] ?? 'bg-muted text-muted-foreground';
}

const confirmGondolaName = computed(() => (
    pendingFromDetail.value
        ? (detailPayload.value?.execution.gondola?.name ?? null)
        : (pendingExecution.value?.gondola_name ?? null)
));

const confirmStepName = computed(() => (
    pendingFromDetail.value
        ? (detailPayload.value?.execution.step?.name ?? null)
        : (pendingExecution.value?.step_name ?? null)
));

function requestCardAction(action: KanbanExecutionAction, execution: Execution): void {
    pendingAction.value = action;
    pendingExecution.value = execution;
    pendingFromDetail.value = false;
    actionNotes.value = '';
    confirmOpen.value = true;
}

function requestDetailAction(action: KanbanExecutionAction): void {
    pendingAction.value = action;
    pendingExecution.value = null;
    pendingFromDetail.value = true;
    confirmOpen.value = true;
}

async function confirmPendingAction(): Promise<void> {
    const action = pendingAction.value;

    if (!action) {
        return;
    }

    if (pendingFromDetail.value) {
        await runDetailAction(action);
    } else if (pendingExecution.value) {
        await runCardAction(action, pendingExecution.value);
    }

    confirmOpen.value = false;
    pendingAction.value = null;
    pendingExecution.value = null;
    pendingFromDetail.value = false;
}

async function runDetailAction(action: KanbanExecutionAction): Promise<void> {
    if (action === 'start') {
        await startDetailExecution();
    } else if (action === 'pause') {
        await pauseDetailExecution();
    } else if (action === 'resume') {
        await resumeDetailExecution();
    } else if (action === 'complete') {
        await completeDetailExecution();
    } else if (action === 'abandon') {
        await abandonDetailExecution();
    }
}

async function runCardAction(action: KanbanExecutionAction, execution: Execution): Promise<void> {
    if (action === 'start') {
        await startExecution(execution);
    } else if (action === 'pause') {
        await pauseExecution(execution);
    } else if (action === 'resume') {
        await resumeExecution(execution);
    } else if (action === 'complete') {
        await completeExecution(execution);
    } else if (action === 'abandon') {
        await abandonExecution(execution);
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />

        <template #header-actions>
            <div class="flex items-center justify-end gap-2">
                <NewActionButton :href="PlanogramController.create.url(props.subdomain)">
                    {{ t('app.tenant.planograms.actions.new') }}
                </NewActionButton>
            </div>
        </template>
        <KankanNavigationLinks :subdomain="props.subdomain" />

        <div class="flex h-full flex-col">
            <div class="border-b border-border bg-background px-4 py-3">
                <KanbanFilters
                    :subdomain="props.subdomain"
                    :planograms="props.planograms"
                    :stores="props.stores"
                    :filters="props.filters"
                    :only-overdue="onlyOverdue"
                    :show-completed="showCompleted"
                    @update:only-overdue="onlyOverdue = $event"
                    @update:show-completed="showCompleted = $event"
                />
            </div>

            <div
                v-if="!props.selected_planogram && !props.board"
                class="flex flex-1 flex-col items-center justify-center gap-3 text-muted-foreground"
            >
                <Kanban class="size-10 opacity-20" />
                <p class="text-sm">{{ t('app.kanban.select_planogram') }}</p>
            </div>

            <div
                v-else-if="filteredBoard.length === 0"
                class="flex flex-1 flex-col items-center justify-center gap-3 text-muted-foreground"
            >
                <Kanban class="size-10 opacity-20" />
                <p class="text-sm">{{ t('app.kanban.empty_steps') }}</p>
            </div>

            <KanbanBoard
                v-else
                :board="filteredBoard"
                :subdomain="props.subdomain"
                :current-user-id="currentUserId"
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
                @start="requestCardAction('start', $event)"
                @pause="requestCardAction('pause', $event)"
                @resume="requestCardAction('resume', $event)"
                @complete="requestCardAction('complete', $event)"
                @abandon="requestCardAction('abandon', $event)"
            />
        </div>

        <KanbanCardDetail
            v-model:open="detailOpen"
            :loading="detailLoading"
            :payload="detailPayload"
            :histories="detailHistories"
            :error="detailError"
            :action-notes="actionNotes"
            :busy="busyExecutionId === detailPayload?.execution.id"
            :steps="filteredBoard.map((column) => column.step)"
            :subdomain="props.subdomain"
            :current-user-id="currentUserId"
            @update:action-notes="actionNotes = $event"
            @start="requestDetailAction('start')"
            @pause="requestDetailAction('pause')"
            @resume="requestDetailAction('resume')"
            @complete="requestDetailAction('complete')"
            @abandon="requestDetailAction('abandon')"
        />

        <KanbanActionConfirmDialog
            v-model:open="confirmOpen"
            :action="pendingAction"
            :gondola-name="confirmGondolaName"
            :step-name="confirmStepName"
            :notes="actionNotes"
            :busy="Boolean(busyExecutionId)"
            @update:notes="actionNotes = $event"
            @confirm="confirmPendingAction"
        />
    </AppLayout>
</template>
