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
     * Busca segmentos ordenados da prateleira
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
     * Verifica se pode mover para esquerda
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
     * Verifica se pode mover para direita
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

    function swapSegment(offset: -1 | 1): boolean {
        const currentSegment = segmentRef.value;

        if (!currentSegment?.id) {
return false;
}

        const found = editor.findSegmentById(currentSegment.id);

        if (!found || !found.shelf?.segments) {
return false;
}

        // Busca todos os segmentos não deletados e ordena por ordering
        const orderedSegments = [...found.shelf.segments]
            .filter((s: Segment) => !s.deleted_at)
            .sort((a: Segment, b: Segment) => {
                const ordA = a.ordering ?? 0;
                const ordB = b.ordering ?? 0;

                if (ordA !== ordB) {
return ordA - ordB;
}

                return 0;
            });

        const currentIndex = orderedSegments.findIndex(
            (seg) => seg.id === currentSegment.id,
        );

        if (currentIndex < 0) {
return false;
}

        const targetIndex = currentIndex + offset;

        if (targetIndex < 0 || targetIndex >= orderedSegments.length) {
return false;
}

        // Guarda os orderings originais antes da troca para comparação
        const originalOrderings = new Map<string, number>();
        orderedSegments.forEach((seg) => {
            originalOrderings.set(seg.id, seg.ordering ?? 0);
        });

        // Troca as posições
        const swapped = [...orderedSegments];
        [swapped[currentIndex], swapped[targetIndex]] = [
            swapped[targetIndex],
            swapped[currentIndex],
        ];

        // Atualiza o ordering de todos os segmentos afetados e cria novos objetos para reatividade
        const reorderedSegments: Segment[] = swapped.map((seg, idx) => {
            const newOrdering = idx + 1;

            // Cria novo objeto com ordering atualizado
            return { ...seg, ordering: newOrdering };
        });

        // Registra mudanças para persistência (apenas para segmentos que mudaram de ordering)
        reorderedSegments.forEach((seg) => {
            const originalOrdering = originalOrderings.get(seg.id) ?? 0;
            const newOrdering = seg.ordering ?? 0;

            if (originalOrdering !== newOrdering) {
                // Registra mudança para persistência
                editor.recordChange({
                    type: 'segment_update',
                    entityType: 'segment',
                    entityId: seg.id,
                    data: { ordering: newOrdering },
                });
            }
        });

        // Força reatividade criando novos arrays/objetos em cada nível da hierarquia
        // 1. Atualiza o array de segmentos da prateleira
        const updatedSegments = [...reorderedSegments];
        found.shelf.segments = updatedSegments;

        // 2. Atualiza a prateleira na seção
        const updatedShelves = [...found.section.shelves];
        const shelfIndex = updatedShelves.findIndex(
            (s: any) => s.id === found.shelf.id,
        );

        if (shelfIndex !== -1) {
            updatedShelves[shelfIndex] = {
                ...found.shelf,
                segments: updatedSegments,
            };
            found.section.shelves = updatedShelves;
        }

        // 3. Atualiza a seção na gôndola
        if (editor.currentGondola.value?.sections) {
            const updatedSections = [...editor.currentGondola.value.sections];
            const sectionIndex = updatedSections.findIndex(
                (s: any) => s.id === found.section.id,
            );

            if (sectionIndex !== -1) {
                updatedSections[sectionIndex] = {
                    ...found.section,
                    shelves: updatedShelves,
                };
                editor.currentGondola.value.sections = updatedSections;
            }
        }

        return true;
    }

    /**
     * Move segmento para esquerda (mesma lógica do Ctrl+ArrowLeft)
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

        // Marca como em movimento para prevenir execução dupla
        segmentsMoving.set(currentSegment.id, true);

        try {
            return swapSegment(-1);
        } finally {
            // Libera o flag após um pequeno delay para garantir que a re-renderização aconteceu
            setTimeout(() => {
                segmentsMoving.delete(currentSegment.id);
            }, 200);
        }
    }

    /**
     * Move segmento para direita (mesma lógica do Ctrl+ArrowRight)
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

        // Marca como em movimento para prevenir execução dupla
        segmentsMoving.set(currentSegment.id, true);

        try {
            return swapSegment(1);
        } finally {
            // Libera o flag após um pequeno delay para garantir que a re-renderização aconteceu
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
