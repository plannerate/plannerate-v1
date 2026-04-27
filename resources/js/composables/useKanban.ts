import { computed, ref, toValue, type MaybeRefOrGetter } from 'vue';
import type { BoardColumn, Execution } from '@/components/kanban/types';

export function useKanban(board: MaybeRefOrGetter<BoardColumn[] | null>) {
    const onlyOverdue = ref(false);
    const showCompleted = ref(true);

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

    return {
        onlyOverdue,
        showCompleted,
        filteredBoard,
        isOverdue,
        formatDate,
        statusColors,
        statusLabel,
    };
}
