// ============================================================================
// GERENCIADOR DE SNAPSHOTS — commit otimista + apply (undo/redo)
// ============================================================================
// Centraliza toda a lógica de captura de estado before/after, commit otimista
// e aplicação de snapshots para undo/redo do editor de planogramas.
// ============================================================================

import { currentGondola, rejectedProducts } from '../core/useGondolaState';
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
                    if (
                        snapshot.sectionIds &&
                        Array.isArray(snapshot.sectionIds)
                    ) {
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

                case 'segments_reorder':
                    if (
                        snapshot.segmentIds &&
                        Array.isArray(snapshot.segmentIds)
                    ) {
                        const segmentsData: Record<string, number> = {};
                        snapshot.segmentIds.forEach((segmentId: string) => {
                            const found = findSegmentById(segmentId);

                            if (found) {
                                segmentsData[segmentId] =
                                    found.segment.ordering || 0;
                            }
                        });

                        return segmentsData;
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
    function captureAfterState(
        snapshot: OptimisticSnapshot,
        beforeState: SnapshotState,
    ): SnapshotState {
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
                            sourceShelfSegments: sourceShelf
                                ? history.cloneState(sourceShelf.shelf.segments)
                                : [],
                            targetShelfSegments: targetShelf
                                ? history.cloneState(targetShelf.shelf.segments)
                                : [],
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
                            targetShelfSegments: targetShelf
                                ? history.cloneState(targetShelf.shelf.segments)
                                : [],
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
                    if (
                        snapshot.sectionIds &&
                        Array.isArray(snapshot.sectionIds)
                    ) {
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

                case 'segments_reorder':
                    if (
                        snapshot.segmentIds &&
                        Array.isArray(snapshot.segmentIds)
                    ) {
                        const segmentsData: Record<string, number> = {};
                        snapshot.segmentIds.forEach((segmentId: string) => {
                            const found = findSegmentById(segmentId);

                            if (found) {
                                segmentsData[segmentId] =
                                    found.segment.ordering || 0;
                            }
                        });

                        return segmentsData;
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
                    afterState = captureAfterState(
                        historySnapshot,
                        beforeState,
                    );
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

    /**
     * Registra o delta de um undo/redo. Usa `replace: true` para SOBRESCREVER
     * qualquer delta pendente da MESMA entidade em vez de mesclar — caso
     * contrário o merge de `pendingChanges` preservaria o `type` da edição
     * original (ex.: segment_transfer) e o backend re-aplicaria a edição em vez
     * do undo. O undo define o estado-alvo completo da entidade, então vence.
     */
    function recordUndoChange(change: OptimisticChange) {
        changes.recordChange(change, { schedule: true, replace: true });
    }

    // ========================================================================
    // APLICAÇÃO DE SNAPSHOTS (undo/redo)
    // ========================================================================

    /**
     * Aplica um snapshot ao estado atual da gôndola E registra para persistência
     */
    function applySnapshot(snapshot: any, shouldPersist: boolean = true) {
        if (!currentGondola.value || !snapshot) {
            console.error(
                '❌ Não foi possível aplicar snapshot: gôndola ou snapshot inválidos',
            );

            return false;
        }

        try {
            switch (snapshot.type) {
                case 'shelf_position':
                case 'shelf_update':
                case 'shelf_transfer':
                    applyShelfSnapshot(snapshot, shouldPersist);
                    break;

                case 'shelf_delete':
                    applyShelfDeleteSnapshot(snapshot, shouldPersist);
                    break;

                case 'section_delete':
                    applySectionDeleteSnapshot(snapshot, shouldPersist);
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

                case 'segments_reorder':
                    applySegmentsReorderSnapshot(snapshot, shouldPersist);
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
                    console.warn(
                        '⚠️ Tipo de snapshot não implementado:',
                        snapshot.type,
                    );

                    return false;
            }

            return true;
        } catch (error) {
            console.error('❌ Erro ao aplicar snapshot:', error);

            return false;
        }
    }

    /**
     * Undo/redo de EXCLUSÃO de prateleira.
     *
     * `state` é o `beforeState` (undo → deleted_at null = restaurar) ou o
     * `afterState` (redo → deleted_at preenchido = re-excluir). Quando restaura e
     * a shelf não está mais na árvore (removida pelo sanitizeGondola na
     * re-hidratação), reinsere a partir do clone completo do snapshot. A linha
     * ainda existe no banco (soft-deleted); o `shelf_update { deleted_at }`
     * persiste a restauração/re-exclusão.
     */
    function applyShelfDeleteSnapshot(snapshot: any, shouldPersist: boolean) {
        if (!currentGondola.value) {
            return;
        }

        const state = snapshot.beforeState;

        if (!state) {
            return;
        }

        const isRestore = !state.deleted_at;
        const section = findSectionById(state.section_id);

        if (!section) {
            console.error(
                '❌ Seção não encontrada para shelf_delete:',
                state.section_id,
            );

            return;
        }

        const existing = findShelfById(snapshot.shelfId);

        if (isRestore) {
            if (existing) {
                existing.shelf.deleted_at = null;
            } else {
                if (!section.shelves) {
                    section.shelves = [];
                }

                if (
                    !section.shelves.some((s: any) => s.id === snapshot.shelfId)
                ) {
                    section.shelves.push({
                        ...history.cloneState(state),
                        id: snapshot.shelfId,
                        deleted_at: null,
                    });
                }
            }
        } else if (existing) {
            existing.shelf.deleted_at = state.deleted_at;
        }

        section.shelves = [...(section.shelves || [])];

        if (currentGondola.value.sections) {
            currentGondola.value.sections = [...currentGondola.value.sections];
        }

        if (shouldPersist) {
            recordUndoChange({
                type: 'shelf_update',
                entityType: 'shelf',
                entityId: snapshot.shelfId,
                data: { deleted_at: isRestore ? null : state.deleted_at },
            });
        }
    }

    /**
     * Undo/redo de EXCLUSÃO de seção/módulo. Mesma lógica de
     * applyShelfDeleteSnapshot, reinserindo a seção completa (com prateleiras)
     * na gôndola quando necessário.
     */
    function applySectionDeleteSnapshot(snapshot: any, shouldPersist: boolean) {
        if (!currentGondola.value) {
            return;
        }

        const state = snapshot.beforeState;

        if (!state) {
            return;
        }

        const isRestore = !state.deleted_at;

        if (!currentGondola.value.sections) {
            currentGondola.value.sections = [];
        }

        const idx = currentGondola.value.sections.findIndex(
            (s: any) => s.id === snapshot.sectionId,
        );

        if (isRestore) {
            if (idx !== -1) {
                currentGondola.value.sections[idx] = {
                    ...currentGondola.value.sections[idx],
                    deleted_at: undefined,
                };
            } else {
                currentGondola.value.sections.push({
                    ...history.cloneState(state),
                    id: snapshot.sectionId,
                    deleted_at: undefined,
                });
            }
        } else if (idx !== -1) {
            currentGondola.value.sections[idx] = {
                ...currentGondola.value.sections[idx],
                deleted_at: state.deleted_at,
            };
        }

        currentGondola.value.sections = [...currentGondola.value.sections];
        currentGondola.value = { ...currentGondola.value };

        if (shouldPersist) {
            recordUndoChange({
                type: 'section_update',
                entityType: 'section',
                entityId: snapshot.sectionId,
                data: { deleted_at: isRestore ? null : state.deleted_at },
            });
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

            const shelfIndex =
                currentSection.shelves?.findIndex(
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
                    currentGondola.value.sections = [
                        ...currentGondola.value.sections,
                    ];
                }
            }
        } else {
            Object.assign(currentShelfData.shelf, beforeState);

            const section = findSectionById(currentSectionId);

            if (section && currentGondola.value?.sections) {
                section.shelves = [...(section.shelves || [])];

                const sectionIndex = currentGondola.value.sections.findIndex(
                    (s) => s.id === currentSectionId,
                );

                if (sectionIndex !== -1) {
                    updateSectionReactive(sectionIndex, {});
                }
            }
        }

        if (shouldPersist) {
            const isTransfer = currentSectionId !== targetSectionId;

            if (isTransfer) {
                recordUndoChange({
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
                recordUndoChange({
                    type: 'shelf_move',
                    entityType: 'shelf',
                    entityId: snapshot.shelfId,
                    data: { shelf_position: beforeState.shelf_position },
                });
            }
        }
    }

    /**
     * Reinsere um segmento na sua shelf a partir do `beforeState` do snapshot.
     *
     * Usado no UNDO de EXCLUSÃO: após o auto-save + re-hidratação, o
     * `sanitizeGondola` remove o segmento soft-deleted da árvore em memória, então
     * o `findSegmentById` do undo não acha nada para restaurar. O `beforeState`
     * carrega o segmento completo (com layer/produto e `shelf_id`), então o
     * reconstruímos e reinserimos. A linha ainda existe no banco (soft-deleted,
     * mesmo ULID); o `segment_update { deleted_at: null }` a restaura.
     */
    function reinsertSegmentFromState(
        segmentId: string,
        beforeState: any,
    ): boolean {
        if (!currentGondola.value || !beforeState?.shelf_id) {
            return false;
        }

        const shelfData = findShelfById(beforeState.shelf_id);

        if (!shelfData) {
            return false;
        }

        if (!shelfData.shelf.segments) {
            shelfData.shelf.segments = [];
        }

        // Idempotência: redo repetido / dupla aplicação não duplica o segmento.
        if (shelfData.shelf.segments.some((s: any) => s.id === segmentId)) {
            return true;
        }

        const segment = {
            ...history.cloneState(beforeState),
            id: segmentId,
            deleted_at: null,
        };

        shelfData.shelf.segments.push(segment);
        shelfData.shelf.segments.sort(
            (a: any, b: any) => (a.ordering ?? 0) - (b.ordering ?? 0),
        );
        shelfData.shelf.segments = [...shelfData.shelf.segments];
        shelfData.section.shelves = [...(shelfData.section.shelves || [])];

        if (currentGondola.value.sections) {
            currentGondola.value.sections = [...currentGondola.value.sections];
        }

        return true;
    }

    function applySegmentSnapshot(snapshot: any, shouldPersist: boolean) {
        const found = findSegmentById(snapshot.segmentId);

        if (found) {
            Object.assign(found.segment, snapshot.beforeState);

            updateSegmentReactive(
                found.section,
                found.shelfIndex,
                found.segmentIndex,
                {},
            );
        } else if (
            !reinsertSegmentFromState(snapshot.segmentId, snapshot.beforeState)
        ) {
            console.error('❌ Segment não encontrado:', snapshot.segmentId);

            return;
        }

        if (shouldPersist) {
            recordUndoChange({
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

        const sectionIndex = currentGondola.value.sections.findIndex(
            (s) => s.id === snapshot.sectionId,
        );

        if (sectionIndex !== -1) {
            updateSectionReactive(sectionIndex, snapshot.beforeState);
        }

        if (shouldPersist) {
            const allowedFields = [
                'name',
                'code',
                'width',
                'height',
                'num_shelves',
                'base_height',
                'base_depth',
                'base_width',
                'cremalheira_width',
                'hole_height',
                'hole_width',
                'hole_spacing',
                'ordering',
                'alignment',
            ];

            const data: Record<string, any> = {};

            for (const field of allowedFields) {
                if (snapshot.beforeState[field] !== undefined) {
                    data[field] = snapshot.beforeState[field];
                }
            }

            recordUndoChange({
                type: 'section_update',
                entityType: 'section',
                entityId: snapshot.sectionId,
                data,
            });
        }
    }

    function applySectionsReorderSnapshot(
        snapshot: any,
        shouldPersist: boolean,
    ) {
        if (
            !currentGondola.value?.sections ||
            !snapshot.sectionIds ||
            !snapshot.beforeState
        ) {
            console.error('❌ Dados inválidos para reordenação de seções');

            return;
        }

        const beforeOrderings = snapshot.beforeState as Record<string, number>;

        snapshot.sectionIds.forEach((sectionId: string) => {
            const sectionIndex =
                currentGondola.value?.sections?.findIndex(
                    (s: any) => s.id === sectionId,
                ) ?? -1;

            if (
                sectionIndex !== -1 &&
                beforeOrderings[sectionId] !== undefined
            ) {
                updateSectionReactive(sectionIndex, {
                    ordering: beforeOrderings[sectionId],
                });
            }
        });

        if (shouldPersist) {
            snapshot.sectionIds.forEach((sectionId: string) => {
                if (beforeOrderings[sectionId] !== undefined) {
                    recordUndoChange({
                        type: 'section_update',
                        entityType: 'section',
                        entityId: sectionId,
                        data: { ordering: beforeOrderings[sectionId] },
                    });
                }
            });
        }
    }

    function applySegmentsReorderSnapshot(
        snapshot: any,
        shouldPersist: boolean,
    ) {
        if (
            !currentGondola.value ||
            !snapshot.segmentIds ||
            !snapshot.beforeState
        ) {
            console.error('❌ Dados inválidos para reordenação de segmentos');

            return;
        }

        const beforeOrderings = snapshot.beforeState as Record<string, number>;
        const segmentIds: string[] = snapshot.segmentIds;

        // Âncora para reatividade: a shelf do primeiro segmento localizado (todos
        // os segmentos do snapshot compartilham a mesma shelf).
        const anchor =
            segmentIds
                .map((id: string) => findSegmentById(id))
                .find((f) => f !== null) ?? null;

        // Restaura o `ordering` de TODOS os segmentos do snapshot (o swap troca
        // dois — reverter só um deixaria ambos com o mesmo `ordering`).
        segmentIds.forEach((segmentId: string) => {
            if (beforeOrderings[segmentId] === undefined) {
                return;
            }

            const found = findSegmentById(segmentId);

            if (!found) {
                return;
            }

            found.segment.ordering = beforeOrderings[segmentId];

            if (shouldPersist) {
                recordUndoChange({
                    type: 'segment_reorder',
                    entityType: 'segment',
                    entityId: segmentId,
                    data: {
                        shelf_id: found.shelf.id,
                        ordering: beforeOrderings[segmentId],
                    },
                });
            }
        });

        // Re-sorta o array da shelf pela nova `ordering` e força reatividade.
        if (anchor) {
            const { shelf, section, shelfIndex } = anchor;
            const segments = [...(shelf.segments || [])];
            segments.sort(
                (a: any, b: any) => (a.ordering ?? 0) - (b.ordering ?? 0),
            );
            shelf.segments = segments;
            section.shelves[shelfIndex] = { ...shelf };
            section.shelves = [...section.shelves];

            if (currentGondola.value?.sections) {
                currentGondola.value.sections = [
                    ...currentGondola.value.sections,
                ];
            }
        }
    }

    /**
     * Reanexa uma layer ao seu segmento a partir do `beforeState`.
     *
     * Usado no UNDO de EXCLUSÃO de produto (soft-delete da layer): após a
     * re-hidratação, o escopo SoftDeletes do Eloquent exclui a layer deletada, e
     * `segment.layer` volta `null` — então `findSegmentByLayerId` não a encontra.
     * O `beforeState` carrega a layer completa (com `segment_id`/produto). A linha
     * ainda existe no banco (soft-deleted); o `layer_update { deleted_at: null }`
     * a restaura.
     */
    function reattachLayerFromState(layerId: string, layerState: any): boolean {
        if (!currentGondola.value || !layerState?.segment_id) {
            return false;
        }

        const found = findSegmentById(layerState.segment_id);

        if (!found) {
            return false;
        }

        found.segment.layer = {
            ...history.cloneState(layerState),
            id: layerId,
            deleted_at: null,
        };

        updateSegmentReactive(
            found.section,
            found.shelfIndex,
            found.segmentIndex,
            {},
        );

        return true;
    }

    function applyLayerSnapshot(snapshot: any, shouldPersist: boolean) {
        const found = findSegmentByLayerId(snapshot.layerId);
        const { _rejectedProduct, ...layerState } = snapshot.beforeState ?? {};

        if (found && found.segment.layer) {
            Object.assign(found.segment.layer, layerState);

            updateSegmentReactive(
                found.section,
                found.shelfIndex,
                found.segmentIndex,
                {},
            );
        } else if (!reattachLayerFromState(snapshot.layerId, layerState)) {
            console.error('❌ Layer não encontrado:', snapshot.layerId);

            return;
        }

        // Restaura produto à lista de rejeitados se havia sido removido
        if (_rejectedProduct) {
            rejectedProducts.value = [
                _rejectedProduct,
                ...rejectedProducts.value,
            ];
        }

        if (shouldPersist) {
            recordUndoChange({
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
                        Object.assign(
                            segment.layer?.product as any,
                            snapshot.beforeState,
                        );

                        const found = findSegmentById(segment.id);

                        if (found) {
                            updateSegmentReactive(
                                found.section,
                                found.shelfIndex,
                                found.segmentIndex,
                                {},
                            );
                        }

                        if (shouldPersist) {
                            recordUndoChange({
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

    function applySegmentTransferSnapshot(
        snapshot: any,
        shouldPersist: boolean,
    ) {
        if (!currentGondola.value) {
            return;
        }

        const {
            sourceShelfId,
            targetShelfId,
            sourceShelfSegments,
            targetShelfSegments,
        } = snapshot.beforeState;

        const currentTargetShelf = findShelfById(targetShelfId);
        const currentSourceShelf = findShelfById(sourceShelfId);

        if (!currentTargetShelf || !currentSourceShelf) {
            console.error(
                '❌ Prateleiras não encontradas para undo de transfer',
            );

            return;
        }

        currentSourceShelf.shelf.segments =
            history.cloneState(sourceShelfSegments);
        currentTargetShelf.shelf.segments =
            history.cloneState(targetShelfSegments);

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

        currentSourceShelf.shelf.segments = [
            ...currentSourceShelf.shelf.segments,
        ];
        currentTargetShelf.shelf.segments = [
            ...currentTargetShelf.shelf.segments,
        ];
        currentSourceShelf.section.shelves = [
            ...(currentSourceShelf.section.shelves || []),
        ];
        currentTargetShelf.section.shelves = [
            ...(currentTargetShelf.section.shelves || []),
        ];

        if (currentGondola.value.sections) {
            currentGondola.value.sections = [...currentGondola.value.sections];
        }

        if (shouldPersist) {
            sourceShelfSegments.forEach((seg: any) => {
                recordUndoChange({
                    type: 'segment_update',
                    entityType: 'segment',
                    entityId: seg.id,
                    data: {
                        shelf_id: sourceShelfId,
                        position: seg.position,
                        ordering: seg.ordering,
                    },
                });
            });

            targetShelfSegments.forEach((seg: any) => {
                recordUndoChange({
                    type: 'segment_update',
                    entityType: 'segment',
                    entityId: seg.id,
                    data: {
                        shelf_id: targetShelfId,
                        position: seg.position,
                        ordering: seg.ordering,
                    },
                });
            });
        }
    }

    function applySegmentCopySnapshot(snapshot: any, shouldPersist: boolean) {
        if (!currentGondola.value) {
            return;
        }

        const { targetShelfId, targetShelfSegments, _rejectedProduct } =
            snapshot.beforeState;

        const targetShelfData = findShelfById(targetShelfId);

        if (!targetShelfData) {
            console.error('❌ Prateleira não encontrada para undo de copy');

            return;
        }

        // Restaura produto à lista de rejeitados se havia sido removido
        if (_rejectedProduct) {
            rejectedProducts.value = [
                _rejectedProduct,
                ...rejectedProducts.value,
            ];
        }

        targetShelfData.shelf.segments =
            history.cloneState(targetShelfSegments);

        targetShelfData.shelf.segments.forEach((seg: any) => {
            if (seg.shelf_id !== targetShelfId) {
                seg.shelf_id = targetShelfId;
            }
        });

        targetShelfData.shelf.segments = [...targetShelfData.shelf.segments];
        targetShelfData.section.shelves = [
            ...(targetShelfData.section.shelves || []),
        ];

        if (currentGondola.value.sections) {
            currentGondola.value.sections = [...currentGondola.value.sections];
        }

        if (shouldPersist) {
            targetShelfSegments.forEach((seg: any) => {
                recordUndoChange({
                    type: 'segment_update',
                    entityType: 'segment',
                    entityId: seg.id,
                    data: {
                        shelf_id: targetShelfId,
                        position: seg.position,
                        ordering: seg.ordering,
                    },
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
            recordUndoChange({
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
