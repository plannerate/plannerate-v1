import { ref } from 'vue';

import type { CategoryTreeStore } from './useCategoryTree';

/** Alvo de soltura: id de uma categoria ou a raiz do mercadológico. */
export const ROOT_TARGET = 'ROOT' as const;

export type DropTarget = string | typeof ROOT_TARGET;

/**
 * Estado do drag & drop nativo da árvore de categorias (mesmo padrão do Kanban).
 *
 * Mantém o nó arrastado e o alvo sob o cursor, aplica um guard client-side
 * (não solta em si mesmo, num descendente já carregado ou no pai atual) e
 * delega o move validado para `requestMove` (a página cuida da confirmação e do
 * POST). O guard definitivo — inclusive para ramos ainda não carregados — fica
 * no backend.
 */
export function useCategoryDrag(
    store: CategoryTreeStore,
    requestMove: (draggedId: string, target: DropTarget) => void,
) {
    const draggingId = ref<string | null>(null);
    const dragOverTarget = ref<DropTarget | null>(null);

    function onDragStart(id: string, event?: DragEvent): void {
        draggingId.value = id;

        // Alguns navegadores (Firefox e certos modos do Chromium) só iniciam o
        // arraste nativo se algum dado for setado no dragstart — sem isso o
        // `drop` nunca dispara e "não move".
        if (event?.dataTransfer) {
            event.dataTransfer.setData('text/plain', id);
            event.dataTransfer.effectAllowed = 'move';
        }
    }

    function onDragEnd(): void {
        draggingId.value = null;
        dragOverTarget.value = null;
    }

    /**
     * Um nó pode receber o arrastado?
     */
    function canDrop(target: DropTarget): boolean {
        const dragged = draggingId.value;

        if (!dragged) {
            return false;
        }

        const state = store.getNode(dragged);

        if (target === ROOT_TARGET) {
            // Só faz sentido mover para a raiz se ainda não for raiz.
            return state?.parentId != null;
        }

        // Não pode soltar em si mesmo nem num descendente já carregado.
        if (store.isSelfOrLoadedDescendant(dragged, target)) {
            return false;
        }

        // Evita o no-op de soltar no próprio pai atual.
        return state?.parentId !== target;
    }

    function onDragOver(target: DropTarget, event?: DragEvent): void {
        // Como a origem seta effectAllowed='move', o alvo PRECISA setar
        // dropEffect='move' aqui — senão o navegador resolve para 'none' e o
        // `drop` é rejeitado (o item "não sai do lugar").
        if (event?.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }

        if (canDrop(target)) {
            dragOverTarget.value = target;
        }
    }

    function onDragLeave(target: DropTarget): void {
        if (dragOverTarget.value === target) {
            dragOverTarget.value = null;
        }
    }

    function onDrop(target: DropTarget): void {
        const dragged = draggingId.value;

        if (dragged && canDrop(target)) {
            requestMove(dragged, target);
        }

        onDragEnd();
    }

    return {
        draggingId,
        dragOverTarget,
        onDragStart,
        onDragEnd,
        onDragOver,
        onDragLeave,
        onDrop,
        canDrop,
    };
}

export type CategoryDragController = ReturnType<typeof useCategoryDrag>;
