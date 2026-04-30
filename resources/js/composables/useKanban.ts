import { router, useHttp } from '@inertiajs/vue3';
import { computed, ref, toValue } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import { toast } from 'vue-sonner';
import WorkflowExecutionController from '@/actions/App/Http/Controllers/Tenant/WorkflowExecutionController';
import type { BoardColumn, Execution, ExecutionDetails, WorkflowHistory } from '@/components/kanban/types';
import { useKanbanMove } from '@/composables/useKanbanMove';
import { useT } from '@/composables/useT';

export function useKanban(board: MaybeRefOrGetter<BoardColumn[] | null>, subdomain: MaybeRefOrGetter<string>) {
    const { t } = useT();
    const onlyOverdue = ref(false);
    const showCompleted = ref(true);
    const detailHttp = useHttp();
    const historyHttp = useHttp();
    const actionHttp = useHttp<{ notes: string | null; target_step_id: string | null }>({
        notes: null,
        target_step_id: null,
    });

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

    function statusLabel(status: string): string {
        return t(`app.kanban.status.${status}`);
    }

    function normalizedUrl(url: string): string {
        return url.replace(/^\/\/[^/]+/, '');
    }

    function denyMove(message: string): void {
        toast.error(message);
    }

    const {
        draggingExecutionId,
        dragOverStepId,
        onDragStart,
        onDragOver,
        onDragLeave,
        resolveDrop,
    } = useKanbanMove(
        filteredBoard,
        {
            mustBeStarted: t('app.kanban.move.must_be_started'),
            skippedStep: t('app.kanban.move.skipped_step'),
        },
        denyMove,
    );

    function reloadBoard(): void {
        router.reload({
            only: ['board', 'filters'],
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
        } catch {
            toast.error(t('app.kanban.messages.action_failed'));
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
            toast.error(t('app.kanban.messages.details_failed'));
            detailError.value = t('app.kanban.messages.details_failed');
        } finally {
            detailLoading.value = false;
        }
    }

    async function openExecutionDetails(execution: Execution): Promise<void> {
        detailOpen.value = true;
        actionNotes.value = '';

        await loadExecutionDetails(execution.id);
    }

    async function onDrop(targetColumn: BoardColumn): Promise<void> {
        const moveAttempt = resolveDrop(targetColumn);

        if (!moveAttempt) {
            return;
        }

        const { executionId } = moveAttempt;
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
        } catch {
            denyMove(t('app.kanban.move.failed'));
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
