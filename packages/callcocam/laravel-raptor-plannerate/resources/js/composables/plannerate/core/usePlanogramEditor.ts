// ============================================================================
// IMPORTS
// ============================================================================

import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { calculateAbc } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaAnalysisController';
import { show as gondolaView } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaPdfPreviewController';
import { usePlanogramChanges } from './usePlanogramChanges';
import { usePlanogramHistory } from './usePlanogramHistory';
import { findNearestHole } from '../geometry/useSectionHoles';
import { useT } from '@/composables/useT';
import type { Gondola, Product, Section, Shelf } from '@/types/planogram';

import {
    commitGondola,
    currentGondola,
    isLoadingRejectedProducts,
    rejectedProducts,
    scaleFactor,
    showGrid,
    showPerformanceModal,
    showProductsPanel,
    showPropertiesPanel,
    showZoneIndicators,
} from './useGondolaState';
import {
    findSectionById,
    findSegmentById,
    findSegmentByLayerId,
    findShelfById,
} from './useLookupHelpers';
import {
    updateSectionReactive,
    updateSegmentReactive,
} from './useReactivityHelpers';
import { useRejectedProductsModule } from '../interactions/useRejectedProductsModule';
import { useSectionOperations } from '../operations/useSectionOperations';
import {
    copySegmentToShelf as copySegmentOperation,
    moveSegmentToShelf as moveSegmentOperation,
    swapSegmentPositions as swapSegmentOp,
} from '../operations/useSegmentOperations';
import { useShelfOperations } from '../operations/useShelfOperations';
import { useSnapshotManager } from '../operations/useSnapshotManager';
import { captureElementAsCanvas } from '../export/useCanvasCapture';

// ============================================================================
// COMPOSABLE
// ============================================================================

const isBrowser = typeof window !== 'undefined';

/**
 * Guard de módulo: o watch de auto-refresh de rejeitados deve ser registrado
 * UMA única vez, não a cada chamada de usePlanogramEditor(). Como o composable é
 * chamado em ~220 Segments + Shelves + outros componentes (direta ou
 * indiretamente via usePlanogramSelection), sem este guard havia centenas de
 * watchers duplicados — cada save com remoções disparava N fetchRejectedProducts.
 */
let rejectedRefreshWatcherInstalled = false;

export function usePlanogramEditor() {
    const { t } = useT();
    const history = usePlanogramHistory();
    const changes = usePlanogramChanges();
    const sectionOps = useSectionOperations();
    const shelfOps = useShelfOperations();

    // Módulo de snapshots: commit otimista + aplicação (undo/redo)
    const snapshots = useSnapshotManager(history, changes);
    const { commitOptimistic, recordChange, applySnapshot } = snapshots;

    // Módulo de produtos rejeitados
    const rejectedOps = useRejectedProductsModule(
        commitOptimistic,
        recordChange,
        history,
        shelfOps.addProductToShelf,
    );

    // ========================================================================
    // AUTO-REFRESH DE PRODUTOS REJEITADOS PÓS-SAVE
    // ========================================================================

    /**
     * Sempre que o auto-save (ou save manual via usePlanogramChanges) concluir com
     * sucesso E o save contiver remoções de produto/segmento/camada, recarrega a
     * lista de produtos rejeitados automaticamente — sem precisar de F5.
     *
     * `lastSavedAt` é atualizado pelo singleton de usePlanogramChanges após cada
     * save bem-sucedido; `lastSaveHadRemovals` indica se havia remoções naquele
     * batch, e é zerado no próximo save que não contenha remoções.
     */
    if (!rejectedRefreshWatcherInstalled) {
        rejectedRefreshWatcherInstalled = true;

        watch(
            () => changes.lastSavedAt.value,
            (savedAt) => {
                if (!savedAt) return;
                if (!changes.lastSaveHadRemovals.value) return;
                if (!currentGondola.value?.id) return;

                void rejectedOps.fetchRejectedProducts(currentGondola.value.id);
            },
        );
    }

    // ========================================================================
    // OPERAÇÕES COM SEGMENTOS (delega a useSegmentOperations)
    // ========================================================================

    /**
     * Reordena segment dentro da mesma shelf (swap de posições)
     */
    function swapSegmentPositions(segment1Id: string, segment2Id: string): boolean {
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
    function moveSegmentToShelf(segmentId: string, targetShelfId: string): boolean {
        const found = findSegmentById(segmentId);

        if (!found) {
            return false;
        }

        const targetShelf = findShelfById(targetShelfId);

        if (!targetShelf) {
            return false;
        }

        const beforeState = {
            sourceShelfId: found.shelf.id,
            targetShelfId: targetShelfId,
            segmentId: segmentId,
            sourceShelfSegments: history.cloneState(found.shelf.segments),
            targetShelfSegments: history.cloneState(targetShelf.shelf.segments),
        };

        const result = commitOptimistic({
            apply: () =>
                moveSegmentOperation(
                    segmentId,
                    targetShelfId,
                    recordChange,
                    t('plannerate.editor.product_does_not_fit_destination_shelf'),
                ),
            historySnapshot: {
                type: 'segment_transfer',
                segmentId: segmentId,
                shelfId: found.shelf.id,
                sectionId: found.section.id,
                description: `Mover produto para outra prateleira`,
                beforeState: beforeState,
                afterState: null,
            },
        });

        return result !== null;
    }

    /**
     * Copia um segmento para outra prateleira
     */
    function copySegmentToShelf(segmentId: string, targetShelfId: string): boolean {
        const found = findSegmentById(segmentId);

        if (!found) {
            return false;
        }

        const targetShelf = findShelfById(targetShelfId);

        if (!targetShelf) {
            return false;
        }

        const beforeState = {
            sourceShelfId: found.shelf.id,
            targetShelfId: targetShelfId,
            sourceSegmentId: segmentId,
            targetShelfSegments: history.cloneState(targetShelf.shelf.segments),
        };

        const result = commitOptimistic({
            apply: () =>
                copySegmentOperation(
                    segmentId,
                    targetShelfId,
                    recordChange,
                    t('plannerate.editor.product_does_not_fit_destination_shelf'),
                ),
            historySnapshot: {
                type: 'segment_copy',
                segmentId: segmentId,
                shelfId: targetShelfId,
                sectionId: found.section.id,
                description: `Copiar produto para outra prateleira`,
                beforeState: beforeState,
                afterState: null,
            },
        });

        return result !== null;
    }

    // ========================================================================
    // OPERAÇÕES COM SHELVES (delega a useShelfOperations)
    // ========================================================================

    /**
     * Move shelf dentro da mesma section (drag vertical)
     */
    function moveShelfWithinSection(shelfId: string, newPosition: number): boolean {
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
    function moveShelfToSection(shelfId: string, targetSectionId: string, newPosition: number): boolean {
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
            apply: () => { shelfOps.invertShelvesOrder(sectionId, recordChange); },
        });
    }

    // ========================================================================
    // SANITIZAÇÃO
    // ========================================================================

    /**
     * Sanitiza gondola removendo entidades deleted_at e encaixando prateleiras no furo mais próximo
     */
    function sanitizeGondola(gondola: Gondola): Gondola {
        const sections = (gondola.sections || [])
            .filter((section: any) => !section.deleted_at)
            .map((section: any) => {
                const shelves = (section.shelves || [])
                    .filter((s: any) => !s.deleted_at)
                    .map((shelf: any) => {
                        const snappedPosition = findNearestHole(section, shelf.shelf_position || 0);

                        const segments = (shelf.segments || [])
                            .filter((seg: any) => !seg.deleted_at)
                            .map((seg: any) => ({
                                ...seg,
                                layers: seg.layers?.filter((layer: any) => !layer.deleted_at) || [],
                            }));

                        return { ...shelf, shelf_position: snappedPosition, segments };
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

        const filtered = sections.filter((s: Section) => !s.deleted_at);
        const ordered = [...filtered].sort(
            (a: Section, b: Section) => (a.ordering || 0) - (b.ordering || 0),
        );

        return flow === 'right_to_left' ? ordered.reverse() : ordered;
    });

    // ========================================================================
    // INICIALIZAÇÃO
    // ========================================================================

    /**
     * Inicializa o editor com dados da gondola
     */
    function initializeEditor(gondola: Gondola) {
        currentGondola.value = sanitizeGondola(gondola);

        const savedScale = isBrowser
            ? window.localStorage.getItem(`gondola_${gondola.id}_scale`)
            : null;

        if (savedScale) {
            const scale = parseFloat(savedScale);
            scaleFactor.value = scale >= 1 && scale <= 10 ? scale : gondola.scale_factor || 3;
        } else {
            scaleFactor.value = gondola.scale_factor || 3;
        }

        history.initializeHistory();

        rejectedProducts.value = [];
        isLoadingRejectedProducts.value = false;
    }

    /**
     * Atualiza a gondola mantendo referência reativa via commit otimista
     */
    function updateGondola(updates: Partial<Gondola>) {
        if (!currentGondola.value?.id) {
            return null;
        }

        return commitOptimistic({
            apply: () => {
                currentGondola.value = { ...currentGondola.value!, ...updates };

                if (currentGondola.value.planogram?.gondolas) {
                    const gondolaIndex = currentGondola.value.planogram.gondolas.findIndex(
                        (g) => g.id === currentGondola.value!.id,
                    );

                    if (gondolaIndex !== -1) {
                        currentGondola.value.planogram.gondolas = [
                            ...currentGondola.value.planogram.gondolas.slice(0, gondolaIndex),
                            currentGondola.value,
                            ...currentGondola.value.planogram.gondolas.slice(gondolaIndex + 1),
                        ];
                    }
                }

                commitGondola();

                return currentGondola.value;
            },
            historySnapshot: {
                type: 'gondola_update',
                description: `Atualizar gôndola`,
                beforeState: null,
                afterState: null,
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
    // OPERAÇÕES COM SECTIONS/SHELVES/SEGMENTS/LAYERS
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
                    t('plannerate.editor.product_does_not_fit_selected_shelf'),
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

    function reorderSectionsByOrdering(): void {
        sectionOps.reorderSectionsByOrdering();
    }

    function addSection(sectionData: Partial<Section>) {
        return commitOptimistic({
            apply: () => sectionOps.addSection(sectionData, recordChange),
        });
    }

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
                beforeState: null,
                afterState: null,
            },
        });
    }

    function updateSection(sectionId: string, updates: Partial<any>) {
        return commitOptimistic({
            apply: () =>
                sectionOps.updateSection(sectionId, updates, updateSectionReactive, recordChange),
            historySnapshot: {
                type: 'section_update',
                sectionId: sectionId,
                description: `Atualizar seção`,
                beforeState: null,
                afterState: null,
            },
        });
    }

    /**
     * Troca a ordem de seções capturando TODAS as mudanças em um único snapshot
     */
    function swapSectionsOrdering(sectionIds: string[], newOrderings: Record<string, number>) {
        if (!currentGondola.value?.sections) {
            return;
        }

        return commitOptimistic({
            apply: () => {
                sectionIds.forEach(sectionId => {
                    const section = findSectionById(sectionId);

                    if (section && newOrderings[sectionId] !== undefined) {
                        section.ordering = newOrderings[sectionId];
                    }
                });

                if (currentGondola.value?.sections) {
                    currentGondola.value.sections = [...currentGondola.value.sections];
                }

                commitGondola();

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
                beforeState: null,
                afterState: null,
            },
        });
    }

    function updateSegment(segmentId: string, updates: Partial<any>) {
        const found = findSegmentById(segmentId);

        if (!found) {
            return null;
        }

        return commitOptimistic({
            apply: () => {
                const segment = found.segment;
                Object.assign(segment, updates);

                const updatedSegments = [...found.shelf.segments];
                updatedSegments[found.segmentIndex] = { ...segment };
                found.shelf.segments = updatedSegments;

                const updatedShelves = [...found.section.shelves];
                const shelfIndex = updatedShelves.findIndex((s: any) => s.id === found.shelf.id);

                if (shelfIndex !== -1) {
                    updatedShelves[shelfIndex] = { ...found.shelf, segments: updatedSegments };
                    found.section.shelves = updatedShelves;
                }

                if (currentGondola.value?.sections) {
                    const updatedSections = [...currentGondola.value.sections];
                    const sectionIndex = updatedSections.findIndex((s: any) => s.id === found.section.id);

                    if (sectionIndex !== -1) {
                        updatedSections[sectionIndex] = { ...found.section, shelves: updatedShelves };
                        currentGondola.value.sections = updatedSections;
                    }
                }

                commitGondola();

                return segment;
            },
            historySnapshot: {
                type: 'segment_update',
                segmentId: segmentId,
                sectionId: found.section.id,
                description: `Atualizar produto`,
                beforeState: null,
                afterState: null,
            },
            change: {
                type: 'segment_update',
                entityType: 'segment',
                entityId: segmentId,
                data: updates,
            },
        });
    }

    function invertSegmentsOrder(shelfId: string): void {
        commitOptimistic({
            apply: () => { shelfOps.invertSegmentsOrder(shelfId, recordChange); },
        });
    }

    function updateLayer(layerId: string, updates: Partial<any>) {
        const found = findSegmentByLayerId(layerId);

        if (!found || !found.segment.layer) {
            return null;
        }

        return commitOptimistic({
            apply: () => {
                updateSegmentReactive(found.section, found.shelfIndex, found.segmentIndex, { layer: updates });

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
     * Atualiza dimensão do produto via layer (busca o layer pelo layerId)
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

        const updatedProduct = { ...product, [dimension]: value };

        const changeKey = `product_${product.id}`;
        const existingChange = changes.getPendingChange(changeKey);
        const existingDimensions = existingChange?.data?.product_dimension || {};

        return commitOptimistic({
            apply: () => {
                updateSegmentReactive(found.section, found.shelfIndex, found.segmentIndex, {
                    product: updatedProduct,
                });

                return product;
            },
            change: {
                type: 'product_update',
                entityType: 'product',
                entityId: product.id,
                data: {
                    product_dimension: { ...existingDimensions, [dimension]: value },
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

        const updatedProduct = { ...product, [dimension]: value };

        const changeKey = `product_${product.id}`;
        const existingChange = changes.getPendingChange(changeKey);
        const existingDimensions = existingChange?.data?.product_dimension || {};

        return commitOptimistic({
            apply: () => updatedProduct,
            change: {
                type: 'product_update',
                entityType: 'product',
                entityId: product.id,
                data: {
                    product_dimension: { ...existingDimensions, [dimension]: value },
                },
            },
            onSaved,
        });
    }

    /**
     * Atualiza dimensão de múltiplos produtos simultaneamente (busca por layerId)
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

        for (const productId of productIds) {
            const gondola = currentGondola.value;

            if (!gondola?.sections) {
                continue;
            }

            let layerId: string | null = null;

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

            if (layerId) {
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

        for (const product of products) {
            const result = updateProductDimensionDirectly(product, dimension, value, onSaved);

            if (result) {
                results.push(result);
            }
        }

        return results;
    }

    // ========================================================================
    // ESCALA
    // ========================================================================

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

    function increaseScale() { setScale(scaleFactor.value + 0.5); }
    function decreaseScale() { setScale(scaleFactor.value - 0.5); }

    // ========================================================================
    // TOOLBAR / UI STATE
    // ========================================================================

    function toggleGrid() { showGrid.value = !showGrid.value; }
    function toggleZoneIndicators() { showZoneIndicators.value = !showZoneIndicators.value; }

    // ========================================================================
    // ALINHAMENTO
    // ========================================================================

    function setAlignment(alignment: 'left' | 'right' | 'center' | 'justify') {
        if (!currentGondola.value?.id) {
            return false;
        }

        const result = commitOptimistic({
            apply: () => { currentGondola.value!.alignment = alignment; commitGondola(); return true; },
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

    function alignLeft() { return setAlignment('left'); }
    function alignRight() { return setAlignment('right'); }
    function alignCenter() { return setAlignment('center'); }
    function alignJustify() { return setAlignment('justify'); }

    // ========================================================================
    // FLUXO DA GÔNDOLA
    // ========================================================================

    function setFlow(flow: 'left_to_right' | 'right_to_left') {
        if (!currentGondola.value?.id) {
            return false;
        }

        const flowLabel = flow === 'left_to_right' ? 'Esquerda → Direita' : 'Direita → Esquerda';

        const result = commitOptimistic({
            apply: () => { currentGondola.value!.flow = flow; commitGondola(); return true; },
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

    function toggleFlow() {
        if (!currentGondola.value?.id) {
            return false;
        }

        const currentFlow = currentGondola.value.flow || 'left_to_right';
        const newFlow = currentFlow === 'left_to_right' ? 'right_to_left' : 'left_to_right';

        return setFlow(newFlow);
    }

    // ========================================================================
    // GESTÃO DE MÓDULOS (sections)
    // ========================================================================

    const showDeleteConfirmation = ref(false);
    const showAddModuleDrawer = ref(false);

    function addModule() {
        if (!currentGondola.value?.id) {
            console.warn('Nenhuma gôndola selecionada para adicionar módulo');

            return;
        }

        showAddModuleDrawer.value = true;
    }

    function handleModuleAdded(_section: any) {
        void _section;
        showAddModuleDrawer.value = false;
    }

    function removeGondola() {
        if (!currentGondola.value?.id) {
            console.warn('Nenhuma gôndola selecionada para remover');

            return;
        }

        showDeleteConfirmation.value = true;
    }

    function confirmRemoveGondola() {
        if (!currentGondola.value?.id) {
            return;
        }

        router.delete(`/api/editor/gondolas/${currentGondola.value.id}`, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => { showDeleteConfirmation.value = false; },
            onError: (errors) => {
                console.error('Erro ao remover gôndola:', errors);
                showDeleteConfirmation.value = false;
            },
        });
    }

    // ========================================================================
    // UNDO / REDO / SAVE
    // ========================================================================

    function undo() {
        const snapshot = history.undoAction();

        if (snapshot) {
            applySnapshot(snapshot, true);
        }
    }

    function redo() {
        const snapshot = history.redoAction();

        if (snapshot) {
            applySnapshot({ ...snapshot, beforeState: snapshot.afterState }, true);
        }
    }

    function clearHistory() {
        history.clearHistory();
    }

    async function save() {
        if (!currentGondola.value?.id) {
            console.error('❌ Nenhuma gôndola selecionada');

            return false;
        }

        if (!changes.hasChanges.value) {
            return false;
        }

        const success = await changes.saveChanges(currentGondola.value.id);

        if (!success) {
            console.error('❌ Erro ao salvar mudanças');
        }

        // Nota: o recarregamento de rejectedProducts após remoções é feito automaticamente
        // pelo watch em lastSavedAt/lastSaveHadRemovals, cobrindo tanto saves manuais
        // quanto auto-saves.

        return success;
    }

    function setSaveChangesRoute(route: string) {
        if (currentGondola.value?.id) {
            changes.setAutoSaveContext(currentGondola.value.id, route);
        }
    }

    // ========================================================================
    // AÇÕES DE EXPORTAÇÃO / RELATÓRIOS
    // ========================================================================

    function showPerformance() { showPerformanceModal.value = true; }

    function print() {
        if (!currentGondola.value?.id) {
            return;
        }

        const route = gondolaView(currentGondola.value.id);
        window.open(route.url, '_blank');
    }

    function showReports() {
        if (!currentGondola.value?.id) {
            return;
        }

        const route = calculateAbc(currentGondola.value.id);
        window.open(route.url, '_blank');
    }

    /**
     * Exporta o canvas do planograma como PNG para download.
     * Usa html2canvas-pro (suporte nativo a oklch) via captureElementAsCanvas.
     */
    async function exportAsImage() {
        try {
            const element = document.querySelector('[data-planogram-canvas]') as HTMLElement | null;

            if (!element) {
                console.error('❌ Elemento do canvas não encontrado');

                return;
            }

            const canvas = await captureElementAsCanvas(element, { scale: 2 });

            canvas.toBlob((blob) => {
                if (!blob) {
                    console.error('❌ Erro ao gerar blob da imagem');

                    return;
                }

                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                const gondolaName = currentGondola.value?.name || 'planograma';
                const timestamp = new Date().toISOString().split('T')[0];

                link.href = url;
                link.download = `${gondolaName}-${timestamp}.png`;
                link.click();

                URL.revokeObjectURL(url);
            });
        } catch (error) {
            console.error('❌ Erro ao exportar imagem:', error);
        }
    }

    // ========================================================================
    // RETURN — API PÚBLICA (mantida idêntica à versão anterior)
    // ========================================================================

    return {
        // Estado
        currentGondola,
        scaleFactor,
        showProductsPanel,
        showPropertiesPanel,
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

        // Inicialização
        initializeEditor,
        updateGondola,
        gondolasAvailable,

        // Operações
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

        // Escala
        setScale,
        increaseScale,
        decreaseScale,

        // Toolbar
        showGrid,
        toggleGrid,
        showZoneIndicators,
        toggleZoneIndicators,

        // Alinhamento
        setAlignment,
        alignLeft,
        alignRight,
        alignCenter,
        alignJustify,

        // Fluxo
        setFlow,
        toggleFlow,

        // Módulos
        addModule,
        handleModuleAdded,
        removeGondola,
        confirmRemoveGondola,

        // Undo/Redo/Save
        undo,
        redo,
        clearHistory,
        save,
        showPerformance,
        print,
        showReports,
        exportAsImage,
        setSaveChangesRoute,

        // Produtos rejeitados
        rejectedProducts,
        isLoadingRejectedProducts,
        fetchRejectedProducts: rejectedOps.fetchRejectedProducts,
        removeRejectedProductLocally: rejectedOps.removeRejectedProductLocally,
        deleteRejectedProduct: rejectedOps.deleteRejectedProduct,
        placeFromRejected: rejectedOps.placeFromRejected,
        patchRejectedProductToLastAction: rejectedOps.patchRejectedProductToLastAction,
        swapRejectedProduct: rejectedOps.swapRejectedProduct,
    };
}
