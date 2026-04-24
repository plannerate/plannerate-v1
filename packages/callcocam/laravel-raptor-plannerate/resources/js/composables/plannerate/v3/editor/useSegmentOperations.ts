// ============================================================================
// OPERAÇÕES COM SEGMENTOS - Move, Copy, Delete
// ============================================================================

import { validateShelfWidth } from '@/lib/validation';
import type { Segment } from '@/types/planogram';
import { ulid } from 'ulid';
import { toast } from 'vue-sonner';
import { currentGondola } from './useGondolaState';
import { findSegmentById } from './useLookupHelpers';

/**
 * Move um segmento de uma prateleira para outra
 */
export function moveSegmentToShelf(
    segmentId: string,
    targetShelfId: string,
    recordChange: (change: any) => void,
): boolean {
    if (!currentGondola.value) {
        console.warn('⚠️ Nenhuma gôndola carregada');
        return false;
    }

    // Encontra o segmento e sua shelf de origem
    const sourceSegment = findSegmentById(segmentId);
    if (!sourceSegment) {
        console.warn('⚠️ Segmento não encontrado:', segmentId);
        return false;
    }

    const {
        segment,
        shelf: sourceShelf,
        section: sourceSection,
    } = sourceSegment;
    const sourceShelfId = segment.shelf_id;

    if (sourceShelfId === targetShelfId) {
        console.warn('⚠️ Segmento já está na shelf de destino');
        return false;
    }

    // Encontra a shelf de destino
    let targetShelf: any = null;
    let targetSection: any = null;

    for (const section of currentGondola.value.sections || []) {
        for (const shelf of section.shelves || []) {
            if (shelf.id === targetShelfId) {
                targetShelf = shelf;
                targetSection = section;
                break;
            }
        }
        if (targetShelf) break;
    }

    if (!targetShelf) {
        console.warn('⚠️ Shelf de destino não encontrada');
        return false;
    }

    // Valida se o segmento cabe na shelf de destino
    if (segment.layer) {
        const validation = validateShelfWidth(
            targetShelf,
            targetSection.width,
            null, // Não estamos alterando produto existente
            segment.layer.quantity || 1,
            segment.layer, // Layer sendo movida
        );

        if (!validation.isValid) {
            toast.error('O produto não cabe na prateleira de destino.');
            console.warn('❌ Segmento não cabe na shelf de destino:', {
                productName: segment.layer.product?.name,
                totalWidth: validation.totalWidth,
                sectionWidth: validation.sectionWidth,
            });
            return false;
        }
    }

    // Remove da shelf de origem
    if (sourceShelf.segments) {
        const index = sourceShelf.segments.findIndex(
            (s: any) => s.id === segmentId,
        );
        if (index > -1) {
            sourceShelf.segments.splice(index, 1);
            // Reposiciona segmentos restantes
            sourceShelf.segments.forEach((seg: any, idx: number) => {
                seg.position = idx;
            });
            sourceShelf.segments = [...sourceShelf.segments];
        }
    }

    // Adiciona à shelf de destino
    if (!targetShelf.segments) {
        targetShelf.segments = [];
    }
    segment.shelf_id = targetShelfId;
    segment.position = targetShelf.segments.length;
    targetShelf.segments.push(segment);
    targetShelf.segments = [...targetShelf.segments];

    // Força reatividade
    sourceShelf.segments = [...(sourceShelf.segments || [])];

    if (sourceSection.id !== targetSection.id) {
        // Atualiza ambas as seções se diferentes
        const sourceSectionIndex =
            currentGondola.value.sections?.findIndex(
                (s: any) => s.id === sourceSection.id,
            ) ?? -1;
        const targetSectionIndex =
            currentGondola.value.sections?.findIndex(
                (s: any) => s.id === targetSection.id,
            ) ?? -1;

        if (sourceSectionIndex !== -1 && currentGondola.value.sections) {
            currentGondola.value.sections[sourceSectionIndex] = {
                ...sourceSection,
            };
        }
        if (targetSectionIndex !== -1 && currentGondola.value.sections) {
            currentGondola.value.sections[targetSectionIndex] = {
                ...targetSection,
            };
        }
    }

    if (currentGondola.value.sections) {
        currentGondola.value.sections = [...currentGondola.value.sections];
    }

    // Registra mudança
    recordChange({
        type: 'segment_transfer',
        entityType: 'segment',
        entityId: segmentId,
        data: {
            from_shelf_id: sourceShelfId,
            to_shelf_id: targetShelfId,
            position: segment.position,
        },
    });

    return true;
}

/**
 * Copia um segmento para outra prateleira (deep copy com novos IDs)
 */
export function copySegmentToShelf(
    segmentId: string,
    targetShelfId: string,
    recordChange: (change: any) => void,
): boolean {
    if (!currentGondola.value) {
        console.warn('⚠️ Nenhuma gôndola carregada');
        return false;
    }

    // Encontra o segmento de origem
    const sourceSegment = findSegmentById(segmentId);
    if (!sourceSegment) {
        console.warn('⚠️ Segmento não encontrado:', segmentId);
        return false;
    }

    // Encontra a shelf de destino
    let targetShelf: any = null;
    let targetSection: any = null;

    for (const section of currentGondola.value.sections || []) {
        for (const shelf of section.shelves || []) {
            if (shelf.id === targetShelfId) {
                targetShelf = shelf;
                targetSection = section;
                break;
            }
        }
        if (targetShelf) break;
    }

    if (!targetShelf) {
        console.warn('⚠️ Shelf de destino não encontrada');
        return false;
    }

    const { segment } = sourceSegment;

    // Valida se o segmento cabe na shelf de destino
    if (segment.layer) {
        const validation = validateShelfWidth(
            targetShelf,
            targetSection.width,
            null, // Não estamos alterando produto existente
            segment.layer.quantity || 1,
            segment.layer, // Layer sendo copiada
        );

        if (!validation.isValid) {
            toast.error('O produto não cabe na prateleira de destino.');
            console.warn('❌ Segmento não cabe na shelf de destino:', {
                productName: segment.layer.product?.name,
                totalWidth: validation.totalWidth,
                sectionWidth: validation.sectionWidth,
            });
            return false;
        }
    }

    // Cria cópia profunda do segmento com novos IDs
    const newSegmentId = ulid();
    const newSegment: Segment = {
        ...segment,
        id: newSegmentId,
        shelf_id: targetShelfId,
        position: targetShelf.segments?.length || 0,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
    };

    // Copia a layer se existir
    if (segment.layer) {
        const newLayerId = ulid();
        newSegment.layer = {
            ...segment.layer,
            id: newLayerId,
            segment_id: newSegmentId,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
        };

        // Copia produtos se existirem
        if (segment.layer?.product && newSegment.layer) {
            newSegment.layer.product = {
                ...segment.layer.product,
                // Mantém o mesmo product_id (é uma referência, não cópia)
            };
        }
    }

    // Adiciona à shelf de destino
    if (!targetShelf.segments) {
        targetShelf.segments = [];
    }
    targetShelf.segments.push(newSegment);
    targetShelf.segments = [...targetShelf.segments];

    // Força reatividade
    const targetSectionIndex =
        currentGondola.value.sections?.findIndex(
            (s: any) => s.id === targetSection.id,
        ) ?? -1;

    if (targetSectionIndex !== -1 && currentGondola.value.sections) {
        currentGondola.value.sections[targetSectionIndex] = {
            ...targetSection,
        };
        currentGondola.value.sections = [...currentGondola.value.sections];
    }

    // Registra mudança
    recordChange({
        type: 'segment_copy',
        entityType: 'segment',
        entityId: newSegmentId,
        data: {
            source_segment_id: segmentId,
            shelf_id: targetShelfId,
            position: newSegment.position,
            layer: newSegment.layer,
        },
    });

    return true;
}
/**
 * Reordena um segment dentro da mesma shelf
 * @param draggedSegmentId - ID do segment sendo arrastado
 * @param targetSegmentId - ID do segment de referência
 * @param position - 'before' ou 'after' do segment de referência
 */
export function reorderSegmentInShelf(
    draggedSegmentId: string,
    targetSegmentId: string,
    position: 'before' | 'after',
    recordChange: (change: any) => void,
): boolean {
    if (!currentGondola.value) {
        console.warn('⚠️ Nenhuma gôndola carregada');
        return false;
    }

    // Encontra o segment arrastado
    const draggedResult = findSegmentById(draggedSegmentId);
    if (!draggedResult) {
        console.warn('⚠️ Segment arrastado não encontrado:', draggedSegmentId);
        return false;
    }

    // Encontra o segment de destino
    const targetResult = findSegmentById(targetSegmentId);
    if (!targetResult) {
        console.warn('⚠️ Segment de destino não encontrado:', targetSegmentId);
        return false;
    }

    const { shelf: draggedShelf } = draggedResult;
    const { shelf: targetShelf } = targetResult;

    // Verifica se estão na mesma shelf
    if (draggedShelf.id !== targetShelf.id) {
        console.warn('⚠️ Segments não estão na mesma shelf');
        return false;
    }

    // Remove o segment arrastado da lista
    const segments = draggedShelf.segments || [];
    const draggedIndex = segments.findIndex(
        (s: any) => s.id === draggedSegmentId,
    );
    const targetIndex = segments.findIndex(
        (s: any) => s.id === targetSegmentId,
    );

    if (draggedIndex === -1 || targetIndex === -1) {
        console.warn('⚠️ Não foi possível localizar segments na shelf');
        return false;
    }

    // Remove o segment arrastado
    const [removed] = segments.splice(draggedIndex, 1);

    // Calcula nova posição
    let newIndex = segments.findIndex((s: any) => s.id === targetSegmentId);
    if (position === 'after') {
        newIndex += 1;
    }

    // Insere na nova posição
    segments.splice(newIndex, 0, removed);

    // Atualiza as ordenações de todos os segments
    segments.forEach((seg: any, idx: number) => {
        seg.ordering = idx;
    });

    // Força reatividade
    draggedShelf.segments = [...segments];

    // Registra mudanças para cada segment que teve a ordenação alterada
    segments.forEach((seg: any) => {
        recordChange({
            type: 'segment_reorder',
            entityType: 'segment',
            entityId: seg.id,
            data: {
                shelf_id: draggedShelf.id,
                ordering: seg.ordering,
            },
        });
    });

    return true;
}
/**
 * Troca posições entre dois segments na mesma shelf
 * @param segment1Id - ID do primeiro segment
 * @param segment2Id - ID do segundo segment
 */
export function swapSegmentPositions(
    segment1Id: string,
    segment2Id: string,
    recordChange: (change: any) => void,
): boolean {
    if (!currentGondola.value) {
        console.warn('⚠️ Nenhuma gôndola carregada');
        return false;
    }

    // Encontra ambos os segments
    const segment1Result = findSegmentById(segment1Id);
    const segment2Result = findSegmentById(segment2Id);

    if (!segment1Result || !segment2Result) {
        console.warn('⚠️ Um ou ambos segments não encontrados');
        return false;
    }

    const { segment: seg1, shelf: shelf1 } = segment1Result;
    const { segment: seg2, shelf: shelf2 } = segment2Result;

    // Verifica se estão na mesma shelf
    if (shelf1.id !== shelf2.id) {
        console.warn('⚠️ Segments não estão na mesma shelf');
        return false;
    }

    // Troca as posições usando ordering
    const tempOrdering = seg1.ordering;
    seg1.ordering = seg2.ordering;
    seg2.ordering = tempOrdering;

    // Reordena o array de segments pela nova ordering
    const segments = shelf1.segments || [];
    segments.sort((a: any, b: any) => (a.ordering ?? 0) - (b.ordering ?? 0));

    // Força reatividade
    shelf1.segments = [...segments];

    // Registra mudanças para ambos os segments
    recordChange({
        type: 'segment_reorder',
        entityType: 'segment',
        entityId: seg1.id,
        data: {
            shelf_id: shelf1.id,
            ordering: seg1.ordering,
        },
    });

    recordChange({
        type: 'segment_reorder',
        entityType: 'segment',
        entityId: seg2.id,
        data: {
            shelf_id: shelf2.id,
            ordering: seg2.ordering,
        },
    });
 
    return true;
}
