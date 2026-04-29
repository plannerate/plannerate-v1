import { computed } from 'vue';
import type { Section, Shelf } from '@/types/planogram';
import { usePlanogramEditor } from './usePlanogramEditor';
import { shouldShowDeleteConfirm } from './usePlanogramUtils';
import { toCamelCase, DEFAULT_SECTION_FIELDS } from './useSectionFields';
import { calculateHolePositions } from './useSectionHoles';

// Map global para rastrear shelves em movimento entre seções (previne execução dupla)
// Exportado para ser compartilhado com usePlanogramKeyboard
export const shelvesMovingBetweenSections = new Map<string, boolean>();

/**
 * Composable para ações de prateleira
 * Centraliza lógica compartilhada entre keyboard handlers e componentes UI
 */
export function useShelfActions(
    shelf: Shelf | (() => Shelf),
    section?: Section | (() => Section),
) {
    const editor = usePlanogramEditor();

    // Normaliza shelf e section para computed
    const shelfRef =
        typeof shelf === 'function' ? computed(shelf) : computed(() => shelf);
    const sectionRef = section
        ? typeof section === 'function'
            ? computed(section)
            : computed(() => section)
        : computed(() => {
              const found = editor.findShelfById(shelfRef.value.id);

              return found?.section;
          });

    /**
     * Verifica se pode mover para cima (diminuir shelf_position)
     * Posição 0 = topo, valores maiores = mais embaixo
     */
    const canMoveUp = computed(() => {
        const currentShelf = shelfRef.value;
        const currentSection = sectionRef.value;

        if (!currentShelf?.id || !currentSection?.id) {
return false;
}

        const currentShelfData = editor.findShelfById(currentShelf.id);
        const currentPosition = currentShelfData?.shelf.shelf_position || 0;

        // Pode mover para cima se a posição atual é maior que 0
        return currentPosition > 0;
    });

    /**
     * Verifica se pode mover para baixo (aumentar shelf_position)
     * Posição 0 = topo, valores maiores = mais embaixo
     */
    const canMoveDown = computed(() => {
        const currentShelf = shelfRef.value;
        const currentSection = sectionRef.value;

        if (!currentShelf?.id || !currentSection?.id) {
return false;
}

        const currentShelfData = editor.findShelfById(currentShelf.id);
        const currentPosition = currentShelfData?.shelf.shelf_position || 0;
        const shelfHeight = currentShelf.shelf_height || 0;

        // Converte para coordenadas da área útil
        const sectionCamel = toCamelCase(currentSection);
        const baseHeightCm =
            sectionCamel.baseHeight ?? DEFAULT_SECTION_FIELDS.baseHeight;

        // A prateleira não pode ultrapassar a área útil
        // shelf_position máximo = baseHeight + usableHeight - shelfHeight
        const maxPosition = currentSection.height - baseHeightCm - shelfHeight;

        // Pode mover para baixo se a posição atual é menor que o máximo
        return currentPosition < maxPosition;
    });

    /**
     * Verifica se pode mover para seção esquerda
     * Usa o array original para manter consistência com moveLeft
     */
    const canMoveLeft = computed(() => {
        const currentShelf = shelfRef.value;
        const currentSection = sectionRef.value;

        if (!currentShelf?.id || !currentSection?.id) {
return false;
}

        const gondola = editor.currentGondola.value;

        if (!gondola?.sections) {
return false;
}

        const currentIndex = gondola.sections.findIndex(
            (s: Section) => s.id === currentSection.id,
        );

        return currentIndex > 0;
    });

    /**
     * Verifica se pode mover para seção direita
     * Usa o array original para manter consistência com moveRight
     */
    const canMoveRight = computed(() => {
        const currentShelf = shelfRef.value;
        const currentSection = sectionRef.value;

        if (!currentShelf?.id || !currentSection?.id) {
return false;
}

        const gondola = editor.currentGondola.value;

        if (!gondola?.sections) {
return false;
}

        const currentIndex = gondola.sections.findIndex(
            (s: Section) => s.id === currentSection.id,
        );

        return currentIndex >= 0 && currentIndex < gondola.sections.length - 1;
    });

    /**
     * Move prateleira para cima (diminui shelf_position)
     * Posição 0 = topo, valores maiores = mais embaixo
     */
    function moveUp(): boolean {
        const currentShelf = shelfRef.value;
        const currentSection = sectionRef.value;

        if (!currentShelf?.id || !currentSection?.id) {
return false;
}

        const currentShelfData = editor.findShelfById(currentShelf.id);
        const currentPosition = currentShelfData?.shelf.shelf_position || 0;

        // Se já está no topo, não move
        if (Math.abs(currentPosition - 0) < 0.1) {
return false;
}

        const sectionCamel = toCamelCase(currentSection);
        const baseHeightCm =
            sectionCamel.baseHeight ?? DEFAULT_SECTION_FIELDS.baseHeight;
        const holePositions = calculateHolePositions(currentSection);

        // Encontra o furo imediatamente anterior (maior valor menor que a posição atual)
        const currentPositionInUsableArea = currentPosition - baseHeightCm;
        const previousHoleInUsableArea = holePositions
            .slice()
            .reverse()
            .find((pos) => pos < currentPositionInUsableArea);

        let targetPosition: number;

        if (previousHoleInUsableArea !== undefined) {
            // Move para o furo anterior
            targetPosition = baseHeightCm + previousHoleInUsableArea;
        } else {
            // Se não há furo anterior, aproxima ao topo em passo do furo
            const stepSize =
                (sectionCamel.holeHeight ?? DEFAULT_SECTION_FIELDS.holeHeight) +
                (sectionCamel.holeSpacing ?? DEFAULT_SECTION_FIELDS.holeSpacing);
            targetPosition = Math.max(0, currentPosition - stepSize);
        }

        editor.updateShelf(currentShelf.id, { shelf_position: targetPosition });

        return true;
    }

    /**
     * Move prateleira para baixo (aumenta shelf_position)
     * Posição 0 = topo, valores maiores = mais embaixo
     */
    function moveDown(): boolean {
        const currentShelf = shelfRef.value;
        const currentSection = sectionRef.value;

        if (!currentShelf?.id || !currentSection?.id) {
return false;
}

        const currentShelfData = editor.findShelfById(currentShelf.id);
        const currentPosition = currentShelfData?.shelf.shelf_position || 0;
        const shelfHeight = currentShelf.shelf_height || 4;

        const sectionCamel = toCamelCase(currentSection);
        const baseHeightCm =
            sectionCamel.baseHeight ?? DEFAULT_SECTION_FIELDS.baseHeight;
        
        // Limite máximo: altura da seção - altura da base - altura da prateleira
        const maxPosition = currentSection.height - baseHeightCm - shelfHeight;
        
        // Se já está no máximo permitido, não move
        if (Math.abs(currentPosition - maxPosition) < 0.1) {
return false;
}

        const holePositions = calculateHolePositions(currentSection);
        const currentPositionInUsableArea = currentPosition - baseHeightCm;

        let targetPosition: number;

        // Passo suave perto do topo: usa o tamanho do furo (altura+espaçamento)
        const stepSize =
            (sectionCamel.holeHeight ?? DEFAULT_SECTION_FIELDS.holeHeight) +
            (sectionCamel.holeSpacing ?? DEFAULT_SECTION_FIELDS.holeSpacing);
        const firstHoleInUsableArea = holePositions[0] ?? 0;

        if (currentPositionInUsableArea < firstHoleInUsableArea - 0.01) {
            const candidate = currentPosition + stepSize;
            const firstHoleTotal = baseHeightCm + firstHoleInUsableArea;
            targetPosition = Math.min(maxPosition, firstHoleTotal, candidate);
            
            if (Math.abs(targetPosition - currentPosition) < 0.1) {
                return false;
            }
        } else {
            // Encontra o próximo furo válido (menor valor maior que a posição atual)
            const nextHoleInUsableArea = holePositions.find(
                (pos) => pos > currentPositionInUsableArea,
            );

            if (nextHoleInUsableArea !== undefined) {
                const nextHole = baseHeightCm + nextHoleInUsableArea;

                if (nextHole <= maxPosition) {
                    targetPosition = nextHole;
                } else {
                    targetPosition = maxPosition;
                }
            } else {
                // Se não há furo válido, vai para o máximo permitido
                targetPosition = maxPosition;
            }
        }

        editor.updateShelf(currentShelf.id, { shelf_position: targetPosition });

        return true;
    }

    /**
     * Move prateleira entre seções (esquerda/direita)
     */
    function moveBetweenSections(direction: -1 | 1): boolean {
        const currentShelf = shelfRef.value;

        if (
            !currentShelf?.id ||
            shelvesMovingBetweenSections.get(currentShelf.id)
        ) {
return false;
}

        const currentShelfData = editor.findShelfById(currentShelf.id);

        if (!currentShelfData?.shelf || !currentShelfData?.section) {
return false;
}

        const displaySections = editor.sectionsOrdered.value;
        const currentIndex = displaySections.findIndex(
            (s: Section) => s.id === currentShelfData.section.id,
        );
        const targetIndex = currentIndex + direction;

        if (targetIndex < 0 || targetIndex >= displaySections.length) {
return false;
}

        const targetSection = displaySections[targetIndex];

        if (!targetSection || targetSection.deleted_at) {
return false;
}

        shelvesMovingBetweenSections.set(currentShelf.id, true);

        try {
            editor.updateShelf(currentShelf.id, {
                section_id: targetSection.id,
                shelf_position: currentShelfData.shelf.shelf_position || 0,
            });

            return true;
        } finally {
            setTimeout(
                () => shelvesMovingBetweenSections.delete(currentShelf.id),
                200,
            );
        }
    }

    const moveLeft = () => moveBetweenSections(-1);
    const moveRight = () => moveBetweenSections(1);

    /**
     * Verifica se deve mostrar modal de confirmação baseado no localStorage
     */
    function shouldShowDeleteConfirmLocal(itemType: string = 'shelf'): boolean {
        return shouldShowDeleteConfirm(itemType);
    }

    return {
        // Computed states
        canMoveUp,
        canMoveDown,
        canMoveLeft,
        canMoveRight,

        // Actions
        moveUp,
        moveDown,
        moveLeft,
        moveRight,
        shouldShowDeleteConfirm: shouldShowDeleteConfirmLocal,
    };
}
