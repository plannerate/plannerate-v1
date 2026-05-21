import { computed } from 'vue';
import type { Segment, Shelf } from '@/types/planogram';
import { usePlanogramEditor } from './usePlanogramEditor';
import { shouldShowDeleteConfirm } from './usePlanogramUtils';

// Map global para rastrear segmentos em movimento (previne execução dupla)
// Exportado para ser compartilhado com usePlanogramKeyboard
export const segmentsMoving = new Map<string, boolean>();

/**
 * Composable para ações de segmento
 * Centraliza lógica compartilhada entre keyboard handlers e componentes UI
 */
export function useSegmentActions(
    segment: Segment | (() => Segment),
    shelf?: Shelf | (() => Shelf),
) {
    const editor = usePlanogramEditor();

    // Normaliza segment e shelf para computed
    const segmentRef =
        typeof segment === 'function'
            ? computed(segment)
            : computed(() => segment);
    const shelfRef = shelf
        ? typeof shelf === 'function'
            ? computed(shelf)
            : computed(() => shelf)
        : computed(() => {
              const found = editor.findSegmentById(segmentRef.value.id);

              return found?.shelf;
          });

    /**
     * Retorna os segmentos ativos da prateleira ordenados por ordering
     */
    function getOrderedSegments(): Segment[] {
        const currentShelf = shelfRef.value;

        if (!currentShelf?.segments) {
            return [];
        }

        return [...currentShelf.segments]
            .filter((s: Segment) => !s.deleted_at)
            .sort(
                (a: Segment, b: Segment) =>
                    (a.ordering || 0) - (b.ordering || 0),
            );
    }

    /**
     * Verifica se o segmento atual pode ser movido para a esquerda
     */
    const canMoveLeft = computed(() => {
        const currentSegment = segmentRef.value;

        if (!currentSegment?.id) {
            return false;
        }

        const orderedSegments = getOrderedSegments();
        const currentIndex = orderedSegments.findIndex(
            (s: Segment) => s.id === currentSegment.id,
        );

        return currentIndex > 0;
    });

    /**
     * Verifica se o segmento atual pode ser movido para a direita
     */
    const canMoveRight = computed(() => {
        const currentSegment = segmentRef.value;

        if (!currentSegment?.id) {
            return false;
        }

        const orderedSegments = getOrderedSegments();
        const currentIndex = orderedSegments.findIndex(
            (s: Segment) => s.id === currentSegment.id,
        );

        return currentIndex >= 0 && currentIndex < orderedSegments.length - 1;
    });

    /**
     * Encontra o ID do segmento adjacente ao atual na direção indicada.
     * Retorna null se não houver segmento na posição.
     * offset: -1 = esquerda, +1 = direita
     */
    function findAdjacentSegmentId(offset: -1 | 1): string | null {
        const currentSegment = segmentRef.value;

        if (!currentSegment?.id) {
            return null;
        }

        const orderedSegments = getOrderedSegments();
        const currentIndex = orderedSegments.findIndex(
            (s) => s.id === currentSegment.id,
        );

        if (currentIndex < 0) {
            return null;
        }

        const targetIndex = currentIndex + offset;

        if (targetIndex < 0 || targetIndex >= orderedSegments.length) {
            return null;
        }

        return orderedSegments[targetIndex].id;
    }

    /**
     * Move segmento para a esquerda (Ctrl+ArrowLeft).
     * Delega a editor.swapSegmentPositions para que a operação seja
     * registrada no histórico e suporte undo/redo.
     */
    function moveLeft(): boolean {
        const currentSegment = segmentRef.value;

        if (!currentSegment?.id) {
            return false;
        }

        // Previne execução dupla usando Map global
        if (segmentsMoving.get(currentSegment.id)) {
            return false;
        }

        segmentsMoving.set(currentSegment.id, true);

        try {
            const targetId = findAdjacentSegmentId(-1);

            if (!targetId) {
                return false;
            }

            return editor.swapSegmentPositions(currentSegment.id, targetId);
        } finally {
            // Libera o flag após um pequeno delay para garantir re-renderização
            setTimeout(() => {
                segmentsMoving.delete(currentSegment.id);
            }, 200);
        }
    }

    /**
     * Move segmento para a direita (Ctrl+ArrowRight).
     * Delega a editor.swapSegmentPositions para que a operação seja
     * registrada no histórico e suporte undo/redo.
     */
    function moveRight(): boolean {
        const currentSegment = segmentRef.value;

        if (!currentSegment?.id) {
            return false;
        }

        // Previne execução dupla usando Map global
        if (segmentsMoving.get(currentSegment.id)) {
            return false;
        }

        segmentsMoving.set(currentSegment.id, true);

        try {
            const targetId = findAdjacentSegmentId(1);

            if (!targetId) {
                return false;
            }

            return editor.swapSegmentPositions(currentSegment.id, targetId);
        } finally {
            // Libera o flag após um pequeno delay para garantir re-renderização
            setTimeout(() => {
                segmentsMoving.delete(currentSegment.id);
            }, 200);
        }
    }

    /**
     * Verifica se deve mostrar modal de confirmação baseado no localStorage
     */
    function shouldShowDeleteConfirmLocal(itemType: string = 'layer'): boolean {
        return shouldShowDeleteConfirm(itemType);
    }

    return {
        // Computed states
        canMoveLeft,
        canMoveRight,

        // Actions
        moveLeft,
        moveRight,
        shouldShowDeleteConfirm: shouldShowDeleteConfirmLocal,
    };
}
