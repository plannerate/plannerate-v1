// ============================================================================
// HELPERS DE REATIVIDADE - Garantem que Vue detecta mudanças
// ============================================================================

import { currentGondola } from './useGondolaState';

/**
 * Reordena as prateleiras de uma seção otimisticamente com base na shelf_position
 * Atualiza o campo ordering sequencialmente (1, 2, 3, ...)
 */
export function reorderShelvesByPosition(section: any): void {
    if (!section?.shelves || section.shelves.length === 0) {
        return;
    }

    // Filtra prateleiras não deletadas e ordena por shelf_position
    const sortedShelves = [...section.shelves]
        .filter((s: any) => !s.deleted_at)
        .sort(
            (a: any, b: any) =>
                (a.shelf_position || 0) - (b.shelf_position || 0),
        );

    // Atualiza ordering sequencialmente
    let ordering = 1;
    let hasChanges = false;

    for (const shelf of sortedShelves) {
        if (shelf.ordering !== ordering) {
            shelf.ordering = ordering;
            hasChanges = true;
        }

        ordering++;
    }

    // Se houver mudanças, força reatividade
    if (hasChanges) {
        section.shelves = [...section.shelves];

        if (currentGondola.value?.sections) {
            currentGondola.value.sections = [...currentGondola.value.sections];
        }
    }
}

/**
 * Atualiza shelf de forma reativa (garante que Vue detecta mudanças)
 */
export function updateShelfReactive(
    section: any,
    shelfIndex: number,
    updates: Partial<any>,
) {
    const shelf = section.shelves[shelfIndex];
    const positionChanged =
        updates.shelf_position !== undefined &&
        updates.shelf_position !== shelf.shelf_position;
    const wasDeleted = !shelf.deleted_at && updates.deleted_at !== undefined;
    const wasRestored = shelf.deleted_at && updates.deleted_at === null;

    section.shelves[shelfIndex] = { ...shelf, ...updates };
    // Força reatividade substituindo arrays
    section.shelves = [...section.shelves];

    if (currentGondola.value?.sections) {
        currentGondola.value.sections = [...currentGondola.value.sections];
    }

    // Reordena otimisticamente se a position mudou ou se foi deletada/restaurada
    if (positionChanged || wasDeleted || wasRestored) {
        reorderShelvesByPosition(section);
    }
}

/**
 * Atualiza section de forma reativa
 */
export function updateSectionReactive(
    sectionIndex: number,
    updates: Partial<any>,
) {
    const sections = currentGondola.value?.sections;

    if (!sections) {
return;
}

    const orderingChanged =
        updates.ordering !== undefined &&
        updates.ordering !== sections[sectionIndex].ordering;

    // Atualiza a seção
    sections[sectionIndex] = { ...sections[sectionIndex], ...updates };

    // Força reatividade substituindo o array (cria nova referência)
    if (currentGondola.value) {
        // Cria novo array para forçar reatividade
        const newSections = [...sections];
        currentGondola.value.sections = newSections;

        // Se o ordering mudou, força recriação completa do objeto gondola
        // Isso garante que o computed sectionsOrdered seja recalculado
        if (orderingChanged) {
            // Recria o objeto gondola inteiro para forçar reatividade do computed
            currentGondola.value = {
                ...currentGondola.value,
                sections: newSections,
            };
        }
    }
}

/**
 * Atualiza segment de forma reativa (garante que Vue detecta mudanças)
 */
export function updateSegmentReactive(
    section: any,
    shelfIndex: number,
    segmentIndex: number,
    updates: { layer?: Partial<any>; product?: Partial<any> },
) {
    const shelf = section.shelves[shelfIndex];
    const segment = shelf.segments[segmentIndex];

    // Atualiza layer se fornecido
    if (updates.layer && segment.layer) {
        segment.layer = { ...segment.layer, ...updates.layer };
    }

    // Atualiza produto se fornecido
    if (updates.product && segment.layer?.product) {
        segment.layer.product = {
            ...segment.layer.product,
            ...updates.product,
        };
    }

    // Força reatividade substituindo arrays
    shelf.segments[segmentIndex] = { ...segment };
    shelf.segments = [...shelf.segments];
    section.shelves[shelfIndex] = { ...shelf };
    section.shelves = [...section.shelves];

    if (currentGondola.value?.sections) {
        currentGondola.value.sections = [...currentGondola.value.sections];
    }
}
