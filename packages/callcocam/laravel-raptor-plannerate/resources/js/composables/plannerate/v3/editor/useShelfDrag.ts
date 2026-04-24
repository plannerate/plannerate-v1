import type { Shelf as ShelfType } from '@/types/planogram';
import { onBeforeUnmount, ref, type Ref } from 'vue';
import {
    draggingShelfId,
    draggingShelfOffset,
    draggingShelfSectionId,
} from './useGondolaState';

interface UseShelfDragOptions {
    shelf: Ref<ShelfType>;
    sectionId: Ref<string>;
    dragThreshold?: number;
}

export function useShelfDrag(options: UseShelfDragOptions) {
    const dragThreshold = options.dragThreshold ?? 2;

    const isDraggingShelf = ref(false);
    const canDrag = ref(false);
    const mouseDownPos = ref<{ x: number; y: number } | null>(null);
    const initialShelfPosition = ref<number | null>(null);

    const cleanup = () => {
        document.removeEventListener('mousemove', handleGlobalMouseMove);
        document.removeEventListener('mouseup', handleGlobalMouseUp);
        mouseDownPos.value = null;
        canDrag.value = false;
        initialShelfPosition.value = null;
    };

    function handleGlobalMouseMove(event: MouseEvent) {
        if (!mouseDownPos.value) return;

        const dx = event.clientX - mouseDownPos.value.x;
        const dy = event.clientY - mouseDownPos.value.y;

        if (Math.sqrt(dx * dx + dy * dy) >= dragThreshold) {
            canDrag.value = true;
        }
    }

    function handleGlobalMouseUp() {
        cleanup();
    }

    function handleMouseDown(event: MouseEvent) {
        if (event.button !== 0) return;

        mouseDownPos.value = { x: event.clientX, y: event.clientY };
        canDrag.value = false;
        initialShelfPosition.value = options.shelf.value.shelf_position;
        document.addEventListener('mousemove', handleGlobalMouseMove);
        document.addEventListener('mouseup', handleGlobalMouseUp);
    }

    function handleShelfDragStart(event: DragEvent) {
        if (!canDrag.value) {
            event.preventDefault();
            return;
        }

        isDraggingShelf.value = true;
        draggingShelfId.value = options.shelf.value.id;
        draggingShelfSectionId.value = options.sectionId.value;

        if (initialShelfPosition.value === null) {
            initialShelfPosition.value = options.shelf.value.shelf_position;
        }

        const target = event.currentTarget as HTMLElement;
        const rect = target.getBoundingClientRect();
        draggingShelfOffset.value = event.clientY - rect.top;

        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('shelfId', options.shelf.value.id);
            event.dataTransfer.setData('sectionId', options.sectionId.value);
            event.dataTransfer.setDragImage(target, event.offsetX, event.offsetY);
        }
    }

    function handleShelfDragEnd() {
        cleanup();
        isDraggingShelf.value = false;
        draggingShelfId.value = null;
        draggingShelfSectionId.value = null;
        draggingShelfOffset.value = 0;
        initialShelfPosition.value = null;
    }

    onBeforeUnmount(cleanup);

    return {
        isDraggingShelf,
        handleMouseDown,
        handleShelfDragStart,
        handleShelfDragEnd,
    };
}
