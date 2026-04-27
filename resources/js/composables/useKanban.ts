import { router, useHttp } from '@inertiajs/vue3';
import { computed, ref, toValue } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import WorkflowExecutionController from '@/actions/App/Http/Controllers/Tenant/WorkflowExecutionController';
import type { BoardColumn, Execution, ExecutionDetails, WorkflowHistory } from '@/components/kanban/types';

export function useKanban(board: MaybeRefOrGetter<BoardColumn[] | null>, subdomain: MaybeRefOrGetter<string>) {
    const onlyOverdue = ref(false);
    const showCompleted = ref(true);
    const detailHttp = useHttp();
    const historyHttp = useHttp();
    const actionHttp = useHttp<{ notes: string | null; target_step_id: string | null }>({
        notes: null,
        target_step_id: null,
    });

    const draggingExecutionId = ref<string | null>(null);
    const draggingFromStepId = ref<string | null>(null);
    const dragOverStepId = ref<string | null>(null);
    const busyExecutionId = ref<string | null>(null);

    const detailOpen = ref(false);
    const detailLoading = ref(false);
    const detailError = ref<string | null>(null);
    const detailPayload = ref<ExecutionDetails | null>(null);
    const detailHistories = ref<WorkflowHistory[]>([]);
    const actionNotes = ref('');

    const filteredBoard = computed((): BoardColumn[] => {
        const columns = toValue(board);

        if (!columns) {
            return [];
        }

        return columns.map((column) => ({
            ...column,
            executions: column.executions.filter((execution) => {
                if (!showCompleted.value && execution.status === 'completed') {
                    return false;
                }

                if (onlyOverdue.value && !isOverdue(execution)) {
                    return false;
                }

                return true;
            }),
        }));
    });

    function isOverdue(execution: Execution): boolean {
        if (!execution.sla_date) {
            return false;
        }

        return new Date(execution.sla_date) < new Date();
    }

    function formatDate(iso: string | null): string {
        if (!iso) {
            return '-';
        }

        return new Date(iso).toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: '2-digit',
        });
    }

    const statusColors: Record<string, string> = {
        pending: 'bg-muted text-muted-foreground',
        active: 'bg-primary/15 text-primary',
        paused: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        completed: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        cancelled: 'bg-destructive/15 text-destructive',
    };

    const statusLabels: Record<string, string> = {
        pending: 'Pendente',
        active: 'Em andamento',
        paused: 'Pausado',
        completed: 'Concluído',
        cancelled: 'Cancelado',
    };

    function statusLabel(status: string): string {
        return statusLabels[status] ?? status;
    }

    function normalizedUrl(url: string): string {
        return url.replace(/^\/\/[^/]+/, '');
    }

    function reloadBoard(): void {
        router.reload({
            only: ['board'],
        });
    }

    async function submitExecutionAction(
        execution: Pick<Execution, 'id'>,
        action: 'start' | 'pause' | 'resume' | 'complete' | 'abandon',
    ): Promise<void> {
        busyExecutionId.value = execution.id;
        actionHttp.notes = actionNotes.value.trim() || null;

        try {
            const route = WorkflowExecutionController[action]({
                subdomain: toValue(subdomain),
                execution: execution.id,
            });

            await actionHttp.submit({
                ...route,
                url: normalizedUrl(route.url),
            });

            actionNotes.value = '';
            reloadBoard();

            if (detailOpen.value) {
                await loadExecutionDetails(execution.id);
            }
        } finally {
            actionHttp.notes = null;
            busyExecutionId.value = null;
        }
    }

    async function startExecution(execution: Execution): Promise<void> {
        await submitExecutionAction(execution, 'start');
    }

    async function startDetailExecution(): Promise<void> {
        const execution = detailPayload.value?.execution;

        if (execution) {
            await submitExecutionAction(execution, 'start');
        }
    }

    async function pauseExecution(execution: Execution): Promise<void> {
        await submitExecutionAction(execution, 'pause');
    }

    async function pauseDetailExecution(): Promise<void> {
        const execution = detailPayload.value?.execution;

        if (execution) {
            await submitExecutionAction(execution, 'pause');
        }
    }

    async function resumeExecution(execution: Execution): Promise<void> {
        await submitExecutionAction(execution, 'resume');
    }

    async function resumeDetailExecution(): Promise<void> {
        const execution = detailPayload.value?.execution;

        if (execution) {
            await submitExecutionAction(execution, 'resume');
        }
    }

    async function completeExecution(execution: Execution): Promise<void> {
        await submitExecutionAction(execution, 'complete');
    }

    async function completeDetailExecution(): Promise<void> {
        const execution = detailPayload.value?.execution;

        if (execution) {
            await submitExecutionAction(execution, 'complete');
        }
    }

    async function abandonExecution(execution: Execution): Promise<void> {
        await submitExecutionAction(execution, 'abandon');
    }

    async function abandonDetailExecution(): Promise<void> {
        const execution = detailPayload.value?.execution;

        if (execution) {
            await submitExecutionAction(execution, 'abandon');
        }
    }

    async function loadExecutionDetails(executionId: string): Promise<void> {
        detailLoading.value = true;
        detailError.value = null;
        detailPayload.value = null;
        detailHistories.value = [];

        try {
            const detailsRoute = WorkflowExecutionController.details({
                subdomain: toValue(subdomain),
                execution: executionId,
            });
            const historyRoute = WorkflowExecutionController.history({
                subdomain: toValue(subdomain),
                execution: executionId,
            });

            detailPayload.value = await detailHttp.submit({
                ...detailsRoute,
                url: normalizedUrl(detailsRoute.url),
            }) as ExecutionDetails;

            const historyPayload = await historyHttp.submit({
                ...historyRoute,
                url: normalizedUrl(historyRoute.url),
            }) as { histories: WorkflowHistory[] };

            detailHistories.value = historyPayload.histories;
        } catch (error) {
            console.error(error);
            detailError.value = 'Não foi possível carregar os detalhes da execução.';
        } finally {
            detailLoading.value = false;
        }
    }

    async function openExecutionDetails(execution: Execution): Promise<void> {
        detailOpen.value = true;
        actionNotes.value = '';

        await loadExecutionDetails(execution.id);
    }

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
        actionHttp.notes = null;

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

            if (detailOpen.value) {
                await loadExecutionDetails(executionId);
            }
        } finally {
            actionHttp.target_step_id = null;
            actionHttp.notes = null;
            busyExecutionId.value = null;
        }
    }

    return {
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
    };
}
