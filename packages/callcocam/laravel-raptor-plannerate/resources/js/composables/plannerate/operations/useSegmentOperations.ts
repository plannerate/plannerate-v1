// ============================================================================
// OPERAÇÕES COM SEGMENTOS - Move, Copy, Delete
// ============================================================================

import { ulid } from 'ulid';
import { toast } from 'vue-sonner';
import type { Segment } from '@/types/planogram';
import { validateShelfWidth } from '@plannerate/libs/validation';
import { currentGondola } from '../core/useGondolaState';
import { findSegmentById } from '../core/useLookupHelpers';

/**
 * Reindexa o `ordering` dos segmentos ATIVOS de uma shelf conforme a ordem
 * atual do array (1-based), registrando `segment_reorder` apenas para os que
 * efetivamente mudaram.
 *
 * `ordering` é a fonte única de verdade da ordem: é o que o backend persiste
 * e usa no reload (Shelf::segments() ordena por `ordering`). Antes, mover
 * segmento entre shelves atualizava só `position` (campo que o reload ignora):
 * a ordem parecia certa em memória, mas podia mudar após salvar + recarregar,
 * e um swap posterior (que re-sorta por `ordering`) "pulava" o item movido.
 *
 * ATENÇÃO à ordem das chamadas: `pendingChanges` é um Map por entidade cujo
 * merge preserva o `type` da PRIMEIRA mudança registrada. Registre
 * `segment_transfer`/`segment_copy` ANTES de reindexar — e garanta que o
 * segmento movido já esteja com o `ordering` final para o reindex não emitir
 * um `segment_reorder` que engoliria o transfer.
 */
/**
 * Converte um índice entre segmentos ATIVOS (o que a medição de DOM produz —
 * segmentos deletados não são renderizados) no índice correspondente no array
 * BRUTO `shelf.segments` (que pode conter deletados intercalados). Sem isso,
 * splice por índice ativo cairia no lugar errado quando há soft-deletes.
 */
export function activeToRawInsertIndex(
    segments: any[],
    activeIndex: number,
): number {
    let seen = 0;

    for (let i = 0; i < segments.length; i++) {
        if (segments[i].deleted_at) {
            continue;
        }

        if (seen === activeIndex) {
            return i;
        }

        seen++;
    }

    return segments.length;
}

export function reindexShelfOrdering(
    shelf: any,
    recordChange: (change: any) => void,
): void {
    const activeSegments = (shelf.segments || []).filter(
        (s: any) => !s.deleted_at,
    );

    activeSegments.forEach((seg: any, idx: number) => {
        const newOrdering = idx + 1;

        if (seg.ordering === newOrdering) {
            return;
        }

        seg.ordering = newOrdering;

        recordChange({
            type: 'segment_reorder',
            entityType: 'segment',
            entityId: seg.id,
            data: {
                shelf_id: shelf.id,
                ordering: newOrdering,
            },
        });
    });
}

/**
 * Move um segmento de uma prateleira para outra
 */
export function moveSegmentToShelf(
    segmentId: string,
    targetShelfId: string,
    recordChange: (change: any) => void,
    productDoesNotFitMessage = 'Product does not fit on destination shelf.',
    targetIndex?: number,
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

        if (targetShelf) {
break;
}
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
            toast.error(productDoesNotFitMessage);
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
        }
    }

    // Adiciona à shelf de destino
    if (!targetShelf.segments) {
        targetShelf.segments = [];
    }

    segment.shelf_id = targetShelfId;

    // Insere na posição apontada pelo preview (targetIndex é entre segmentos
    // ativos) ou no fim.
    if (targetIndex !== undefined && targetIndex >= 0) {
        const rawIdx = activeToRawInsertIndex(targetShelf.segments, targetIndex);
        targetShelf.segments.splice(rawIdx, 0, segment);
    } else {
        targetShelf.segments.push(segment);
    }

    // Ordering final do segmento movido = sua posição ativa (1-based) no array.
    // Setar ANTES do reindex para ele não emitir reorder deste segmento
    // (o transfer registrado abaixo já carrega o ordering — ver helper).
    segment.ordering =
        targetShelf.segments
            .filter((s: any) => !s.deleted_at)
            .findIndex((s: any) => s.id === segment.id) + 1;

    // Registra o transfer PRIMEIRO (o merge do Map preserva o type inicial)
    recordChange({
        type: 'segment_transfer',
        entityType: 'segment',
        entityId: segmentId,
        data: {
            from_shelf_id: sourceShelfId,
            to_shelf_id: targetShelfId,
            position: segment.ordering,
        },
    });

    // Compacta o ordering das duas shelves (persistido via segment_reorder)
    reindexShelfOrdering(sourceShelf, recordChange);
    reindexShelfOrdering(targetShelf, recordChange);

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

    return true;
}

/**
 * Copia um segmento para outra prateleira (deep copy com novos IDs)
 */
export function copySegmentToShelf(
    segmentId: string,
    targetShelfId: string,
    recordChange: (change: any) => void,
    productDoesNotFitMessage = 'Product does not fit on destination shelf.',
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

        if (targetShelf) {
break;
}
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
            toast.error(productDoesNotFitMessage);
            console.warn('❌ Segmento não cabe na shelf de destino:', {
                productName: segment.layer.product?.name,
                totalWidth: validation.totalWidth,
                sectionWidth: validation.sectionWidth,
            });

            return false;
        }
    }

    // Cria cópia profunda do segmento com novos IDs.
    // `ordering` explícito (fim da lista ativa): o spread copiaria o ordering
    // da origem, colidindo com um segmento existente no destino.
    const newSegmentId = ulid();
    const newOrdering =
        (targetShelf.segments?.filter((s: any) => !s.deleted_at).length || 0) + 1;
    const newSegment: Segment = {
        ...segment,
        id: newSegmentId,
        shelf_id: targetShelfId,
        ordering: newOrdering,
        position: newOrdering,
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

    // Registra mudança (chave `position` é o contrato de wire — o backend
    // mapeia para a coluna `ordering`)
    recordChange({
        type: 'segment_copy',
        entityType: 'segment',
        entityId: newSegmentId,
        data: {
            source_segment_id: segmentId,
            shelf_id: targetShelfId,
            position: newSegment.ordering,
            layer: newSegment.layer,
        },
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
