// ============================================================================
// GERENCIADOR DE SNAPSHOTS — commit otimista + apply (undo/redo)
// ============================================================================
// Centraliza toda a lógica de captura de estado before/after, commit otimista
// e aplicação de snapshots para undo/redo do editor de planogramas.
// ============================================================================

import {
    currentGondola,
    rejectedProducts,
} from '../core/useGondolaState';
import {
    findSectionById,
    findSegmentById,
    findSegmentByLayerId,
    findShelfById,
} from '../core/useLookupHelpers';
import type { usePlanogramChanges } from '../core/usePlanogramChanges';
import type { usePlanogramHistory } from '../core/usePlanogramHistory';
import {
    updateSectionReactive,
    updateSegmentReactive,
} from '../core/useReactivityHelpers';
import type {
    SegmentCopyAfterState,
    SegmentTransferAfterState,
    SnapshotState,
} from './useSnapshotTypes';

export function useSnapshotManager(
    history: ReturnType<typeof usePlanogramHistory>,
    changes: ReturnType<typeof usePlanogramChanges>,
) {
    type OptimisticSnapshot = Parameters<typeof history.recordAction>[0];
    type OptimisticChange = Parameters<typeof changes.recordChange>[0];

    // ========================================================================
    // CAPTURA DE ESTADO
    // ========================================================================

    /**
     * Captura o estado ANTES da mudança para histórico
     */
    function captureBeforeState(snapshot: OptimisticSnapshot): SnapshotState {
        if (!currentGondola.value) {
            return null;
        }

        try {
            switch (snapshot.type) {
                case 'shelf_position':
                case 'shelf_update':
                case 'shelf_transfer':
                    if (snapshot.shelfId) {
                        const shelfData = findShelfById(snapshot.shelfId);

                        if (!shelfData) {
                            return null;
                        }

                        return history.cloneState({
                            shelf_position: shelfData.shelf.shelf_position,
                            section_id: shelfData.shelf.section_id,
                            shelf_height: shelfData.shelf.shelf_height,
                            shelf_depth: shelfData.shelf.shelf_depth,
                        });
                    }

                    break;

                case 'segment_position':
                case 'segment_update':
                case 'segment_transfer':
                case 'segment_copy':
                    if (snapshot.segmentId) {
                        const found = findSegmentById(snapshot.segmentId);

                        return found ? history.cloneState(found.segment) : null;
                    }

                    break;

                case 'section_update':
                    if (snapshot.sectionId) {
                        const section = findSectionById(snapshot.sectionId);

                        return section ? history.cloneState(section) : null;
                    }

                    break;

                case 'sections_reorder':
                    if (snapshot.sectionIds && Array.isArray(snapshot.sectionIds)) {
                        const sectionsData: Record<string, number> = {};
                        snapshot.sectionIds.forEach((sectionId: string) => {
                            const section = findSectionById(sectionId);

                            if (section) {
                                sectionsData[sectionId] = section.ordering || 0;
                            }
                        });

                        return sectionsData;
                    }

                    break;

                case 'layer_update':
                    if (snapshot.layerId) {
                        const found = findSegmentByLayerId(snapshot.layerId);

                        return found?.segment.layer
                            ? history.cloneState(found.segment.layer)
                            : null;
                    }

                    break;

                case 'gondola_update':
                case 'gondola_scale':
                case 'gondola_alignment':
                case 'gondola_flow':
                    return history.cloneState({
                        scale_factor: currentGondola.value.scale_factor,
                        alignment: currentGondola.value.alignment,
                        flow: currentGondola.value.flow,
                    });

                default:
                    return null;
            }
        } catch (error) {
            console.error('❌ Erro ao capturar estado before:', error);

            return null;
        }

        return null;
    }

    /**
     * Captura o estado DEPOIS da mudança para histórico
     */
    function captureAfterState(snapshot: OptimisticSnapshot, beforeState: SnapshotState): SnapshotState {
        if (!currentGondola.value || !beforeState) {
            return null;
        }

        try {
            switch (snapshot.type) {
                case 'shelf_position':
                case 'shelf_update':
                case 'shelf_transfer':
                    if (snapshot.shelfId) {
                        const shelfData = findShelfById(snapshot.shelfId);

                        if (!shelfData) {
                            return null;
                        }

                        return history.cloneState({
                            shelf_position: shelfData.shelf.shelf_position,
                            section_id: shelfData.shelf.section_id,
                            shelf_height: shelfData.shelf.shelf_height,
                            shelf_depth: shelfData.shelf.shelf_depth,
                        });
                    }

                    break;

                case 'segment_position':
                case 'segment_update':
                    if (snapshot.segmentId) {
                        const found = findSegmentById(snapshot.segmentId);

                        return found ? history.cloneState(found.segment) : null;
                    }

                    break;

                case 'segment_transfer': {
                    if (
                        beforeState !== null &&
                        typeof beforeState === 'object' &&
                        'sourceShelfId' in beforeState
                    ) {
                        const state = beforeState as SegmentTransferAfterState;
                        const sourceShelf = findShelfById(state.sourceShelfId);
                        const targetShelf = findShelfById(state.targetShelfId);

                        return {
                            sourceShelfId: state.sourceShelfId,
                            targetShelfId: state.targetShelfId,
                            segmentId: state.segmentId,
                            sourceShelfSegments: sourceShelf ? history.cloneState(sourceShelf.shelf.segments) : [],
                            targetShelfSegments: targetShelf ? history.cloneState(targetShelf.shelf.segments) : [],
                        };
                    }

                    break;
                }

                case 'segment_copy': {
                    if (
                        beforeState !== null &&
                        typeof beforeState === 'object' &&
                        'targetShelfId' in beforeState
                    ) {
                        const state = beforeState as SegmentCopyAfterState;
                        const targetShelf = findShelfById(state.targetShelfId);

                        return {
                            targetShelfId: state.targetShelfId,
                            targetShelfSegments: targetShelf ? history.cloneState(targetShelf.shelf.segments) : [],
                        };
                    }

                    break;
                }

                case 'section_update':
                    if (snapshot.sectionId) {
                        const section = findSectionById(snapshot.sectionId);

                        return section ? history.cloneState(section) : null;
                    }

                    break;

                case 'sections_reorder':
                    if (snapshot.sectionIds && Array.isArray(snapshot.sectionIds)) {
                        const sectionsData: Record<string, number> = {};
                        snapshot.sectionIds.forEach((sectionId: string) => {
                            const section = findSectionById(sectionId);

                            if (section) {
                                sectionsData[sectionId] = section.ordering || 0;
                            }
                        });

                        return sectionsData;
                    }

                    break;

                case 'layer_update':
                    if (snapshot.layerId) {
                        const found = findSegmentByLayerId(snapshot.layerId);

                        return found?.segment.layer
                            ? history.cloneState(found.segment.layer)
                            : null;
                    }

                    break;

                case 'gondola_update':
                case 'gondola_scale':
                case 'gondola_alignment':
                case 'gondola_flow':
                    return history.cloneState({
                        scale_factor: currentGondola.value.scale_factor,
                        alignment: currentGondola.value.alignment,
                        flow: currentGondola.value.flow,
                    });

                default:
                    return null;
            }
        } catch (error) {
            console.error('❌ Erro ao capturar estado after:', error);

            return null;
        }

        return null;
    }

    // ========================================================================
    // COMMIT OTIMISTA
    // ========================================================================

    /**
     * Commit otimista: aplica mudança + registra histórico + agenda save
     */
    function commitOptimistic<T>({
        apply,
        historySnapshot,
        change,
        autoSave = true,
        onSaved,
    }: {
        apply: () => T;
        historySnapshot?: OptimisticSnapshot;
        change?: OptimisticChange;
        autoSave?: boolean;
        onSaved?: () => void | Promise<void>;
    }): T | null {
        try {
            let beforeState: SnapshotState = null;

            if (historySnapshot) {
                if (historySnapshot.beforeState) {
                    beforeState = historySnapshot.beforeState as SnapshotState;
                } else {
                    beforeState = captureBeforeState(historySnapshot);
                }
            }

            const result = apply();

            if (historySnapshot && beforeState) {
                let afterState: SnapshotState;

                if (historySnapshot.afterState) {
                    afterState = historySnapshot.afterState;
                } else {
                    afterState = captureAfterState(historySnapshot, beforeState);
                }

                history.recordAction({
                    ...historySnapshot,
                    beforeState,
                    afterState,
                });
            }

            if (change) {
                changes.recordChange(change, { schedule: autoSave, onSaved });
            }

            return result;
        } catch (error) {
            console.error('❌ Erro ao aplicar mudança otimista:', error);

            return null;
        }
    }

    /**
     * Registra mudança para persistência (exposto para uso externo)
     */
    function recordChange(change: OptimisticChange) {
        changes.recordChange(change, { schedule: true });
    }

    // ========================================================================
    // APLICAÇÃO DE SNAPSHOTS (undo/redo)
    // ========================================================================

    /**
     * Aplica um snapshot ao estado atual da gôndola E registra para persistência
     */
    function applySnapshot(snapshot: any, shouldPersist: boolean = true) {
        if (!currentGondola.value || !snapshot) {
            console.error('❌ Não foi possível aplicar snapshot: gôndola ou snapshot inválidos');

            return false;
        }

        try {
            switch (snapshot.type) {
                case 'shelf_position':
                case 'shelf_update':
                case 'shelf_transfer':
                    applyShelfSnapshot(snapshot, shouldPersist);
                    break;

                case 'segment_position':
                case 'segment_update':
                    applySegmentSnapshot(snapshot, shouldPersist);
                    break;

                case 'segment_transfer':
                    applySegmentTransferSnapshot(snapshot, shouldPersist);
                    break;

                case 'segment_copy':
                    applySegmentCopySnapshot(snapshot, shouldPersist);
                    break;

                case 'section_update':
                    applySectionSnapshot(snapshot, shouldPersist);
                    break;

                case 'sections_reorder':
                    applySectionsReorderSnapshot(snapshot, shouldPersist);
                    break;

                case 'layer_update':
                    applyLayerSnapshot(snapshot, shouldPersist);
                    break;

                case 'product_update':
                    applyProductSnapshot(snapshot, shouldPersist);
                    break;

                case 'gondola_update':
                case 'gondola_scale':
                case 'gondola_alignment':
                case 'gondola_flow':
                    applyGondolaSnapshot(snapshot, shouldPersist);
                    break;

                default:
                    console.warn('⚠️ Tipo de snapshot não implementado:', snapshot.type);

                    return false;
            }

            return true;
        } catch (error) {
            console.error('❌ Erro ao aplicar snapshot:', error);

            return false;
        }
    }

    function applyShelfSnapshot(snapshot: any, shouldPersist: boolean) {
        const currentShelfData = findShelfById(snapshot.shelfId);

        if (!currentShelfData) {
            console.error('❌ Shelf não encontrada:', snapshot.shelfId);

            return;
        }

        const { beforeState } = snapshot;
        const currentSectionId = currentShelfData.shelf.section_id;
        const targetSectionId = beforeState.section_id;

        if (currentSectionId !== targetSectionId) {
            const currentSection = findSectionById(currentSectionId);
            const targetSection = findSectionById(targetSectionId);

            if (!currentSection || !targetSection) {
                console.error('❌ Modulos não encontradas');

                return;
            }

            const shelfIndex = currentSection.shelves?.findIndex(
                (s: any) => s.id === snapshot.shelfId,
            ) ?? -1;

            if (shelfIndex !== -1 && currentSection.shelves) {
                const shelf = currentSection.shelves[shelfIndex];
                currentSection.shelves.splice(shelfIndex, 1);
                currentSection.shelves = [...currentSection.shelves];

                Object.assign(shelf, beforeState);

                if (!targetSection.shelves) {
                    targetSection.shelves = [];
                }

                targetSection.shelves.push(shelf);
                targetSection.shelves = [...targetSection.shelves];

                if (currentGondola.value?.sections) {
                    currentGondola.value.sections = [...currentGondola.value.sections];
                }
            }
        } else {
            Object.assign(currentShelfData.shelf, beforeState);

            const section = findSectionById(currentSectionId);

            if (section && currentGondola.value?.sections) {
                section.shelves = [...(section.shelves || [])];

                const sectionIndex = currentGondola.value.sections.findIndex(s => s.id === currentSectionId);

                if (sectionIndex !== -1) {
                    updateSectionReactive(sectionIndex, {});
                }
            }
        }

        if (shouldPersist) {
            const isTransfer = currentSectionId !== targetSectionId;

            if (isTransfer) {
                recordChange({
                    type: 'shelf_transfer',
                    entityType: 'shelf',
                    entityId: snapshot.shelfId,
                    data: {
                        to_section_id: targetSectionId,
                        shelf_position: beforeState.shelf_position,
                        ordering: beforeState.ordering,
                    },
                });
            } else {
                recordChange({
                    type: 'shelf_move',
                    entityType: 'shelf',
                    entityId: snapshot.shelfId,
                    data: { shelf_position: beforeState.shelf_position },
                });
            }
        }
    }

    function applySegmentSnapshot(snapshot: any, shouldPersist: boolean) {
        const found = findSegmentById(snapshot.segmentId);

        if (!found) {
            console.error('❌ Segment não encontrado:', snapshot.segmentId);

            return;
        }

        Object.assign(found.segment, snapshot.beforeState);

        updateSegmentReactive(found.section, found.shelfIndex, found.segmentIndex, {});

        if (shouldPersist) {
            recordChange({
                type: 'segment_update',
                entityType: 'segment',
                entityId: snapshot.segmentId,
                data: snapshot.beforeState,
            });
        }
    }

    function applySectionSnapshot(snapshot: any, shouldPersist: boolean) {
        const section = findSectionById(snapshot.sectionId);

        if (!section || !currentGondola.value?.sections) {
            console.error('❌ Section não encontrada:', snapshot.sectionId);

            return;
        }

        const sectionIndex = currentGondola.value.sections.findIndex(s => s.id === snapshot.sectionId);

        if (sectionIndex !== -1) {
            updateSectionReactive(sectionIndex, snapshot.beforeState);
        }

        if (shouldPersist) {
            const allowedFields = [
                'name', 'code', 'width', 'height', 'num_shelves',
                'base_height', 'base_depth', 'base_width', 'cremalheira_width',
                'hole_height', 'hole_width', 'hole_spacing',
                'ordering', 'alignment',
            ];

            const data: Record<string, any> = {};

            for (const field of allowedFields) {
                if (snapshot.beforeState[field] !== undefined) {
                    data[field] = snapshot.beforeState[field];
                }
            }

            recordChange({
                type: 'section_update',
                entityType: 'section',
                entityId: snapshot.sectionId,
                data,
            });
        }
    }

    function applySectionsReorderSnapshot(snapshot: any, shouldPersist: boolean) {
        if (!currentGondola.value?.sections || !snapshot.sectionIds || !snapshot.beforeState) {
            console.error('❌ Dados inválidos para reordenação de seções');

            return;
        }

        const beforeOrderings = snapshot.beforeState as Record<string, number>;

        snapshot.sectionIds.forEach((sectionId: string) => {
            const sectionIndex = currentGondola.value?.sections?.findIndex(
                (s: any) => s.id === sectionId,
            ) ?? -1;

            if (sectionIndex !== -1 && beforeOrderings[sectionId] !== undefined) {
                updateSectionReactive(sectionIndex, { ordering: beforeOrderings[sectionId] });
            }
        });

        if (shouldPersist) {
            snapshot.sectionIds.forEach((sectionId: string) => {
                if (beforeOrderings[sectionId] !== undefined) {
                    recordChange({
                        type: 'section_update',
                        entityType: 'section',
                        entityId: sectionId,
                        data: { ordering: beforeOrderings[sectionId] },
                    });
                }
            });
        }
    }

    function applyLayerSnapshot(snapshot: any, shouldPersist: boolean) {
        const found = findSegmentByLayerId(snapshot.layerId);

        if (!found || !found.segment.layer) {
            console.error('❌ Layer não encontrado:', snapshot.layerId);

            return;
        }

        const { _rejectedProduct, ...layerState } = snapshot.beforeState ?? {};
        Object.assign(found.segment.layer, layerState);

        // Restaura produto à lista de rejeitados se havia sido removido
        if (_rejectedProduct) {
            rejectedProducts.value = [_rejectedProduct, ...rejectedProducts.value];
        }

        updateSegmentReactive(found.section, found.shelfIndex, found.segmentIndex, {});

        if (shouldPersist) {
            recordChange({
                type: 'layer_update',
                entityType: 'layer',
                entityId: snapshot.layerId,
                data: layerState,
            });
        }
    }

    function applyProductSnapshot(snapshot: any, shouldPersist: boolean) {
        const gondola = currentGondola.value;

        if (!gondola?.sections) {
            return;
        }

        for (const section of gondola.sections) {
            if (!section.shelves) {
                continue;
            }

            for (const shelf of section.shelves) {
                if (!shelf.segments) {
                    continue;
                }

                for (const segment of shelf.segments) {
                    if (segment.layer?.product?.id === snapshot.entityId) {
                        Object.assign(segment.layer?.product as any, snapshot.beforeState);

                        const found = findSegmentById(segment.id);

                        if (found) {
                            updateSegmentReactive(found.section, found.shelfIndex, found.segmentIndex, {});
                        }

                        if (shouldPersist) {
                            recordChange({
                                type: 'product_update',
                                entityType: 'product',
                                entityId: snapshot.entityId,
                                data: snapshot.beforeState,
                            });
                        }

                        return;
                    }
                }
            }
        }

        console.error('❌ Produto não encontrado:', snapshot.entityId);
    }

    function applySegmentTransferSnapshot(snapshot: any, shouldPersist: boolean) {
        if (!currentGondola.value) {
            return;
        }

        const { sourceShelfId, targetShelfId, sourceShelfSegments, targetShelfSegments } = snapshot.beforeState;

        const currentTargetShelf = findShelfById(targetShelfId);
        const currentSourceShelf = findShelfById(sourceShelfId);

        if (!currentTargetShelf || !currentSourceShelf) {
            console.error('❌ Prateleiras não encontradas para undo de transfer');

            return;
        }

        currentSourceShelf.shelf.segments = history.cloneState(sourceShelfSegments);
        currentTargetShelf.shelf.segments = history.cloneState(targetShelfSegments);

        currentSourceShelf.shelf.segments.forEach((seg: any) => {
            if (seg.shelf_id !== sourceShelfId) {
                seg.shelf_id = sourceShelfId;
            }
        });
        currentTargetShelf.shelf.segments.forEach((seg: any) => {
            if (seg.shelf_id !== targetShelfId) {
                seg.shelf_id = targetShelfId;
            }
        });

        currentSourceShelf.shelf.segments = [...currentSourceShelf.shelf.segments];
        currentTargetShelf.shelf.segments = [...currentTargetShelf.shelf.segments];
        currentSourceShelf.section.shelves = [...(currentSourceShelf.section.shelves || [])];
        currentTargetShelf.section.shelves = [...(currentTargetShelf.section.shelves || [])];

        if (currentGondola.value.sections) {
            currentGondola.value.sections = [...currentGondola.value.sections];
        }

        if (shouldPersist) {
            sourceShelfSegments.forEach((seg: any) => {
                recordChange({
                    type: 'segment_update',
                    entityType: 'segment',
                    entityId: seg.id,
                    data: { shelf_id: sourceShelfId, position: seg.position, ordering: seg.ordering },
                });
            });

            targetShelfSegments.forEach((seg: any) => {
                recordChange({
                    type: 'segment_update',
                    entityType: 'segment',
                    entityId: seg.id,
                    data: { shelf_id: targetShelfId, position: seg.position, ordering: seg.ordering },
                });
            });
        }
    }

    function applySegmentCopySnapshot(snapshot: any, shouldPersist: boolean) {
        if (!currentGondola.value) {
            return;
        }

        const { targetShelfId, targetShelfSegments, _rejectedProduct } = snapshot.beforeState;

        const targetShelfData = findShelfById(targetShelfId);

        if (!targetShelfData) {
            console.error('❌ Prateleira não encontrada para undo de copy');

            return;
        }

        // Restaura produto à lista de rejeitados se havia sido removido
        if (_rejectedProduct) {
            rejectedProducts.value = [_rejectedProduct, ...rejectedProducts.value];
        }

        targetShelfData.shelf.segments = history.cloneState(targetShelfSegments);

        targetShelfData.shelf.segments.forEach((seg: any) => {
            if (seg.shelf_id !== targetShelfId) {
                seg.shelf_id = targetShelfId;
            }
        });

        targetShelfData.shelf.segments = [...targetShelfData.shelf.segments];
        targetShelfData.section.shelves = [...(targetShelfData.section.shelves || [])];

        if (currentGondola.value.sections) {
            currentGondola.value.sections = [...currentGondola.value.sections];
        }

        if (shouldPersist) {
            targetShelfSegments.forEach((seg: any) => {
                recordChange({
                    type: 'segment_update',
                    entityType: 'segment',
                    entityId: seg.id,
                    data: { shelf_id: targetShelfId, position: seg.position, ordering: seg.ordering },
                });
            });
        }
    }

    function applyGondolaSnapshot(snapshot: any, shouldPersist: boolean) {
        if (!currentGondola.value) {
            return;
        }

        const gondolaId = currentGondola.value.id;
        Object.assign(currentGondola.value, snapshot.beforeState);
        currentGondola.value = { ...currentGondola.value };

        if (shouldPersist && gondolaId) {
            recordChange({
                type: snapshot.type,
                entityType: 'gondola',
                entityId: gondolaId,
                data: snapshot.beforeState,
            });
        }
    }

    return {
        commitOptimistic,
        recordChange,
        applySnapshot,
    };
}
