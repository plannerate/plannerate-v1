import type { Section } from '@/types/planogram';
import { computed } from 'vue';
import { usePlanogramEditor } from './usePlanogramEditor';
import { usePlanogramSelection } from './usePlanogramSelection';
import { shouldShowDeleteConfirm } from './usePlanogramUtils';

// Map global para rastrear seções em movimento (previne execução dupla)
const sectionsMoving = new Map<string, boolean>();

/**
 * Composable para ações de seção
 * Centraliza lógica compartilhada entre keyboard handlers e componentes UI
 */
export function useSectionActions(section: Section | (() => Section)) {
    const editor = usePlanogramEditor();
    const selection = usePlanogramSelection();

    // Normaliza section para computed
    const sectionRef =
        typeof section === 'function'
            ? computed(section)
            : computed(() => section);

    /**
     * Verifica se pode inverter prateleiras (precisa de pelo menos 2 prateleiras)
     */
    const canInvertShelves = computed(() => {
        const currentSection = sectionRef.value;
        if (!currentSection?.shelves) return false;
        const activeShelves = currentSection.shelves.filter(
            (s: any) => !s.deleted_at,
        );
        return activeShelves.length >= 2;
    });

    /**
     * Verifica se pode mover para esquerda
     */
    const canMoveLeft = computed(() => {
        const currentSection = sectionRef.value;
        if (!currentSection?.id) return false;

        const displaySections = editor.sectionsOrdered.value;
        const currentIndex = displaySections.findIndex(
            (s: Section) => s.id === currentSection.id,
        );
        return currentIndex > 0;
    });

    /**
     * Verifica se pode mover para direita
     */
    const canMoveRight = computed(() => {
        const currentSection = sectionRef.value;
        if (!currentSection?.id) return false;

        const displaySections = editor.sectionsOrdered.value;
        const currentIndex = displaySections.findIndex(
            (s: Section) => s.id === currentSection.id,
        );
        return currentIndex >= 0 && currentIndex < displaySections.length - 1;
    });

    /**
     * Inverte a ordem das prateleiras (mesma lógica do Ctrl+I)
     */
    function invertShelves(): void {
        const currentSection = sectionRef.value;
        if (!currentSection?.id) return;
        editor.invertShelvesOrder(currentSection.id);
    }

    /**
     * Move seção entre posições (esquerda/direita)
     * Troca orderings e registra apenas mudanças efetivas
     */
    function moveBetweenPositions(direction: -1 | 1): boolean {
        const currentSection = sectionRef.value;
        if (!currentSection?.id || sectionsMoving.get(currentSection.id))
            return false;

        const displaySections = editor.sectionsOrdered.value;
        const currentIndex = displaySections.findIndex(
            (s: Section) => s.id === currentSection.id,
        );
        const targetIndex = currentIndex + direction;

        if (targetIndex < 0 || targetIndex >= displaySections.length)
            return false;

        const currentSectionData = displaySections[currentIndex];
        const targetSection = displaySections[targetIndex];

        sectionsMoving.set(currentSection.id, true);
        sectionsMoving.set(targetSection.id, true);

        try {
            const currentSectionOriginal = editor.findSectionById(
                currentSectionData.id,
            );
            const targetSectionOriginal = editor.findSectionById(
                targetSection.id,
            );

            if (!currentSectionOriginal || !targetSectionOriginal) return false;

            // Guarda orderings originais ANTES de fazer qualquer mudança
            const originalOrderings = new Map<string, number>();
            const gondola = editor.currentGondola.value;

            gondola?.sections?.forEach((s: Section) => {
                if (!s.deleted_at) {
                    originalOrderings.set(s.id, s.ordering || 0);
                }
            });

            // Calcula os NOVOS orderings SEM modificar ainda
            const tempOrdering = currentSectionOriginal.ordering;
            const newCurrentOrdering = targetSectionOriginal.ordering;
            const newTargetOrdering = tempOrdering;

            // Simula a troca para calcular novos orderings
            const tempSections = [...(gondola?.sections || [])]
                .filter((s: Section) => !s.deleted_at)
                .map((s: Section) => {
                    if (s.id === currentSectionOriginal.id) {
                        return { ...s, ordering: newCurrentOrdering };
                    }
                    if (s.id === targetSectionOriginal.id) {
                        return { ...s, ordering: newTargetOrdering };
                    }
                    return s;
                })
                .sort((a: Section, b: Section) => (a.ordering || 0) - (b.ordering || 0));

            // Calcula quais seções mudaram e seus novos orderings
            const changedSections: string[] = [];
            const newOrderings: Record<string, number> = {};

            tempSections.forEach((section: Section, index: number) => {
                const newOrdering = index + 1;
                const originalOrdering = originalOrderings.get(section.id) || 0;

                if (originalOrdering !== newOrdering) {
                    changedSections.push(section.id);
                    newOrderings[section.id] = newOrdering;
                }
            });

            // Agora SIM, aplica e registra TODAS as mudanças em um único snapshot
            if (changedSections.length > 0) {
                editor.swapSectionsOrdering(changedSections, newOrderings);
            }

            // Mantém seleção
            const updatedSection = editor.findSectionById(
                currentSectionData.id,
            );
            if (updatedSection) {
                selection.selectItem(
                    'section',
                    currentSectionData.id,
                    updatedSection,
                );
            }

            return true;
        } finally {
            setTimeout(() => {
                sectionsMoving.delete(currentSection.id);
                sectionsMoving.delete(targetSection.id);
            }, 200);
        }
    }

    const moveLeft = () => moveBetweenPositions(-1);
    const moveRight = () => moveBetweenPositions(1);

    /**
     * Verifica se deve mostrar modal de confirmação baseado no localStorage
     */
    function shouldShowDeleteConfirmLocal(
        itemType: string = 'section',
    ): boolean {
        return shouldShowDeleteConfirm(itemType);
    }

    return {
        // Computed states
        canInvertShelves,
        canMoveLeft,
        canMoveRight,

        // Actions
        invertShelves,
        moveLeft,
        moveRight,
        shouldShowDeleteConfirm: shouldShowDeleteConfirmLocal,
    };
}

/**
 * Exporta função helper para uso direto sem instanciar o composable
 * Re-exporta a função do utilitário para manter compatibilidade
 */
export { shouldShowDeleteConfirm };
