// ============================================================================
// LOOKUP HELPERS - Busca otimizada na estrutura da gôndola
// ============================================================================

import { currentGondola } from './useGondolaState';

/**
 * Encontra section por ID
 */
export function findSectionById(sectionId: string) {
    const sections = currentGondola.value?.sections;

    if (!sections) {
return null;
}

    return sections.find((s) => s.id === sectionId) || null;
}

/**
 * Encontra shelf por ID (retorna shelf + section pai)
 */
export function findShelfById(
    shelfId: string,
): { shelf: any; section: any } | null {
    const sections = currentGondola.value?.sections;

    if (!sections) {
return null;
}

    for (const section of sections) {
        if (!section.shelves) {
continue;
}

        const shelf = section.shelves.find((s: any) => s.id === shelfId);

        if (shelf) {
            return { shelf, section };
        }
    }

    return null;
}

/**
 * Encontra segment por ID (retorna segment + shelf + section + índices)
 */
export function findSegmentById(segmentId: string): {
    segment: any;
    shelf: any;
    section: any;
    shelfIndex: number;
    segmentIndex: number;
} | null {
    const sections = currentGondola.value?.sections;

    if (!sections) {
return null;
}

    for (const section of sections) {
        if (!section.shelves) {
continue;
}

        for (const shelf of section.shelves) {
            if (!shelf.segments) {
continue;
}

            const segmentIndex = shelf.segments.findIndex(
                (s: any) => s.id === segmentId,
            );

            if (segmentIndex !== -1) {
                const shelfIndex = section.shelves.findIndex(
                    (s: any) => s.id === shelf.id,
                );

                return {
                    segment: shelf.segments[segmentIndex],
                    shelf,
                    section,
                    shelfIndex,
                    segmentIndex,
                };
            }
        }
    }

    return null;
}

/**
 * Encontra segment que contém a layer por layerId
 */
export function findSegmentByLayerId(layerId: string) {
    const sections = currentGondola.value?.sections;

    if (!sections) {
return null;
}

    for (const section of sections) {
        if (!section.shelves) {
continue;
}

        for (const shelf of section.shelves) {
            if (!shelf.segments) {
continue;
}

            const segmentIndex = shelf.segments.findIndex(
                (s: any) => s.layer?.id === layerId,
            );

            if (segmentIndex !== -1) {
                const shelfIndex = section.shelves.findIndex(
                    (s: any) => s.id === shelf.id,
                );

                return {
                    segment: shelf.segments[segmentIndex],
                    shelf,
                    section,
                    shelfIndex,
                    segmentIndex,
                };
            }
        }
    }

    return null;
}
