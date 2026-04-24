import { Section } from '@/types/planogram';
import { ulid } from 'ulid';
import { currentGondola } from './useGondolaState';

/**
 * Operações relacionadas a Sections
 */
export function useSectionOperations() {
    /**
     * Adiciona uma nova seção à gôndola atual
     * @param sectionData - Dados parciais da seção a ser criada
     * @param recordChange - Função para registrar mudança no histórico
     * @returns Section criada ou null
     */
    function addSection(
        sectionData: Partial<Section>,
        recordChange: (change: any) => void,
    ): Section | null {
        if (!currentGondola.value?.id) {
            console.warn('Nenhuma gôndola selecionada para adicionar seção');
            return null;
        }

        // Calcula o próximo ordering
        const sections = currentGondola.value.sections || [];
        const maxOrdering = sections.reduce((max: number, s: Section) => {
            return Math.max(max, s.ordering || 0);
        }, 0);

        // Cria a nova seção
        const newSection = {
            ...sectionData,
            id: sectionData.id || ulid(),
            gondola_id: currentGondola.value.id,
            ordering: sectionData.ordering ?? maxOrdering + 1,
            _is_new: true,
        } as Section;

        // Adiciona à gôndola
        if (!currentGondola.value.sections) {
            currentGondola.value.sections = [];
        }
        currentGondola.value.sections.push(newSection);

        // Força reatividade
        currentGondola.value.sections = [...currentGondola.value.sections];

        // Registra mudança
        recordChange({
            type: 'section_update',
            entityType: 'section',
            entityId: newSection.id,
            data: newSection,
        });

        return newSection;
    }

    /**
     * Atualiza uma seção existente
     * @param sectionId - ID da seção a atualizar
     * @param updates - Propriedades a atualizar
     * @param updateSectionReactive - Função helper para atualizar reativamente
     * @param recordChange - Função para registrar mudança
     * @returns Section atualizada ou null
     */
    function updateSection(
        sectionId: string,
        updates: Partial<any>,
        updateSectionReactive: (
            sectionIndex: number,
            updates: Partial<any>,
        ) => void,
        recordChange: (change: any) => void,
    ): Section | null {
        const sections = currentGondola.value?.sections;
        if (!sections) return null;

        const sectionIndex = sections.findIndex((s: any) => s.id === sectionId);
        if (sectionIndex === -1) return null;

        // Atualiza reativamente
        updateSectionReactive(sectionIndex, updates);

        // Registra mudança
        recordChange({
            type: 'section_update',
            entityType: 'section',
            entityId: sectionId,
            data: updates,
        });

        return sections[sectionIndex];
    }

    /**
     * Reordena as seções da gôndola atual com base no campo ordering
     * Atualiza ordering sequencialmente (1, 2, 3, ...) apenas para seções não deletadas
     */
    function reorderSectionsByOrdering(): void {
        const gondola = currentGondola.value;
        if (!gondola?.sections || gondola.sections.length === 0) return;

        const sortedSections = [...gondola.sections]
            .filter((s: any) => !s.deleted_at)
            .sort((a: any, b: any) => (a.ordering || 0) - (b.ordering || 0));

        let ordering = 1;
        let hasChanges = false;

        for (const section of sortedSections) {
            if (section.ordering !== ordering) {
                section.ordering = ordering;
                hasChanges = true;
            }
            ordering++;
        }

        if (hasChanges) {
            gondola.sections = [...gondola.sections];
        }
    }

    return {
        addSection,
        updateSection,
        reorderSectionsByOrdering,
    };
}
