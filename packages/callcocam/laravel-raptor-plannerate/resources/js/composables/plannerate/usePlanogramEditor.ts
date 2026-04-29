// ============================================================================
// IMPORTS
// ============================================================================

import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { calculateAbc } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaAnalysisController';
import { show as gondolaView } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaPdfPreviewController';
import { usePlanogramChanges } from '@/composables/plannerate/usePlanogramChanges';
import { usePlanogramHistory } from '@/composables/plannerate/usePlanogramHistory';
import { findNearestHole } from '@/composables/plannerate/useSectionHoles';
import type { Gondola, Product, Section, Shelf } from '@/types/planogram';

// Importa módulos separados por responsabilidade
import {
    currentGondola,
    scaleFactor,
    selectedId,
    selectedItem,
    selectedType,
    showGrid,
    showPerformanceModal,
    showProductsPanel,
    showPropertiesPanel,
} from './editor/useGondolaState';


import {
    findSectionById,
    findSegmentById,
    findSegmentByLayerId,
    findShelfById,
} from './editor/useLookupHelpers';

import {
    updateSectionReactive,
    updateSegmentReactive,
} from './editor/useReactivityHelpers';

import { useSectionOperations } from './editor/useSectionOperations';
import {
    copySegmentToShelf as copySegmentOperation,
    moveSegmentToShelf as moveSegmentOperation,
    swapSegmentPositions as swapSegmentOp,
} from './editor/useSegmentOperations';

import { useShelfOperations } from './editor/useShelfOperations';

// ============================================================================
// COMPOSABLE
// ============================================================================

const isBrowser = typeof window !== 'undefined';

export function usePlanogramEditor() {
    const history = usePlanogramHistory();
    const changes = usePlanogramChanges();

    // Inicializa módulos de operações
    const sectionOps = useSectionOperations();
    const shelfOps = useShelfOperations();

    // Tipos auxiliares para commit otimista
    type OptimisticSnapshot = Parameters<typeof history.recordAction>[0];
    type OptimisticChange = Parameters<typeof changes.recordChange>[0];

    // ========================================================================
    // COMMIT OTIMISTA
    // ========================================================================

    /**
     * Captura o estado ANTES da mudança para histórico
     */
    function captureBeforeState(snapshot: OptimisticSnapshot): any {
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
                        
                        // Captura posição, section_id e outros dados importantes
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
                    // Captura o ordering de TODAS as seções afetadas
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
    function captureAfterState(snapshot: OptimisticSnapshot, beforeState: any): any {
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
                        
                        // Captura nova posição, section_id e outros dados importantes
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

                case 'segment_transfer':
                    // Para transfer, captura estado completo DEPOIS
                    if (beforeState.sourceShelfId && beforeState.targetShelfId) {
                        const sourceShelf = findShelfById(beforeState.sourceShelfId);
                        const targetShelf = findShelfById(beforeState.targetShelfId);
                        
                        return {
                            sourceShelfId: beforeState.sourceShelfId,
                            targetShelfId: beforeState.targetShelfId,
                            segmentId: beforeState.segmentId,
                            sourceShelfSegments: sourceShelf ? history.cloneState(sourceShelf.shelf.segments) : [],
                            targetShelfSegments: targetShelf ? history.cloneState(targetShelf.shelf.segments) : [],
                        };
                    }

                    break;

                case 'segment_copy':
                    // Para copy, captura estado DEPOIS da prateleira de destino
                    if (beforeState.targetShelfId) {
                        const targetShelf = findShelfById(beforeState.targetShelfId);
                        
                        return {
                            targetShelfId: beforeState.targetShelfId,
                            targetShelfSegments: targetShelf ? history.cloneState(targetShelf.shelf.segments) : [],
                        };
                    }

                    break;

                case 'section_update':
                    if (snapshot.sectionId) {
                        const section = findSectionById(snapshot.sectionId);

                        return section ? history.cloneState(section) : null;
                    }

                    break;

                case 'sections_reorder':
                    // Captura o ordering de TODAS as seções afetadas após a mudança
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
            // Captura estado ANTES
            let beforeState: any = null;

            if (historySnapshot) {
                // Se beforeState já foi passado manualmente, usa ele
                if (historySnapshot.beforeState) {
                    beforeState = historySnapshot.beforeState;
                } else {
                    beforeState = captureBeforeState(historySnapshot);
                }
            }

            // Aplica a mudança
            const result = apply();

            // Captura estado DEPOIS e registra no histórico
            if (historySnapshot && beforeState) {
                // Se afterState já foi passado manualmente, usa ele
                let afterState: any;

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

            // Registra mudança para persistência
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
    // OPERAÇÕES COM SEGMENTOS (usando módulo separado)
    // ========================================================================

    /**
     * Reordena segment dentro da mesma shelf (swap de posições)
     */
    function swapSegmentPositions(
        segment1Id: string,
        segment2Id: string,
    ): boolean {
        const segment1 = findSegmentById(segment1Id);
        const segment2 = findSegmentById(segment2Id);
        
        if (!segment1 || !segment2) {
return false;
}

        if (segment1.shelf.id !== segment2.shelf.id) {
return false;
}

        const result = commitOptimistic({
            apply: () => swapSegmentOp(segment1Id, segment2Id, recordChange),
            historySnapshot: {
                type: 'segment_position',
                segmentId: segment1Id,
                shelfId: segment1.shelf.id,
                sectionId: segment1.section.id,
                description: `Reordenar produto na prateleira`,
                beforeState: null,
                afterState: null,
            },
        });

        return result !== null;
    }

    /**
     * Move um segmento para outra prateleira
     */
    function moveSegmentToShelf(
        segmentId: string,
        targetShelfId: string,
    ): boolean {
        const found = findSegmentById(segmentId);

        if (!found) {
return false;
}

        const targetShelf = findShelfById(targetShelfId);

        if (!targetShelf) {
return false;
}

        // Captura estado completo ANTES da operação
        const beforeState = {
            sourceShelfId: found.shelf.id,
            targetShelfId: targetShelfId,
            segmentId: segmentId,
            sourceShelfSegments: history.cloneState(found.shelf.segments),
            targetShelfSegments: history.cloneState(targetShelf.shelf.segments),
        };

        const result = commitOptimistic({
            apply: () => moveSegmentOperation(segmentId, targetShelfId, recordChange),
            historySnapshot: {
                type: 'segment_transfer',
                segmentId: segmentId,
                shelfId: found.shelf.id,
                sectionId: found.section.id,
                description: `Mover produto para outra prateleira`,
                beforeState: beforeState,
                afterState: null, // será capturado automaticamente
            },
        });

        return result !== null;
    }

    /**
     * Copia um segmento para outra prateleira
     */
    function copySegmentToShelf(
        segmentId: string,
        targetShelfId: string,
    ): boolean {
        const found = findSegmentById(segmentId);

        if (!found) {
return false;
}

        const targetShelf = findShelfById(targetShelfId);

        if (!targetShelf) {
return false;
}

        // Captura estado completo ANTES da operação
        const beforeState = {
            sourceShelfId: found.shelf.id,
            targetShelfId: targetShelfId,
            sourceSegmentId: segmentId,
            targetShelfSegments: history.cloneState(targetShelf.shelf.segments),
        };

        const result = commitOptimistic({
            apply: () => copySegmentOperation(segmentId, targetShelfId, recordChange),
            historySnapshot: {
                type: 'segment_copy',
                segmentId: segmentId,
                shelfId: targetShelfId,
                sectionId: found.section.id,
                description: `Copiar produto para outra prateleira`,
                beforeState: beforeState,
                afterState: null, // será capturado automaticamente
            },
        });

        return result !== null;
    }

    // ========================================================================
    // OPERAÇÕES COM SHELVES (usando módulo separado)
    // ========================================================================

    /**
     * Move shelf dentro da mesma section (drag vertical)
     */
    function moveShelfWithinSection(
        shelfId: string,
        newPosition: number,
    ): boolean {
        const shelf = findShelfById(shelfId);

        if (!shelf) {
return false;
}

        const result = commitOptimistic({
            apply: () => shelfOps.moveShelfWithinSection(shelfId, newPosition, recordChange),
            historySnapshot: {
                type: 'shelf_position',
                shelfId: shelfId,
                sectionId: shelf.section.id,
                description: `Mover prateleira verticalmente`,
                beforeState: null,
                afterState: null,
            },
        });

        return result !== null;
    }

    /**
     * Move shelf para outra section (drag horizontal)
     */
    function moveShelfToSection(
        shelfId: string,
        targetSectionId: string,
        newPosition: number,
    ): boolean {
        const shelf = findShelfById(shelfId);

        if (!shelf) {
return false;
}

        const result = commitOptimistic({
            apply: () => shelfOps.moveShelfToSection(shelfId, targetSectionId, newPosition, recordChange),
            historySnapshot: {
                type: 'shelf_transfer',
                shelfId: shelfId,
                sectionId: shelf.section.id,
                description: `Mover prateleira para outra seção`,
                beforeState: null,
                afterState: null,
            },
        });

        return result !== null;
    }

    function invertShelvesOrder(sectionId: string): void {
        commitOptimistic({
            apply: () => {
                shelfOps.invertShelvesOrder(sectionId, recordChange);
            },
        });
    }

    // ========================================================================
    // SANITIZAÇÃO
    // ========================================================================
    function sanitizeGondola(gondola: Gondola): Gondola {
        const sections = (gondola.sections || [])
            .filter((section: any) => !section.deleted_at)
            .map((section: any) => {
                const shelves = (section.shelves || [])
                    .filter((s: any) => !s.deleted_at)
                    .map((shelf: any) => {
                        // Encaixa a prateleira no furo mais próximo ao carregar
                        const snappedPosition = findNearestHole(
                            section,
                            shelf.shelf_position || 0,
                        );

                        const segments = (shelf.segments || [])
                            .filter((seg: any) => !seg.deleted_at)
                            .map((seg: any) => ({
                                ...seg,
                                layers:
                                    seg.layers?.filter(
                                        (layer: any) => !layer.deleted_at,
                                    ) || [],
                            }));

                        return {
                            ...shelf,
                            shelf_position: snappedPosition,
                            segments,
                        };
                    });

                return { ...section, shelves };
            });

        return { ...gondola, sections };
    }

    // ========================================================================
    // COMPUTED
    // ========================================================================

    const sectionsOrdered = computed(() => {
        const sections = currentGondola.value?.sections;
        const flow = currentGondola.value?.flow || 'left_to_right';

        if (!sections) {
return [];
}

        // Filtra seções deletadas e ordena por ordering
        const filtered = sections.filter((s: Section) => !s.deleted_at);
        const ordered = [...filtered].sort(
            (a: Section, b: Section) => (a.ordering || 0) - (b.ordering || 0),
        );

        // Se o fluxo for right_to_left, inverte apenas visualmente
        return flow === 'right_to_left' ? ordered.reverse() : ordered;
    });

    // ========================================================================
    // INICIALIZAÇÃO
    // ========================================================================

    /**
     * Inicializa o editor com dados da gondola
     */
    function initializeEditor(gondola: Gondola) {
        // Sanitiza e atribui
        currentGondola.value = sanitizeGondola(gondola);

        // Carrega scale do localStorage ou usa padrão
        const savedScale = isBrowser
            ? window.localStorage.getItem(`gondola_${gondola.id}_scale`)
            : null;

        if (savedScale) {
            const scale = parseFloat(savedScale);
            scaleFactor.value =
                scale >= 1 && scale <= 10 ? scale : gondola.scale_factor || 3;
        } else {
            scaleFactor.value = gondola.scale_factor || 3;
        }

        // Inicializa histórico
        history.initializeHistory();
    }

    /**
     * Atualiza a gondola mantendo referência reativa
     * Usa commit otimista para histórico + auto-save
     * Também atualiza a gôndola no array planogram.gondolas
     *
     * @param updates - Propriedades a serem atualizadas
     * @returns Gondola atualizada ou null se não houver gondola
     */
    function updateGondola(updates: Partial<Gondola>) {
        if (!currentGondola.value?.id) {
return null;
}

        return commitOptimistic({
            apply: () => {
                // Força reatividade criando novo objeto
                currentGondola.value = { ...currentGondola.value!, ...updates };

                // Atualiza também no array de gôndolas do planograma
                if (currentGondola.value.planogram?.gondolas) {
                    const gondolaIndex =
                        currentGondola.value.planogram.gondolas.findIndex(
                            (g) => g.id === currentGondola.value!.id,
                        );

                    if (gondolaIndex !== -1) {
                        // Força reatividade substituindo o array
                        currentGondola.value.planogram.gondolas = [
                            ...currentGondola.value.planogram.gondolas.slice(
                                0,
                                gondolaIndex,
                            ),
                            currentGondola.value,
                            ...currentGondola.value.planogram.gondolas.slice(
                                gondolaIndex + 1,
                            ),
                        ];
                    }
                }

                return currentGondola.value;
            },
            historySnapshot: {
                type: 'gondola_update',
                description: `Atualizar gôndola`,
                beforeState: null, // será capturado automaticamente
                afterState: null, // será capturado automaticamente
            },
            change: {
                type: 'gondola_update',
                entityType: 'gondola',
                entityId: currentGondola.value.id,
                data: updates,
            },
        });
    }

    /**
     * Retorna gôndolas disponíveis no planograma
     */
    function gondolasAvailable(): Gondola[] {
        if (!currentGondola.value) {
return [];
}

        const { gondolas } = currentGondola.value.planogram || {};

        return gondolas || [];
    }

    // ========================================================================
    // OPERAÇÕES COM SECTIONS/SHELVES (usando módulos separados)
    // ========================================================================

    function addShelf(sectionId: string, shelfData: Partial<Shelf>) {
        return commitOptimistic({
            apply: () => shelfOps.addShelf(sectionId, shelfData, recordChange),
        });
    }

    /**
     * Adiciona um produto a uma prateleira criando a hierarquia: Segment → Layer → Product
     */
    function addProductToShelf(
        shelfId: string,
        productId: string,
        productData?: any,
        onProductUsed?: (productId: string) => void,
    ) {
        const shelf = findShelfById(shelfId);

        if (!shelf) {
return null;
}

        return commitOptimistic({
            apply: () =>
                shelfOps.addProductToShelf(
                    shelfId,
                    productId,
                    productData,
                    onProductUsed,
                    recordChange,
                ),
            historySnapshot: {
                type: 'segment_update',
                sectionId: shelf.section.id,
                description: `Adicionar produto`,
                beforeState: null,
                afterState: null,
            },
        });
    }

    /**
     * Reordena as seções da gôndola atual
     */
    function reorderSectionsByOrdering(): void {
        sectionOps.reorderSectionsByOrdering();
    }

    /**
     * Adiciona uma nova seção à gôndola atual
     */
    function addSection(sectionData: Partial<Section>) {
        return commitOptimistic({
            apply: () => sectionOps.addSection(sectionData, recordChange),
        });
    }

    /**
     * Atualiza shelf em tempo real
     */
    function updateShelf(shelfId: string, updates: Partial<any>) {
        const shelf = findShelfById(shelfId);

        if (!shelf) {
return null;
}

        return commitOptimistic({
            apply: () => shelfOps.updateShelf(shelfId, updates, recordChange),
            historySnapshot: {
                type: 'shelf_update',
                shelfId: shelfId,
                sectionId: shelf.section.id,
                description: `Atualizar prateleira`,
                beforeState: null, // será capturado automaticamente
                afterState: null, // será capturado automaticamente
            },
        });
    }

    /**
     * Atualiza section em tempo real
     */
    function updateSection(sectionId: string, updates: Partial<any>) {
        return commitOptimistic({
            apply: () =>
                sectionOps.updateSection(
                    sectionId,
                    updates,
                    updateSectionReactive,
                    recordChange,
                ),
            historySnapshot: {
                type: 'section_update',
                sectionId: sectionId,
                description: `Atualizar seção`,
                beforeState: null, // será capturado automaticamente
                afterState: null, // será capturado automaticamente
            },
        });
    }

    /**
     * Troca a ordem de seções (usado para mover com teclado)
     * Captura TODAS as mudanças de ordering em um único snapshot
     */
    function swapSectionsOrdering(sectionIds: string[], newOrderings: Record<string, number>) {
        if (!currentGondola.value?.sections) {
return;
}

        return commitOptimistic({
            apply: () => {
                // Aplica as novas ordenações
                sectionIds.forEach(sectionId => {
                    const section = findSectionById(sectionId);

                    if (section && newOrderings[sectionId] !== undefined) {
                        section.ordering = newOrderings[sectionId];
                    }
                });

                // Força reatividade
                if (currentGondola.value?.sections) {
                    currentGondola.value.sections = [...currentGondola.value.sections];
                }

                // Registra mudanças
                sectionIds.forEach(sectionId => {
                    if (newOrderings[sectionId] !== undefined) {
                        recordChange({
                            type: 'section_update',
                            entityType: 'section',
                            entityId: sectionId,
                            data: { ordering: newOrderings[sectionId] },
                        });
                    }
                });
            },
            historySnapshot: {
                type: 'sections_reorder',
                sectionIds,
                description: `Reordenar seções`,
                beforeState: null, // será capturado
                afterState: null,
            },
        });
    }

    /**
     * Atualiza segment em tempo real
     */
    function updateSegment(segmentId: string, updates: Partial<any>) {
        const found = findSegmentById(segmentId);

        if (!found) {
return null;
}

        return commitOptimistic({
            apply: () => {
                const segment = found.segment;
                Object.assign(segment, updates);

                // Força reatividade criando novos arrays/objetos
                // Atualiza o segmento específico
                const updatedSegments = [...found.shelf.segments];
                updatedSegments[found.segmentIndex] = { ...segment };
                found.shelf.segments = updatedSegments;

                // Atualiza a prateleira na seção
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

                // Atualiza a seção na gôndola
                if (currentGondola.value?.sections) {
                    const updatedSections = [...currentGondola.value.sections];
                    const sectionIndex = updatedSections.findIndex(
                        (s: any) => s.id === found.section.id,
                    );

                    if (sectionIndex !== -1) {
                        updatedSections[sectionIndex] = {
                            ...found.section,
                            shelves: updatedShelves,
                        };
                        currentGondola.value.sections = updatedSections;
                    }
                }

                return segment;
            },
            historySnapshot: {
                type: 'segment_update',
                segmentId: segmentId,
                sectionId: found.section.id,
                description: `Atualizar produto`,
                beforeState: null, // será capturado automaticamente
                afterState: null, // será capturado automaticamente
            },
            change: {
                type: 'segment_update',
                entityType: 'segment',
                entityId: segmentId,
                data: updates,
            },
        });
    }
    /**
        * Inverte a ordem dos segments de uma shelf
        */
    function invertSegmentsOrder(shelfId: string): void {
        commitOptimistic({
            apply: () => {
                shelfOps.invertSegmentsOrder(shelfId, recordChange);
            },
        });
    }
    // ========================================================================
    // SANITIZAÇÃO
    // ========================================================================

    /**
     * Sanitiza gondola removendo deleted_at recursivamente
     */

    /**
     * Atualiza layer em tempo real
     */
    function updateLayer(layerId: string, updates: Partial<any>) {
        const found = findSegmentByLayerId(layerId);

        if (!found || !found.segment.layer) {
return null;
}

        return commitOptimistic({
            apply: () => {
                updateSegmentReactive(
                    found.section,
                    found.shelfIndex,
                    found.segmentIndex,
                    { layer: updates },
                );

                return found.segment.layer;
            },
            historySnapshot: {
                type: 'layer_update',
                layerId: layerId,
                sectionId: found.section.id,
                description: `Atualizar quantidade de produto`,
                beforeState: null,
                afterState: null,
            },
            change: {
                type: 'layer_update',
                entityType: 'layer',
                entityId: layerId,
                data: updates,
            },
        });
    }

    /**
     * Atualiza dimensão do produto em tempo real
     * Nota: Dimensões agora estão diretamente no produto (tabela dimensions foi removida)
     */
    function updateProductDimension(
        layerId: string,
        dimension: 'width' | 'height' | 'depth',
        value: number,
        onSaved?: () => void | Promise<void>,
    ) {
        const found = findSegmentByLayerId(layerId);

        if (!found || !found.segment.layer?.product) {
return null;
}

        const product = found.segment.layer.product;

        if (!product) {
return null;
}

        // Atualiza o campo diretamente no produto
        const updatedProduct = {
            ...product,
            [dimension]: value,
        };

        // Busca dimensões existentes no pendingChanges para fazer merge correto
        const changeKey = `product_${product.id}`;
        const existingChange = changes.getPendingChange(changeKey);
        const existingDimensions = existingChange?.data?.product_dimension || {};

        return commitOptimistic({
            apply: () => {
                updateSegmentReactive(
                    found.section,
                    found.shelfIndex,
                    found.segmentIndex,
                    {
                        product: updatedProduct,
                    },
                );

                return product;
            },
            change: {
                type: 'product_update',
                entityType: 'product',
                entityId: product.id,
                data: {
                    product_dimension: {
                        ...existingDimensions,
                        [dimension]: value,
                    },
                },
            },
            onSaved,
        });
    }

    function updateProductDimensionDirectly(
        product: Product,
        dimension: 'width' | 'height' | 'depth',
        value: number,
        onSaved?: () => void | Promise<void>,
    ) { 
        if (!product) {
return null;
}

        // Atualiza o campo diretamente no produto
        // product[dimension] = value;
        const updatedProduct = {
            ...product,
            [dimension]: value,
        };

        // Busca dimensões existentes no pendingChanges para fazer merge correto
        const changeKey = `product_${product.id}`;
        const existingChange = changes.getPendingChange(changeKey);
        const existingDimensions = existingChange?.data?.product_dimension || {};

        return commitOptimistic({
            apply: () => {
                
                return updatedProduct;
            },
            change: {
                type: 'product_update',
                entityType: 'product',
                entityId: product.id,
                data: {
                    product_dimension: {
                        ...existingDimensions,
                        [dimension]: value,
                    },
                },
            },
            onSaved,
        });
    }

    /**
     * Atualiza dimensão de múltiplos produtos simultaneamente
     * Útil para produtos da mesma marca/categoria que compartilham dimensões
     */
    function updateMultipleProductsDimensions(
        productIds: string[],
        dimension: 'width' | 'height' | 'depth',
        value: number,
        onSaved?: () => void | Promise<void>,
    ) {
        if (!productIds.length) {
return [];
}

        const results: any[] = [];
        let callbackAdded = false;

        // Atualiza cada produto encontrado no canvas
        for (const productId of productIds) {
            // Busca o layer deste produto no gondola
            const gondola = currentGondola.value;

            if (!gondola?.sections) {
continue;
}

            let layerId: string | null = null;

            // Busca o layer pelo product_id
            outerLoop: for (const section of gondola.sections) {
                if (!section.shelves) {
continue;
}

                for (const shelf of section.shelves) {
                    if (!shelf.segments) {
continue;
}

                    for (const segment of shelf.segments) {
                        if (segment.layer?.product?.id === productId) {
                            layerId = segment.layer.id;
                            break outerLoop;
                        }
                    }
                }
            }

            // Se encontrou o layer, atualiza a dimensão
            if (layerId) {
                // Adiciona callback apenas na última atualização
                const isLast = productId === productIds[productIds.length - 1];
                const result = updateProductDimension(
                    layerId,
                    dimension,
                    value,
                    isLast && !callbackAdded ? onSaved : undefined,
                );

                if (result) {
                    results.push(result);

                    if (isLast) {
callbackAdded = true;
}
                }
            }
        }
 
        return results;
    }
    function updateMultipleProductsDimensionsDirectly(
        products: Product[],
        dimension: 'width' | 'height' | 'depth',
        value: number,
        onSaved?: () => void | Promise<void>,
    ) {
        if (!products.length) {
return [];
}

        const results: any[] = [];

        // Atualiza cada produto encontrado no canvas
        for (const product of products) {
            // Se encontrou o layer, atualiza a dimensão
            const result = updateProductDimensionDirectly(
                product,
                dimension,
                value,
                onSaved,
            );

            if (result) {
                results.push(result);
            }
        }

        return results;
    }
    // ========================================================================
    // MÉTODOS DE ESCALA
    // ========================================================================

    // Salva zoom no localStorage
    function saveScaleToLocalStorage() {
        if (isBrowser && currentGondola.value?.id) {
            window.localStorage.setItem(
                `gondola_${currentGondola.value.id}_scale`,
                scaleFactor.value.toString(),
            );
        }
    }
    function setScale(value: number) {
        if (value >= 1 && value <= 10 && currentGondola.value?.id) {
            commitOptimistic({
                apply: () => {
                    scaleFactor.value = Math.max(1, Math.min(10, value));
                    saveScaleToLocalStorage();

                    return scaleFactor.value;
                },
                historySnapshot: {
                    type: 'gondola_scale',
                    description: `Alterar zoom para ${value.toFixed(1)}x`,
                    beforeState: null,
                    afterState: null,
                },
                change: {
                    type: 'gondola_scale',
                    entityType: 'gondola',
                    entityId: currentGondola.value.id,
                    data: { scale_factor: scaleFactor.value },
                },
            });
        }
    }

    function increaseScale() {
        setScale(scaleFactor.value + 0.5);
    }

    function decreaseScale() {
        setScale(scaleFactor.value - 0.5);
    }

    // ==================== AÇÕES DO TOOLBAR ====================

    /**
     * Exibe/oculta a grade de alinhamento no canvas
     */
    function toggleGrid() {
        showGrid.value = !showGrid.value;
    }

    // ==================== ALIGNMENT FUNCTIONS ====================

    /**
     * Define o alinhamento da gôndola
     */
    function setAlignment(alignment: 'left' | 'right' | 'center' | 'justify') {
        if (!currentGondola.value?.id) {
return false;
}

        const result = commitOptimistic({
            apply: () => {
                currentGondola.value!.alignment = alignment;

                return true;
            },
            historySnapshot: {
                type: 'gondola_alignment',
                description: `Alterar alinhamento para ${alignment}`,
                beforeState: null,
                afterState: null,
            },
            change: {
                type: 'gondola_alignment',
                entityType: 'gondola',
                entityId: currentGondola.value.id,
                data: { alignment },
            },
        });

        return result !== null;
    }

    function alignLeft() {
        return setAlignment('left');
    }

    function alignRight() {
        return setAlignment('right');
    }

    function alignCenter() {
        return setAlignment('center');
    }

    function alignJustify() {
        return setAlignment('justify');
    }

    // ==================== FLOW DIRECTION FUNCTIONS ====================

    /**
     * Define a direção do fluxo da gôndola (onde começar a colocar produtos premium)
     */
    function setFlow(flow: 'left_to_right' | 'right_to_left') {
        if (!currentGondola.value?.id) {
return false;
}

        const flowLabel = flow === 'left_to_right' ? 'Esquerda → Direita' : 'Direita → Esquerda';

        const result = commitOptimistic({
            apply: () => {
                currentGondola.value!.flow = flow;

                return true;
            },
            historySnapshot: {
                type: 'gondola_flow',
                description: `Alterar direção para ${flowLabel}`,
                beforeState: null,
                afterState: null,
            },
            change: {
                type: 'gondola_flow',
                entityType: 'gondola',
                entityId: currentGondola.value.id,
                data: { flow },
            },
        });

        return result !== null;
    }

    /**
     * Inverte a direção do fluxo da gôndola
     */
    function toggleFlow() {
        if (!currentGondola.value?.id) {
return false;
}

        const currentFlow = currentGondola.value.flow || 'left_to_right';
        const newFlow =
            currentFlow === 'left_to_right' ? 'right_to_left' : 'left_to_right';

        return setFlow(newFlow);
    }

    // ==================== DEPRECATED - MANTER POR COMPATIBILIDADE COM VERSÃO ANTIGA ====================
    // Estas funções são mantidas apenas para compatibilidade com componentes da versão antiga (Editor.vue)
    // Não devem ser usadas em novos componentes. Use as funções modernas correspondentes.

    /**
     * @deprecated Use setAlignment('left') ou alignLeft()
     * Mantida apenas por compatibilidade com Editor.vue (versão antiga)
     */
    function alignHorizontal() {
        console.warn(
            '⚠️ alignHorizontal() deprecated - use alignLeft/Right/Center',
        );

        return alignLeft();
    }

    /**
     * @deprecated Use setAlignment('right') ou alignRight()
     * Mantida apenas por compatibilidade com Editor.vue (versão antiga)
     */
    function alignVertical() {
        console.warn(
            '⚠️ alignVertical() deprecated - use setAlignment() ou alignRight()',
        );

        return alignRight();
    }

    /**
     * @deprecated Use setAlignment('justify') ou alignJustify()
     * Mantida apenas por compatibilidade com Editor.vue (versão antiga)
     */
    function distribute() {
        console.warn(
            '⚠️ distribute() deprecated - use setAlignment("justify") ou alignJustify()',
        );

        return alignJustify();
    }

    /**
     * Abre drawer para adicionar novo módulo
     */
    function addModule() {
        if (!currentGondola.value?.id) {
            console.warn('Nenhuma gôndola selecionada para adicionar módulo');

            return;
        }

        showAddModuleDrawer.value = true;
    }

    /**
     * Callback executado após módulo ser adicionado com sucesso
     * O backend retorna back() que já recarrega automaticamente via Inertia
     */
    function handleModuleAdded(_section: any) {
        void _section;
        showAddModuleDrawer.value = false;
        // Não precisa fazer reload aqui - o back() do backend já faz isso automaticamente
    }

    /**
     * Estado da modal de confirmação de remoção
     */
    const showDeleteConfirmation = ref(false);

    /**
     * Estado do drawer de adicionar módulo
     */
    const showAddModuleDrawer = ref(false);

    /**
     * Abre modal de confirmação para remover gôndola
     */
    function removeGondola() {
        if (!currentGondola.value?.id) {
            console.warn('Nenhuma gôndola selecionada para remover');

            return;
        }

        showDeleteConfirmation.value = true;
    }

    /**
     * Confirma e executa a remoção da gôndola
     */
    function confirmRemoveGondola() {
        if (!currentGondola.value?.id) {
            return;
        }

        // Usando router do Inertia para fazer DELETE request

        router.delete(`/api/editor/gondolas/${currentGondola.value.id}`, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                showDeleteConfirmation.value = false;
            },
            onError: (errors) => {
                console.error('Erro ao remover gôndola:', errors);
                showDeleteConfirmation.value = false;
            },
        });
    }

    /**
     * Aplica um snapshot ao estado atual da gôndola E registra para persistência
     */
    function applySnapshot(snapshot: any, shouldPersist: boolean = true) {
        if (!currentGondola.value || !snapshot) {
            console.error('❌ Não foi possível aplicar snapshot: gôndola ou snapshot inválidos');

            return false;
        }

        try {
            // Aplica o estado baseado no tipo de ação
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

    /**
     * Aplica snapshot de shelf (posição ou transfer)
     */
    function applyShelfSnapshot(snapshot: any, shouldPersist: boolean) {
        const currentShelfData = findShelfById(snapshot.shelfId);

        if (!currentShelfData) {
            console.error('❌ Shelf não encontrada:', snapshot.shelfId);

            return;
        }

        const { beforeState } = snapshot;
        const currentSectionId = currentShelfData.shelf.section_id;
        const targetSectionId = beforeState.section_id;
 

        // Verifica se é uma transferência entre seções
        if (currentSectionId !== targetSectionId) {
            // É uma transferência - precisa mover a shelf de volta
            const currentSection = findSectionById(currentSectionId);
            const targetSection = findSectionById(targetSectionId);
            
            if (!currentSection || !targetSection) {
                console.error('❌ Seções não encontradas');

                return;
            }
 

            // Remove da seção atual
            const shelfIndex = currentSection.shelves?.findIndex(
                (s: any) => s.id === snapshot.shelfId
            ) ?? -1;
            
            if (shelfIndex !== -1 && currentSection.shelves) {
                const shelf = currentSection.shelves[shelfIndex];
                currentSection.shelves.splice(shelfIndex, 1);
                currentSection.shelves = [...currentSection.shelves];

                // Atualiza propriedades da shelf
                Object.assign(shelf, beforeState);

                // Adiciona na seção de destino
                if (!targetSection.shelves) {
targetSection.shelves = [];
}

                targetSection.shelves.push(shelf);
                targetSection.shelves = [...targetSection.shelves];

                // Força reatividade global
                if (currentGondola.value?.sections) {
                    currentGondola.value.sections = [...currentGondola.value.sections];
                }
            }
        } else {
            // É apenas mudança de posição na mesma seção
            Object.assign(currentShelfData.shelf, beforeState);

            // Força reatividade
            const section = findSectionById(currentSectionId);

            if (section && currentGondola.value?.sections) {
                section.shelves = [...(section.shelves || [])];
                
                // Encontra o índice da seção para updateSectionReactive
                const sectionIndex = currentGondola.value.sections.findIndex(s => s.id === currentSectionId);

                if (sectionIndex !== -1) {
                    updateSectionReactive(sectionIndex, {});
                }
            }
        }

        // Registra para persistência
        if (shouldPersist) {
            // Usa a mesma lógica de detecção: verifica se mudou de seção
            const isTransfer = currentSectionId !== targetSectionId;
            
            if (isTransfer) {
                // shelf_transfer: move entre seções
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
                // shelf_move: apenas mudança de posição na mesma seção
                recordChange({
                    type: 'shelf_move',
                    entityType: 'shelf',
                    entityId: snapshot.shelfId,
                    data: {
                        shelf_position: beforeState.shelf_position,
                    },
                });
            }
        }
    }

    /**
     * Aplica snapshot de segment
     */
    function applySegmentSnapshot(snapshot: any, shouldPersist: boolean) {
        const found = findSegmentById(snapshot.segmentId);

        if (!found) {
            console.error('❌ Segment não encontrado:', snapshot.segmentId);

            return;
        }

        // Aplica as mudanças do estado
        Object.assign(found.segment, snapshot.beforeState);

        // Força reatividade
        updateSegmentReactive(
            found.section,
            found.shelfIndex,
            found.segmentIndex,
            {},
        );

        // Registra para persistência
        if (shouldPersist) {
            recordChange({
                type: 'segment_update',
                entityType: 'segment',
                entityId: snapshot.segmentId,
                data: snapshot.beforeState,
            });
        }
    }

    /**
     * Aplica snapshot de section
     */
    function applySectionSnapshot(snapshot: any, shouldPersist: boolean) {
        const section = findSectionById(snapshot.sectionId);

        if (!section || !currentGondola.value?.sections) {
            console.error('❌ Section não encontrada:', snapshot.sectionId);

            return;
        }

        // Força reatividade aplicando as mudanças através do updateSectionReactive
        const sectionIndex = currentGondola.value.sections.findIndex(s => s.id === snapshot.sectionId);

        if (sectionIndex !== -1) {
            updateSectionReactive(sectionIndex, snapshot.beforeState);
        }

        // Registra para persistência
        if (shouldPersist) {
            // Filtra apenas campos permitidos pelo backend
            const allowedFields = [
                'name', 'code', 'width', 'height', 'num_shelves',
                'base_height', 'base_depth', 'base_width', 'cremalheira_width',
                'hole_height', 'hole_width', 'hole_spacing',
                'ordering', 'alignment'
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

    /**
     * Aplica snapshot de reordenação de múltiplas seções
     */
    function applySectionsReorderSnapshot(snapshot: any, shouldPersist: boolean) {
        if (!currentGondola.value?.sections || !snapshot.sectionIds || !snapshot.beforeState) {
            console.error('❌ Dados inválidos para reordenação de seções');

            return;
        }

        const beforeOrderings = snapshot.beforeState as Record<string, number>;

        // Aplica os orderings anteriores a cada seção e força reatividade
        snapshot.sectionIds.forEach((sectionId: string) => {
            const sectionIndex = currentGondola.value?.sections?.findIndex(
                (s: any) => s.id === sectionId
            ) ?? -1;
            
            if (sectionIndex !== -1 && beforeOrderings[sectionId] !== undefined) {
                // Usa updateSectionReactive para garantir reatividade
                updateSectionReactive(sectionIndex, {
                    ordering: beforeOrderings[sectionId]
                });
            }
        });

        // Registra para persistência
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

    /**
     * Aplica snapshot de layer
     */
    function applyLayerSnapshot(snapshot: any, shouldPersist: boolean) {
        const found = findSegmentByLayerId(snapshot.layerId);

        if (!found || !found.segment.layer) {
            console.error('❌ Layer não encontrado:', snapshot.layerId);

            return;
        }

        // Aplica as mudanças do estado
        Object.assign(found.segment.layer, snapshot.beforeState);

        // Força reatividade
        updateSegmentReactive(
            found.section,
            found.shelfIndex,
            found.segmentIndex,
            {},
        );

        // Registra para persistência
        if (shouldPersist) {
            recordChange({
                type: 'layer_update',
                entityType: 'layer',
                entityId: snapshot.layerId,
                data: snapshot.beforeState,
            });
        }
    }

    /**
     * Aplica snapshot de produto
     */
    function applyProductSnapshot(snapshot: any, shouldPersist: boolean) {
        // Busca o produto no canvas
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
                        // Aplica as mudanças do estado
                        Object.assign(segment.layer?.product as any, snapshot.beforeState);

                        // Força reatividade
                        const found = findSegmentById(segment.id);

                        if (found) {
                            updateSegmentReactive(
                                found.section,
                                found.shelfIndex,
                                found.segmentIndex,
                                {},
                            );
                        }

                        // Registra para persistência
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

    /**
     * Aplica snapshot de transferência de segmento (mover entre prateleiras)
     */
    function applySegmentTransferSnapshot(snapshot: any, shouldPersist: boolean) {
        if (!currentGondola.value) {
return;
}
        
        const { sourceShelfId, targetShelfId, sourceShelfSegments, targetShelfSegments } = snapshot.beforeState;
        
        // Busca as prateleiras ATUAIS (onde o segmento está agora)
        const currentTargetShelf = findShelfById(targetShelfId);
        const currentSourceShelf = findShelfById(sourceShelfId);
        
        if (!currentTargetShelf || !currentSourceShelf) {
            console.error('❌ Prateleiras não encontradas para undo de transfer');

            return;
        }

        // Restaura os arrays de segmentos de AMBAS as prateleiras
        // Isso move o segmento de volta para a prateleira de origem
        currentSourceShelf.shelf.segments = history.cloneState(sourceShelfSegments);
        currentTargetShelf.shelf.segments = history.cloneState(targetShelfSegments);

        // Atualiza section_id dos segmentos se necessário
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

        // Força reatividade nas shelves
        currentSourceShelf.shelf.segments = [...currentSourceShelf.shelf.segments];
        currentTargetShelf.shelf.segments = [...currentTargetShelf.shelf.segments];

        // Força reatividade nas sections
        currentSourceShelf.section.shelves = [...(currentSourceShelf.section.shelves || [])];
        currentTargetShelf.section.shelves = [...(currentTargetShelf.section.shelves || [])];

        // Força reatividade global
        if (currentGondola.value.sections) {
            currentGondola.value.sections = [...currentGondola.value.sections];
        }

        // Registra para persistência (restaura os arrays completos)
        if (shouldPersist) {
            // Registra mudanças para todos os segmentos envolvidos
            sourceShelfSegments.forEach((seg: any) => {
                recordChange({
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
                recordChange({
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

    /**
     * Aplica snapshot de cópia de segmento (remove o produto copiado)
     */
    function applySegmentCopySnapshot(snapshot: any, shouldPersist: boolean) {
        if (!currentGondola.value) {
return;
}
        
        const { targetShelfId, targetShelfSegments } = snapshot.beforeState;
        
        // Busca a prateleira de destino
        const targetShelfData = findShelfById(targetShelfId);
        
        if (!targetShelfData) {
            console.error('❌ Prateleira não encontrada para undo de copy');

            return;
        }

        // Restaura os segmentos da prateleira (remove o copiado)
        targetShelfData.shelf.segments = history.cloneState(targetShelfSegments);

        // Atualiza shelf_id dos segmentos
        targetShelfData.shelf.segments.forEach((seg: any) => {
            if (seg.shelf_id !== targetShelfId) {
                seg.shelf_id = targetShelfId;
            }
        });

        // Força reatividade
        targetShelfData.shelf.segments = [...targetShelfData.shelf.segments];
        targetShelfData.section.shelves = [...(targetShelfData.section.shelves || [])];
        
        if (currentGondola.value.sections) {
            currentGondola.value.sections = [...currentGondola.value.sections];
        }

        // Registra para persistência
        if (shouldPersist) {
            // Registra mudanças para todos os segmentos da prateleira de destino
            targetShelfSegments.forEach((seg: any) => {
                recordChange({
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

    /**
     * Aplica snapshot de gôndola
     */
    function applyGondolaSnapshot(snapshot: any, shouldPersist: boolean) {
        if (!currentGondola.value) {
return;
}

        const gondolaId = currentGondola.value.id; // Salva ID antes de aplicar mudanças

        // Aplica as mudanças do estado
        Object.assign(currentGondola.value, snapshot.beforeState);

        // Força reatividade
        currentGondola.value = { ...currentGondola.value };

        // Registra para persistência
        if (shouldPersist && gondolaId) {
            recordChange({
                type: snapshot.type,
                entityType: 'gondola',
                entityId: gondolaId,
                data: snapshot.beforeState,
            });
        }
    }

    /**
     * Desfaz a última ação
     */
    function undo() {
        const snapshot = history.undoAction();

        if (snapshot) {
            // Aplica o estado ANTES da ação (desfaz) E registra para persistência
            applySnapshot(snapshot, true);
        }
    }

    /**
     * Refaz a última ação desfeita
     */
    function redo() {
        const snapshot = history.redoAction();

        if (snapshot) {
            // Aplica o estado DEPOIS da ação (refaz) E registra para persistência
            const redoSnapshot = { ...snapshot, beforeState: snapshot.afterState };
            applySnapshot(redoSnapshot, true);
        }
    }

    /**
     * Limpa todo o histórico de undo/redo
     */
    function clearHistory() {
        history.clearHistory();
    }

    /**
     * Debug: Mostra estado atual do histórico
     */
    function debugHistory() {
        // Estado do histórico disponível via history.canUndo, history.canRedo, history.historyStack, history.getRecentActions()
    }

    /**
     * Salva as mudanças pendentes manualmente
     *
     * - Valida se há gôndola selecionada
     * - Valida se há mudanças para salvar
     * - Usa rota configurada via setSaveChangesRoute()
     * - Se não houver rota, apenas loga erro
     *
     * @returns Promise<boolean> - true se salvou com sucesso
     */
    async function save() {
        if (!currentGondola.value?.id) {
            console.error('❌ Nenhuma gôndola selecionada');

            return false;
        }

        if (!changes.hasChanges.value) {
            return false;
        }

        // Salva mudanças usando o contexto configurado
        // A rota foi configurada via setSaveChangesRoute()
        const success = await changes.saveChanges(currentGondola.value.id);

        if (!success) {
            console.error('❌ Erro ao salvar mudanças');
        }

        return success;
    }

    /**
     * Abre modal/painel de performance
     */
    function showPerformance() {
        showPerformanceModal.value = true;
    }

    /**
     * Imprime o planograma atual
     */
    function print() {
        if (!currentGondola.value?.id) {
            return;
        }

        const route = gondolaView(currentGondola.value.id);
        window.open(route.url, '_blank');
    }

    /**
     * Abre modal/painel de relatórios
     */
    function showReports() {
        if (!currentGondola.value?.id) {
            return;
        }

        const route = calculateAbc(currentGondola.value.id);
        window.open(route.url, '_blank');
    }
    /**
     * Converte cores oklch para rgb (html2canvas não suporta oklch)
     * (Currently unused - left for potential future use)
     */
    /*
    function _convertOklchToRgb(element: HTMLElement): HTMLElement {
        const clone = element.cloneNode(true) as HTMLElement;

        // Função para converter oklch para rgb usando getComputedStyle
        function processElement(el: HTMLElement) {
            const computed = window.getComputedStyle(el);

            // Lista de propriedades CSS que podem ter cores
            const colorProps = [
                'color',
                'background-color',
                'border-color',
                'border-top-color',
                'border-right-color',
                'border-bottom-color',
                'border-left-color',
                'outline-color',
                'text-decoration-color',
                'fill',
                'stroke',
            ];

            colorProps.forEach((prop) => {
                const value = computed.getPropertyValue(prop);
                if (
                    value &&
                    value !== 'rgba(0, 0, 0, 0)' &&
                    value !== 'transparent' &&
                    value !== 'none'
                ) {
                    // Aplica a cor computada (já convertida pelo navegador)
                    el.style.setProperty(prop, value, 'important');
                }
            });

            // Remove classes do Tailwind que podem ter oklch
            if (el.hasAttribute('class')) {
                const classes = el.className;
                // Preserva as classes mas força as cores inline
                el.setAttribute('data-original-class', classes);
            }

            // Processa filhos recursivamente
            Array.from(el.children).forEach((child) => {
                if (child instanceof HTMLElement) {
                    processElement(child);
                }
            });
        }

        processElement(clone);
        return clone;
    }
    */

    /**
     * Exporta o planograma como imagem PNG
     */
    async function exportAsImage() {
        try {
            // Importa html2canvas dinamicamente
            const html2canvas = (await import('html2canvas')).default;

            // Busca a div que contém as seções
            const element = document.querySelector(
                '[data-planogram-canvas]',
            ) as HTMLElement;

            if (!element) {
                console.error('❌ Elemento do canvas não encontrado');

                return;
            }

            // Cria um wrapper temporário
            const wrapper = document.createElement('div');
            wrapper.style.position = 'absolute';
            wrapper.style.left = '-9999px';
            wrapper.style.top = '0';
            wrapper.style.width = element.offsetWidth + 'px';
            wrapper.style.height = element.offsetHeight + 'px';

            // Clona o elemento
            const clone = element.cloneNode(true) as HTMLElement;

            // Cria uma folha de estilo inline para sobrescrever oklch
            const style = document.createElement('style');
            style.textContent = `
                * {
                    color: inherit !important;
                    background-color: inherit !important;
                    border-color: inherit !important;
                }
            `;

            wrapper.appendChild(style);
            wrapper.appendChild(clone);
            document.body.appendChild(wrapper);

            // Processa todos os elementos recursivamente
            function applyComputedStyles(original: Element, cloned: Element) {
                if (!(original instanceof HTMLElement) || !(cloned instanceof HTMLElement)) {
                    return;
                }

                const computed = window.getComputedStyle(original);

                // Aplica apenas as cores computadas
                const colorProps = [
                    'color',
                    'background-color',
                    'border-top-color',
                    'border-right-color',
                    'border-bottom-color',
                    'border-left-color',
                ];

                colorProps.forEach((prop) => {
                    const value = computed.getPropertyValue(prop);

                    if (value && value !== 'rgba(0, 0, 0, 0)' && value !== 'transparent') {
                        cloned.style.setProperty(prop, value, 'important');
                    }
                });

                // Processa filhos
                const originalChildren = Array.from(original.children);
                const clonedChildren = Array.from(cloned.children);

                originalChildren.forEach((origChild, index) => {
                    if (clonedChildren[index]) {
                        applyComputedStyles(origChild, clonedChildren[index]);
                    }
                });
            }

            // Aplica estilos computados
            applyComputedStyles(element, clone);

            try {
                // Aguarda renderização
                await new Promise((resolve) => setTimeout(resolve, 100));

                // Gera o canvas a partir do clone
                const canvas = await html2canvas(clone, {
                    backgroundColor: '#ffffff',
                    scale: 2,
                    logging: true,
                    useCORS: true,
                    allowTaint: false,
                });

                // Remove o wrapper
                document.body.removeChild(wrapper);

                // Converte para blob e faz download
                canvas.toBlob((blob) => {
                    if (!blob) {
                        console.error('❌ Erro ao gerar blob da imagem');

                        return;
                    }

                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    const gondolaName =
                        currentGondola.value?.name || 'planograma';
                    const timestamp = new Date().toISOString().split('T')[0];

                    link.href = url;
                    link.download = `${gondolaName}-${timestamp}.png`;
                    link.click();

                    URL.revokeObjectURL(url);
                });
            } catch (canvasError) {
                if (wrapper.parentNode) {
                    document.body.removeChild(wrapper);
                }

                throw canvasError;
            }
        } catch (error) {
            console.error('❌ Erro ao exportar imagem:', error);
        }
    }

    function setSaveChangesRoute(route: string) {
        if (currentGondola.value?.id) {
            changes.setAutoSaveContext(currentGondola.value.id, route);
        }
    }
    // ========================================================================
    // RETURN - EXPÕE API DO COMPOSABLE
    // ========================================================================

    return {
        // Estado (readonly para prevenir mutações diretas)
        currentGondola: currentGondola,
        scaleFactor,
        showProductsPanel,
        showPropertiesPanel,
        selectedType,
        selectedId,
        selectedItem,
        showDeleteConfirmation,
        showAddModuleDrawer,

        // Computed
        sectionsOrdered,

        // Histórico
        canUndo: history.canUndo,
        canRedo: history.canRedo,

        // Mudanças/Salvamento
        hasChanges: changes.hasChanges,
        changeCount: changes.changeCount,
        isSaving: changes.isSaving,

        // Métodos de inicialização
        initializeEditor,
        updateGondola,
        gondolasAvailable,

        // Métodos de operações
        addShelf,
        addSection,
        addProductToShelf,
        moveSegmentToShelf,
        copySegmentToShelf,
        swapSegmentPositions,
        moveShelfWithinSection,
        moveShelfToSection,
        reorderSectionsByOrdering,
        swapSectionsOrdering,
        invertShelvesOrder,
        updateShelf,
        updateSection,
        updateSegment,
        invertSegmentsOrder,
        updateLayer,
        updateProductDimension,
        updateProductDimensionDirectly,
        updateMultipleProductsDimensions,
        updateMultipleProductsDimensionsDirectly,
        recordChange,
        findSegmentById,
        findShelfById,
        findSectionById,
        findSegmentByLayerId,

        // Métodos de escala
        setScale,
        increaseScale,
        decreaseScale,

        // Ações do Toolbar
        showGrid,
        toggleGrid,

        // Alinhamento da gondola
        setAlignment,
        alignLeft,
        alignRight,
        alignCenter,
        alignJustify,

        // Flow da gondola
        setFlow,
        toggleFlow,

        // Deprecated (manter por compatibilidade com versão antiga)
        alignHorizontal,
        alignVertical,
        distribute,

        // Outras ações
        addModule,
        handleModuleAdded,
        removeGondola,
        confirmRemoveGondola,
        undo,
        redo,
        clearHistory,
        save,
        showPerformance,
        print,
        showReports,
        exportAsImage,
        setSaveChangesRoute,
        
        // Debug
        debugHistory,
    };
}
