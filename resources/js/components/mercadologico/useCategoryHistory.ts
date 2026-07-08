import { computed, ref } from 'vue';

/**
 * Entrada da pilha de histórico: cada ação sabe se desfazer e se refazer
 * (thunks que chamam os endpoints e atualizam a árvore).
 */
export type HistoryEntry = {
    label: string;
    undo: () => Promise<void>;
    redo: () => Promise<void>;
};

/**
 * Histórico de desfazer/refazer para as ações do mercadológico (mover, criar,
 * editar, excluir). Client-side: cada ação registra como se inverter.
 */
export function useCategoryHistory() {
    const undoStack = ref<HistoryEntry[]>([]);
    const redoStack = ref<HistoryEntry[]>([]);
    const busy = ref(false);

    const canUndo = computed(() => undoStack.value.length > 0 && !busy.value);
    const canRedo = computed(() => redoStack.value.length > 0 && !busy.value);

    /** Registra uma ação já executada (limpa a pilha de refazer). */
    function record(entry: HistoryEntry): void {
        undoStack.value.push(entry);
        redoStack.value = [];
    }

    async function undo(): Promise<HistoryEntry | null> {
        if (undoStack.value.length === 0 || busy.value) {
            return null;
        }

        const entry = undoStack.value[undoStack.value.length - 1];
        busy.value = true;

        try {
            await entry.undo();
            undoStack.value.pop();
            redoStack.value.push(entry);

            return entry;
        } finally {
            busy.value = false;
        }
    }

    async function redo(): Promise<HistoryEntry | null> {
        if (redoStack.value.length === 0 || busy.value) {
            return null;
        }

        const entry = redoStack.value[redoStack.value.length - 1];
        busy.value = true;

        try {
            await entry.redo();
            redoStack.value.pop();
            undoStack.value.push(entry);

            return entry;
        } finally {
            busy.value = false;
        }
    }

    return { canUndo, canRedo, busy, record, undo, redo };
}

export type CategoryHistory = ReturnType<typeof useCategoryHistory>;
