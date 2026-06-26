<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Kanban, Plus, RefreshCw } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import PlanogramController from '@/actions/App/Http/Controllers/Tenant/PlanogramController';
import KanbanActionConfirmDialog from '@/components/kanban/KanbanActionConfirmDialog.vue';
import KanbanBoard from '@/components/kanban/KanbanBoard.vue';
import KanbanCardDetail from '@/components/kanban/KanbanCardDetail.vue';
import KanbanFilters from '@/components/kanban/KanbanFilters.vue';
import type { Execution, KanbanExecutionAction, KanbanPageProps } from '@/components/kanban/types';
import KankanNavigationLinks from '@/components/KankanNavigationLinks.vue';
import NewActionButton from '@/components/NewActionButton.vue';
import GondolaCreateStepper from '@/components/plannerate/form/GondolaCreateStepper.vue';
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
const showGondolaCreate = ref(false);
const confirmOpen = ref(false);
const pendingAction = ref<KanbanExecutionAction | null>(null);
const pendingExecution = ref<Execution | null>(null);
const pendingFromDetail = ref(false);
const boardRegion = ref<HTMLElement | null>(null);
const boardRegionHeight = ref('60vh');

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
    requestAbandonment,
    requestDetailAbandonment,
    openExecutionDetails,
    onDragStart,
    onDragOver,
    onDragLeave,
    onDrop,
} = useKanban(() => props.board);

function statusClass(status: string): string {
    return statusColors[status] ?? 'bg-muted text-muted-foreground';
}

function updateBoardRegionHeight(): void {
    if (!boardRegion.value) {
        return;
    }

    const viewportHeight = window.visualViewport?.height ?? window.innerHeight;
    const topOffset = Math.max(0, boardRegion.value.getBoundingClientRect().top);
    const availableHeight = viewportHeight - topOffset - 12;
    boardRegionHeight.value = `${Math.max(320, Math.floor(availableHeight))}px`;
}

onMounted(async () => {
    await nextTick();
    updateBoardRegionHeight();
    window.addEventListener('resize', updateBoardRegionHeight);
    window.addEventListener('orientationchange', updateBoardRegionHeight);
    window.addEventListener('scroll', updateBoardRegionHeight, { passive: true });
    window.visualViewport?.addEventListener('resize', updateBoardRegionHeight);
    window.visualViewport?.addEventListener('scroll', updateBoardRegionHeight);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateBoardRegionHeight);
    window.removeEventListener('orientationchange', updateBoardRegionHeight);
    window.removeEventListener('scroll', updateBoardRegionHeight);
    window.visualViewport?.removeEventListener('resize', updateBoardRegionHeight);
    window.visualViewport?.removeEventListener('scroll', updateBoardRegionHeight);
});

// Badge do ciclo de vida do planograma selecionado: sinaliza conclusão
// (aguardando revisão periódica) ou que já está em revisão periódica.
const lifecycleBadge = computed(() => {
    const planogram = props.selected_planogram;

    if (!planogram) {
        return null;
    }

    if (planogram.lifecycle_status === 'completed') {
        return {
            text: planogram.periodic_review_due_at
                ? t('app.kanban.lifecycle.awaiting_review', { date: formatDate(planogram.periodic_review_due_at) })
                : t('app.kanban.lifecycle.awaiting_review_no_date'),
            class: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300',
        };
    }

    if (planogram.lifecycle_status === 'periodic_review') {
        return {
            text: t('app.kanban.lifecycle.in_review'),
            class: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-300',
        };
    }

    return null;
});

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
    } else if (action === 'request_abandonment') {
        await requestDetailAbandonment();
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
    } else if (action === 'request_abandonment') {
        await requestAbandonment(execution);
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <Head :title="pageMeta.headTitle" />

        <template #header-actions>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button
                    v-if="props.can_create_gondola && props.selected_planogram"
                    type="button"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition hover:bg-muted"
                    @click="showGondolaCreate = true"
                >
                    <Plus class="size-3.5" />
                    {{ t('app.tenant.gondolas.actions.new') }}
                </button>

                <NewActionButton :href="PlanogramController.create.url()">
                    {{ t('app.tenant.planograms.actions.new') }}
                </NewActionButton>
            </div>
        </template>
        <KankanNavigationLinks />

        <div class="flex h-full min-h-0 flex-col overflow-hidden">
            <div class="border-b border-border bg-background px-4 py-3">
                <div v-if="lifecycleBadge" class="mb-2 flex">
                    <span
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium',
                            lifecycleBadge.class,
                        ]"
                    >
                        <RefreshCw class="size-3.5" />
                        {{ lifecycleBadge.text }}
                    </span>
                </div>

                <KanbanFilters
                    :planograms="props.planograms"
                    :stores="props.stores"
                    :users="props.users"
                    :filters="props.filters"
                />
            </div>

            <div
                ref="boardRegion"
                class="min-h-0 flex-1 overflow-hidden"
                :style="{ height: boardRegionHeight, maxHeight: boardRegionHeight }"
            >
                <div
                    v-if="filteredBoard.length === 0"
                    class="flex h-full flex-col items-center justify-center gap-3 text-muted-foreground"
                >
                    <Kanban class="size-10 opacity-20" />
                    <p class="text-sm">{{ t('app.kanban.empty_steps') }}</p>
                </div>

                <KanbanBoard
                    v-else
                    class="h-full min-h-0"
                    :board="filteredBoard"
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
                    @request-abandonment="requestCardAction('request_abandonment', $event)"
                />
            </div>
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
            :current-user-id="currentUserId"
            @update:action-notes="actionNotes = $event"
            @start="requestDetailAction('start')"
            @pause="requestDetailAction('pause')"
            @resume="requestDetailAction('resume')"
            @complete="requestDetailAction('complete')"
            @abandon="requestDetailAction('abandon')"
            @request-abandonment="requestDetailAction('request_abandonment')"
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

        <GondolaCreateStepper
            v-if="props.can_create_gondola && props.selected_planogram"
            :open="showGondolaCreate"
            :planogram-id="props.selected_planogram.id"
            :planogram-start-date="props.selected_planogram.start_date"
            :planogram-end-date="props.selected_planogram.end_date"
            :planogram-category-id="props.selected_planogram.category_id"
            @update:open="(val) => (showGondolaCreate = val)"
            @success="showGondolaCreate = false"
        />
    </AppLayout>
</template>
