import { ulid } from 'ulid';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { useT } from '@/composables/useT';
import type { Layer, Section, Segment, Shelf } from '@/types/planogram';
import { validateShelfWidth } from '@plannerate/libs/validation';
import { useSectionActions } from '../actions/useSectionActions';
import { segmentsMoving, useSegmentActions } from '../actions/useSegmentActions';
import { useShelfActions } from '../actions/useShelfActions';
import { copiedSegmentId } from '../core/useGondolaState';
import { usePlanogramEditor } from '../core/usePlanogramEditor';
import { usePlanogramSelection } from '../core/usePlanogramSelection';
import { DEFAULT_SECTION_FIELDS, toCamelCase } from '../fields/useSectionFields';
import { calculateHolePositions } from '../geometry/useSectionHoles';
import { shouldShowDeleteConfirm } from '../shared/usePlanogramUtils';
import { useModuleClipboard } from './useModuleClipboard';

// ============================================================================
// ESTADO GLOBAL (SINGLETON PATTERN)
// Refs fora da função = compartilhados entre todas as instâncias
// ============================================================================

// Estado da modal de duplicação de seção
const showDuplicateSectionDialog = ref(false);
const sectionToDuplicate = ref<Section | null>(null);

// Estado da modal de confirmação de exclusão
const showDeleteConfirmDialog = ref(false);
const itemToDelete = ref<{
    type: 'section' | 'shelf' | 'layer';
    item: Section | Shelf | Layer;
} | null>(null);

// Contador de referências para rastrear quantas instâncias estão usando o listener
let keyboardListenerRefCount = 0;
let keyboardHandler: ((event: KeyboardEvent) => void) | null = null;

// Estado para digitação de quantidade (com debounce)
let numberInputBuffer = '';
let numberInputTimer: ReturnType<typeof setTimeout> | null = null;
let currentInputLayerId: string | null = null;
const numberInputDisplay = ref(''); // Para mostrar visualmente ao usuário

/**
 * Handler para entrada de números (digitar quantidade)
 * Acumula dígitos e aplica após 800ms de inatividade
 */
function handleNumberInput(
    digit: string,
    layerId: string,
    buildShelfWidthExceededMessage: (
        totalWidth: number,
        sectionWidth: number,
    ) => string,
) {
    const editor = usePlanogramEditor();
    
    // Se mudou de layer, reseta o buffer
    if (currentInputLayerId !== layerId) {
        numberInputBuffer = '';
        currentInputLayerId = layerId;
    }

    // Adiciona o dígito ao buffer
    numberInputBuffer += digit;
    
    // Atualiza o display visual
    numberInputDisplay.value = numberInputBuffer;

    // Limpa o timer anterior
    if (numberInputTimer) {
        clearTimeout(numberInputTimer);
    }

    // Define novo timer (debounce de 800ms)
    numberInputTimer = setTimeout(() => {
        const quantity = parseInt(numberInputBuffer, 10);
        
        // Valida a quantidade (entre 1 e 999)
        if (quantity >= 1 && quantity <= 999) {
            // Busca dados do segment associado à layer para validação
            const segmentData = editor.findSegmentByLayerId(layerId);

            if (segmentData && segmentData.shelf && segmentData.section && segmentData.segment?.layer?.product?.id) {
                // Valida se a nova quantidade cabe na largura da shelf
                const validation = validateShelfWidth(
                    segmentData.shelf,
                    segmentData.section.width,
                    segmentData.segment.layer.product.id,
                    quantity,
                    null,
                );

                if (!validation.isValid) {
                    toast.error(
                        buildShelfWidthExceededMessage(
                            validation.totalWidth,
                            validation.sectionWidth,
                        ),
                    );
                    // Não permite - excederia a largura da shelf
                    numberInputBuffer = '';
                    currentInputLayerId = null;
                    numberInputTimer = null;
                    numberInputDisplay.value = '';

                    return;
                }
            }

            // Atualiza a quantity da layer
            editor.updateLayer(layerId, { quantity });
        }

        // Reseta o buffer e display
        numberInputBuffer = '';
        currentInputLayerId = null;
        numberInputTimer = null;
        numberInputDisplay.value = '';
    }, 800); // 800ms de debounce
}

/**
 * Composable centralizado para gerenciar atalhos de teclado do planograma
 *
 * Hierarquia de processamento:
 * 1. Handlers específicos por tipo de item (layer, shelf, section)
 * 2. Handlers globais (delete, undo/redo, save)
 *
 * Atalhos implementados:
 * - Layer/Product: Arrow keys para ajustar quantities
 * - Layer/Product: Digitar números 0-9 para definir quantity (com debounce)
 * - Shelf: Ctrl+Arrows para mover posição/seção
 * - Global: Delete, Escape (limpa seleção), Ctrl+Z/Y, Ctrl+S,
 *   Ctrl+C/V (copiar/colar segmento)
 */
export function usePlanogramKeyboard() {
    const { t } = useT();
    const selection = usePlanogramSelection();
    const editor = usePlanogramEditor();
    const moduleClipboard = useModuleClipboard();

    const buildShelfWidthExceededMessage = (
        totalWidth: number,
        sectionWidth: number,
    ): string => {
        return t('plannerate.editor.shelf_width_exceeded', {
            totalWidth: String(totalWidth),
            sectionWidth: String(sectionWidth),
        });
    };

    // ==================== KEYBOARD HANDLERS BY TYPE ====================

    /**
     * Handler de teclado para Layer/Product/Segment selecionado
     * - Ctrl+ArrowLeft/Right: Troca posição do segmento com anterior/próximo
     * - ArrowLeft/Right: Ajusta quantity da layer (facing) - SEM Ctrl
     * - ArrowUp/Down: Ajusta quantity do segment (altura) - SEM Ctrl
     */
    function handleLayerKeyboard(event: KeyboardEvent): boolean {
        const selectedItem = selection.selectedItem.value;

        if (
            !selectedItem ||
            (selectedItem.type !== 'layer' &&
                selectedItem.type !== 'product' &&
                selectedItem.type !== 'segment')
        ) {
            return false;
        }

        // Se for segment, pega a layer dentro dele
        let layerId: string | null = null;
        let segmentId: string | null = null;
        let segment: Segment | null = null;

        if (selectedItem.type === 'segment') {
            segment = selectedItem.item as Segment;
            layerId = segment?.layer?.id || null;
            segmentId = segment?.id || null;
        } else {
            const layer = selectedItem.item as Layer;
            layerId = layer?.id || null;
            segmentId =
                layer?.segment_id || selectedItem.context?.segment?.id || null;

            if (segmentId) {
                const segmentData = editor.findSegmentById(segmentId);
                segment = segmentData?.segment || null;
            }
        }

        // Ctrl+ArrowLeft/Right: Troca posição do segmento
        if (
            (event.ctrlKey || event.metaKey) &&
            (event.key === 'ArrowLeft' || event.key === 'ArrowRight')
        ) {
            if (!segment || !segmentId) {
                return false;
            }

            // Previne execução dupla usando Map global compartilhado
            if (segmentsMoving.get(segmentId)) {
                return true; // Já está em movimento, ignora
            }

            event.preventDefault();
            event.stopPropagation();

            // Usa composable compartilhado para ações de segmento
            const segmentActions = useSegmentActions(
                () => segment!,
                () => {
                    const found = editor.findSegmentById(segmentId!);

                    return found?.shelf;
                },
            );

            if (event.key === 'ArrowLeft') {
                // Troca com o anterior
                if (segmentActions.canMoveLeft.value) {
                    segmentActions.moveLeft();

                    return true;
                }
            } else if (event.key === 'ArrowRight') {
                // Troca com o próximo
                if (segmentActions.canMoveRight.value) {
                    segmentActions.moveRight();

                    return true;
                }
            }

            return false;
        }

        // Ignora se Ctrl estiver pressionado para outras teclas (shelf handlers precisam de Ctrl)
        if (event.ctrlKey || event.metaKey) {
            return false;
        }

        if (!layerId) {
            return false;
        }

        // Handler para digitar quantidade diretamente (números 0-9)
        if (/^[0-9]$/.test(event.key)) {
            event.preventDefault();
            event.stopPropagation();
            handleNumberInput(
                event.key,
                layerId,
                buildShelfWidthExceededMessage,
            );

            return true;
        }

        const handledKeys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'];

        if (!handledKeys.includes(event.key)) {
return false;
}

        event.preventDefault();
        event.stopPropagation();

        // Busca dados atualizados do estado reativo
        const segmentData = editor.findSegmentById(segmentId || '');
        const currentLayerQty = segmentData?.segment?.layer?.quantity || 0;
        const currentSegmentQty = segmentData?.segment?.quantity || 0;
        const layer = segmentData?.segment?.layer;
        const shelf = segmentData?.shelf;
        const section = segmentData?.section;

        switch (event.key) {
            case 'ArrowLeft':
                // Diminui quantity da layer (facing)
                if (currentLayerQty > 1) {
                    const newQty = currentLayerQty - 1;
                    editor.updateLayer(layerId, { quantity: newQty });
                }

                return true;

            case 'ArrowRight': {
                // Aumenta quantity da layer (facing)
                const newLayerQty = currentLayerQty + 1;

                // Valida se a nova quantidade cabe na largura da shelf (apenas se Shift não estiver pressionado)
                if (!event.shiftKey && shelf && section && layer?.product?.id) {
                    const validation = validateShelfWidth(
                        shelf,
                        section.width,
                        layer.product.id,
                        newLayerQty,
                        null,
                    );

                    if (!validation.isValid) {
                        toast.error(
                            buildShelfWidthExceededMessage(
                                validation.totalWidth,
                                validation.sectionWidth,
                            ),
                        );

                        // Não permite aumentar - excederia a largura da shelf
                        return true;
                    }
                }

                editor.updateLayer(layerId, { quantity: newLayerQty });

                return true;
            }

            case 'ArrowDown':
                // Diminui quantity do segment (altura)
                if (segmentId && currentSegmentQty > 1) {
                    const newSegQty = currentSegmentQty - 1;
                    editor.updateSegment(segmentId, { quantity: newSegQty });
                }

                return true;

            case 'ArrowUp':
                // Aumenta quantity do segment (altura)
                if (segmentId) {
                    const newSegQty = currentSegmentQty + 1;
                    editor.updateSegment(segmentId, { quantity: newSegQty });
                }

                return true;
        }

        return false;
    }

    /**
     * Handler de teclado para Shelf selecionada
     * Requer Ctrl/Cmd para evitar conflitos:
     * - Ctrl+ArrowUp: Move shelf para cima (aumenta shelf_position)
     * - Ctrl+ArrowDown: Move shelf para baixo (diminui shelf_position)
     * - Ctrl+ArrowLeft: Move shelf para seção anterior
     * - Ctrl+ArrowRight: Move shelf para próxima seção
     * - Ctrl+D: Duplica a shelf completa (com todos os produtos)
     */
    function handleShelfKeyboard(event: KeyboardEvent): boolean {

        const selectedItem = selection.selectedItem.value;

        if (!selectedItem || selectedItem.type !== 'shelf') {
            return false;
        }

        const shelf = selectedItem.item as Shelf;
        const section = selectedItem.context?.section as Section;

        // Ctrl+I: Inverter ordem dos segments da shelf
        if ((event.ctrlKey || event.metaKey) && (event.key === 'i' || event.key === 'I')) {
            event.preventDefault();
            event.stopPropagation();
            editor.invertSegmentsOrder(shelf?.id || '');

            return true;
        }

        if (!shelf?.id) {
            return false;
        }

        // Se não tiver section no context, busca pela shelf
        let targetSection = section;

        if (!targetSection) {
            const found = editor.findShelfById(shelf.id);
            targetSection = found?.section;
        }

        if (!targetSection?.id) {
            return false;
        }

        // Ctrl+D para duplicar (não precisa ser arrow key)
        if ((event.ctrlKey || event.metaKey) && event.key === 'd') {
            event.preventDefault();
            event.stopPropagation();
            duplicateShelf(shelf, targetSection);

            return true;
        }

        if (!event.ctrlKey && !event.metaKey) {
            // Setas "nuas" com shelf selecionada: consome sem agir, para a
            // página não rolar no meio da edição (mover exige Ctrl/Cmd).
            // PageUp/PageDown/espaço continuam rolando normalmente.
            if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
                event.preventDefault();

                return true;
            }

            return false;
        } // Requer Ctrl/Cmd

        const handledKeys = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'];

        if (!handledKeys.includes(event.key)) {
return false;
}

        event.preventDefault();
        event.stopPropagation();

        // Usa useShelfActions para toda a lógica de movimento (furos, seções adjacentes)
        const { moveUp, moveDown, moveLeft, moveRight } = useShelfActions(
            () => shelf,
            () => targetSection,
        );

        switch (event.key) {
            case 'ArrowUp':
                // Move shelf para cima encaixando nos furos
                moveUp();

                return true;

            case 'ArrowDown':
                // Move shelf para baixo encaixando nos furos
                moveDown();

                return true;

            case 'ArrowLeft': {
                // Move shelf para seção anterior e atualiza seleção
                const moved = moveLeft();

                if (moved) {
                    const updated = editor.findShelfById(shelf.id);

                    if (updated) {
                        selection.selectItem('shelf', shelf.id, updated.shelf, {
                            section: updated.section,
                        });
                    }
                }

                return true;
            }

            case 'ArrowRight': {
                // Move shelf para próxima seção e atualiza seleção
                const moved = moveRight();

                if (moved) {
                    const updated = editor.findShelfById(shelf.id);

                    if (updated) {
                        selection.selectItem('shelf', shelf.id, updated.shelf, {
                            section: updated.section,
                        });
                    }
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Handler de teclado para Section selecionada
     * - Ctrl+ArrowLeft: Move seção para esquerda (diminui ordering)
     * - Ctrl+ArrowRight: Move seção para direita (aumenta ordering)
     */
    function handleSectionKeyboard(event: KeyboardEvent): boolean {
        const selectedItem = selection.selectedItem.value;

        if (!selectedItem || selectedItem.type !== 'section') {
            return false;
        }

        // Requer Ctrl/Cmd para evitar conflitos
        if (!event.ctrlKey && !event.metaKey) {
            // Setas "nuas" com section selecionada: consome sem agir, para a
            // página não rolar no meio da edição (mover exige Ctrl/Cmd).
            if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
                event.preventDefault();

                return true;
            }

            return false;
        }

        const section = selectedItem.item as Section;
        const gondola = editor.currentGondola.value;

        if (!gondola?.sections || !section?.id) {
            return false;
        }

        // Ctrl+D: Duplicar seção
        if (event.key === 'd' || event.key === 'D') {
            event.preventDefault();
            event.stopPropagation();

            // Abre modal de confirmação
            sectionToDuplicate.value = section;
            showDuplicateSectionDialog.value = true;

            return true;
        }

        // Usa composable de ações de seção para lógica compartilhada
        const sectionActions = useSectionActions(() => section);

        // Ctrl+I: Inverter ordem das prateleiras no banco
        if (event.key === 'i' || event.key === 'I') {
            event.preventDefault();
            event.stopPropagation();

            sectionActions.invertShelves();

            return true;
        }

        const handledKeys = ['ArrowLeft', 'ArrowRight'];

        if (!handledKeys.includes(event.key)) {
            return false;
        }

        event.preventDefault();
        event.stopPropagation();

        // Move seção usando composable compartilhado
        if (event.key === 'ArrowLeft') {
            return sectionActions.moveLeft();
        } else if (event.key === 'ArrowRight') {
            return sectionActions.moveRight();
        }

        return false;
    }

    // ==================== HELPER FUNCTIONS ====================

    /**
     * Procura o furo livre mais próximo para a cópia da prateleira: primeiro
     * abaixo da original (comportamento esperado), e — quando não há espaço
     * abaixo — acima.
     *
     * Antes a posição era calculada às cegas (`posição + altura + espaçamento`)
     * e comparada com um limite ainda mais estreito que o do movimento manual.
     * Na prateleira do último furo (a mais baixa) o resultado sempre estourava o
     * limite e o Ctrl+D não fazia nada, em silêncio. Agora o alvo é sempre um
     * furo real, e só desistimos quando não existe nenhum livre.
     */
    function findPositionForShelfCopy(
        section: Section,
        originalShelf: Shelf,
    ): number | null {
        const shelfHeight = originalShelf.shelf_height || 0;
        const sectionCamel = toCamelCase(section);
        const baseHeight =
            sectionCamel.baseHeight ?? DEFAULT_SECTION_FIELDS.baseHeight;

        // Mesmo limite do movimento manual (moveDown), não um mais estreito.
        const maxPosition = section.height - baseHeight - shelfHeight;

        const occupied = (section.shelves ?? [])
            .filter((s: Shelf) => !s.deleted_at)
            .map((s: Shelf) => s.shelf_position);

        // Um furo só serve se a cópia não invadir nenhuma prateleira existente.
        const isFree = (position: number): boolean =>
            occupied.every(
                (taken) =>
                    Math.abs(taken - position) >= Math.max(shelfHeight, 1) - 0.01,
            );

        const candidates = calculateHolePositions(section)
            .map((hole) => baseHeight + hole)
            .filter(
                (position) =>
                    position >= 0 && position <= maxPosition && isFree(position),
            );

        const current = originalShelf.shelf_position;

        // Posição cresce para baixo (0 = topo): furo livre logo abaixo primeiro.
        const below = candidates
            .filter((position) => position > current + 0.01)
            .sort((a, b) => a - b)[0];

        if (below !== undefined) {
            return below;
        }

        const above = candidates
            .filter((position) => position < current - 0.01)
            .sort((a, b) => b - a)[0];

        return above ?? null;
    }

    /**
     * Duplica uma shelf completa (com todos os segments, layers e products),
     * encaixando a cópia no furo livre mais próximo (abaixo, ou acima quando a
     * original já está no último furo).
     */
    function duplicateShelf(shelf: Shelf, section: Section): void {
        // Busca a shelf atual com todos os dados atualizados
        const currentShelfData = editor.findShelfById(shelf.id);

        if (!currentShelfData?.shelf) {
return;
}

        const originalShelf = currentShelfData.shelf;
        const currentSection = currentShelfData.section ?? section;
        const newPosition = findPositionForShelfCopy(
            currentSection,
            originalShelf,
        );

        if (newPosition === null) {
            toast.error(t('plannerate.editor.shelf_duplicate_no_free_hole'));

            return;
        }

        const shelfId = ulid();
        // Cria estrutura da nova shelf (sem ID - será gerado pelo backend)
        const newShelf = {
            id: shelfId,
            // Gera código no mesmo padrão do backend: SHELF-{timestamp_segundos}
            code: `SHELF-${Math.floor(Date.now() / 1000)}`,
            section_id: currentSection.id,
            shelf_position: newPosition,
            shelf_height: originalShelf.shelf_height,
            shelf_width: originalShelf.shelf_width,
            shelf_depth: originalShelf.shelf_depth,
            product_type: originalShelf.product_type,
            shelf_thickness: originalShelf.shelf_thickness,
            shelf_color: originalShelf.shelf_color,
            updated_at: new Date(),
            created_at: new Date(),
            segments:
                originalShelf.segments?.map((segment: Segment) => {
                    const segmentId = ulid();

                    return {
                        ...segment,
                        id: segmentId,
                        shelf_id: shelfId,
                        updated_at: new Date(),
                        created_at: new Date(),
                        layer: {
                            ...segment.layer,
                            id: ulid(),
                            segment_id: segmentId,
                            updated_at: new Date(),
                            created_at: new Date(),
                        } as Layer,
                    } as Segment;
                }) || [],
        };
        // Adiciona a nova shelf via editor
        editor.addShelf(currentSection.id, newShelf);
    }

    /**
     * Duplica uma seção completa ou apenas a estrutura (sem produtos)
     * @param section - Seção a ser duplicada
     * @param duplicateType - 'structure' para apenas estrutura, 'complete' para duplicação completa
     */
    function duplicateSection(
        section: Section,
        duplicateType: 'structure' | 'complete',
    ): void {
        if (!section?.id) {
return;
}

        const gondola = editor.currentGondola.value;

        if (!gondola?.sections) {
return;
}

        // Busca a seção atual com todos os dados atualizados
        const currentSection = editor.findSectionById(section.id);

        if (!currentSection) {
return;
}

        // Calcula o ordering: coloca logo após a seção original
        const sections = gondola.sections.filter((s: Section) => !s.deleted_at);
        const sortedSections = [...sections].sort(
            (a: Section, b: Section) => (a.ordering || 0) - (b.ordering || 0),
        );

        // Encontra o índice da seção original
        const currentIndex = sortedSections.findIndex(
            (s: Section) => s.id === section.id,
        );
        const currentOrdering = currentSection.ordering || 0;

        // Se for a última seção, coloca no final. Caso contrário, coloca logo após
        let newOrdering: number;

        if (currentIndex === sortedSections.length - 1) {
            // É a última seção, coloca no final
            const maxOrdering = sections.reduce((max: number, s: Section) => {
                return Math.max(max, s.ordering || 0);
            }, 0);
            newOrdering = maxOrdering + 1;
        } else {
            // Coloca logo após a seção original
            const nextSection = sortedSections[currentIndex + 1];
            const nextOrdering = nextSection.ordering || 0;

            // Se há espaço entre a seção atual e a próxima, usa o espaço
            if (nextOrdering > currentOrdering + 1) {
                // Há espaço, usa ordering entre as duas
                newOrdering = currentOrdering + 1;
            } else {
                // Não há espaço, incrementa todas as seções seguintes
                newOrdering = currentOrdering + 1;
                
                // Incrementa ordering de todas as seções que vêm depois
                for (let i = currentIndex + 1; i < sortedSections.length; i++) {
                    const sectionToUpdate = sortedSections[i];
                    editor.updateSection(sectionToUpdate.id, {
                        ordering: (sectionToUpdate.ordering || 0) + 1,
                    });
                }
            }
        }

        const newSectionId = ulid();

        // Cria a nova seção com os dados básicos (incluindo todas as propriedades necessárias)
        const newSection = {
            ...currentSection,
            id: newSectionId,
            name: `${currentSection.name} (Cópia)`,
            ordering: newOrdering,
            _is_new: true,
            shelves: [],
        } as Partial<Section>;

        // Duplica as prateleiras
        if (currentSection.shelves && currentSection.shelves.length > 0) {
            const activeShelves = currentSection.shelves.filter(
                (s: Shelf) => !s.deleted_at,
            );

            newSection.shelves = activeShelves.map((shelf: Shelf) => {
                const newShelfId = ulid();
                const newShelf = {
                    ...shelf,
                    id: newShelfId,
                    section_id: newSectionId,
                    segments: [],
                    _is_new: true,
                } as Partial<Shelf>;

                // Se for duplicação completa, duplica também os segments, layers e products
                if (duplicateType === 'complete' && shelf.segments) {
                    const activeSegments = shelf.segments.filter(
                        (seg: Segment) => !seg.deleted_at,
                    );

                    newShelf.segments = activeSegments.map(
                        (segment: Segment) => {
                            const newSegmentId = ulid();
                            const newSegment = {
                                ...segment,
                                id: newSegmentId,
                                shelf_id: newShelfId,
                                _is_new: true,
                            } as Partial<Segment>;

                            // Duplica a layer se existir
                            if (segment.layer && !segment.layer.deleted_at) {
                                const newLayerId = ulid();
                                newSegment.layer = {
                                    ...segment.layer,
                                    id: newLayerId,
                                    segment_id: newSegmentId,
                                    _is_new: true,
                                } as Layer;
                            }

                            return newSegment as Segment;
                        },
                    );
                }

                return newShelf as Shelf;
            });
        }

        // Adiciona a nova seção via editor (sem as prateleiras primeiro)
        const sectionWithoutShelves = { ...newSection, shelves: [] };
        const addedSection = editor.addSection(sectionWithoutShelves);

        // Adiciona as prateleiras separadamente para que sejam registradas como mudanças
        if (
            addedSection &&
            newSection.shelves &&
            newSection.shelves.length > 0
        ) {
            // Garante que temos o array de prateleiras antes de iterar
            const shelvesToAdd = [...newSection.shelves];
            shelvesToAdd.forEach((shelf: Shelf) => {
                editor.addShelf(newSectionId, shelf);
            });
        }

        // Reordena seções no front após duplicar
        editor.reorderSectionsByOrdering();

        // Seleciona a nova seção após ser adicionada
        if (addedSection) {
            const newSectionData = editor.findSectionById(newSectionId);

            if (newSectionData) {
                selection.selectItem('section', newSectionId, newSectionData);
            }
        }
    }

    /**
     * Verifica se deve mostrar modal de confirmação baseado no localStorage
     * Usa função helper do utilitário compartilhado
     */
    function shouldShowDeleteConfirmLocal(itemType: string): boolean {
        return shouldShowDeleteConfirm(itemType);
    }

    /**
     * Handler de confirmação da modal de duplicação
     */
    function handleDuplicateSectionConfirm(
        duplicateType: 'structure' | 'complete',
    ): void {
        if (!sectionToDuplicate.value) {
return;
}

        duplicateSection(sectionToDuplicate.value, duplicateType);

        // Fecha a modal e limpa a referência
        showDuplicateSectionDialog.value = false;
        sectionToDuplicate.value = null;
    }

    /**
     * Handler de confirmação da modal de exclusão
     */
    function handleDeleteConfirm(): void {
        if (!itemToDelete.value) {
return;
}

        // Garante que o item está selecionado antes de deletar
        const { type, item } = itemToDelete.value;
        selection.selectItem(type, (item as any).id, item);

        // Pequeno delay para garantir que a seleção foi aplicada
        setTimeout(() => {
            // Executa a exclusão
            selection.deleteSelected();

            // Fecha a modal e limpa a referência
            showDeleteConfirmDialog.value = false;
            itemToDelete.value = null;
        }, 0);
    }

    // ==================== MAIN KEYBOARD HANDLER ====================

    /**
     * Handler principal - delega para handlers específicos ou globais
     */
    function handleKeyboard(event: KeyboardEvent) {
        // Ignora eventos em inputs/textareas
        const target = event.target as HTMLElement;
        const isInputFocused =
            target.tagName === 'INPUT' ||
            target.tagName === 'TEXTAREA' ||
            target.isContentEditable;

        if (isInputFocused) {
return;
}

        const selectedItem = selection.selectedItem.value;

        // PRIORIDADE 1: Handlers específicos por tipo
        if (selectedItem) {
            let handled = false;

            switch (selectedItem.type) {
                case 'layer':
                case 'product':
                case 'segment':
                    handled = handleLayerKeyboard(event);
                    break;
                case 'shelf':
                    handled = handleShelfKeyboard(event);
                    break;
                case 'section':
                    handled = handleSectionKeyboard(event);
                    break;
            }

            if (handled) {
return;
}
        }

        // PRIORIDADE 2: Handlers globais
        handleGlobalShortcuts(event);
    }

    /**
     * Processa atalhos globais (sempre disponíveis)
     */
    function handleGlobalShortcuts(event: KeyboardEvent) {
        const isCtrl = event.ctrlKey || event.metaKey;

        // Escape: limpa a seleção do editor.
        // Dialogs/modais abertos têm prioridade (o Escape deles fecha o dialog
        // sem mexer na seleção). O GenerationOverlay tem listener próprio de
        // Escape (minimizar) que coexiste sem conflito funcional.
        if (event.key === 'Escape') {
            if (document.querySelector('[role="dialog"][data-state="open"]')) {
                return;
            }

            selection.clearSelection();

            return;
        }

        // Delete: Remove item selecionado.
        // Backspace NÃO deleta (fácil de apertar sem querer em notebooks).
        if (event.key === 'Delete') {
            event.preventDefault();

            const selectedItem = selection.selectedItem.value;

            if (!selectedItem) {
return;
}

            // Produto do painel esquerdo não é deletável pelo editor —
            // avisa em vez do antigo no-op silencioso.
            if (selectedItem.type === 'product') {
                toast.info(t('plannerate.editor.product_delete_not_supported'));

                return;
            }

            // Só mostra modal para section, shelf e layer
            const supportedTypes = ['section', 'shelf', 'layer'];

            if (!supportedTypes.includes(selectedItem.type)) {
                // Para outros tipos (segment), deleta diretamente
                selection.deleteSelected();

                return;
            }

            // Verifica se deve mostrar modal de confirmação
            const shouldShowConfirm = shouldShowDeleteConfirmLocal(
                selectedItem.type,
            );

            if (shouldShowConfirm) {
                // Abre modal de confirmação
                itemToDelete.value = {
                    type: selectedItem.type as 'section' | 'shelf' | 'layer',
                    item: selectedItem.item as Section | Shelf | Layer,
                };
                showDeleteConfirmDialog.value = true;
            } else {
                // Deleta diretamente sem confirmação
                selection.deleteSelected();
            }

            return;
        }

        // Ctrl+Z: Desfazer
        if (isCtrl && event.key === 'z' && !event.shiftKey) {
            event.preventDefault();
            editor.undo();

            return;
        }

        // Ctrl+Shift+Z: Refazer
        if (isCtrl && event.key === 'z' && event.shiftKey) {
            event.preventDefault();
            editor.redo();

            return;
        }

        // Ctrl+Y: Refazer (atalho alternativo)
        if (isCtrl && event.key === 'y') {
            event.preventDefault();
            editor.redo();

            return;
        }

        // Ctrl+S: Salvar manualmente
        if (isCtrl && event.key === 's') {
            event.preventDefault();

            if (editor.hasChanges?.value) {
                editor.save();
            }

            return;
        }

        // Ctrl+C: copia o item selecionado. Segmento → clipboard interno de
        // segmento; Seção (módulo) → clipboard persistente de módulo.
        if (isCtrl && event.key === 'c') {
            // Cópia de texto real tem prioridade (não intercepta)
            if (window.getSelection()?.toString()) {
                return;
            }

            const selected = selection.selectedItem.value;

            if (selected?.type === 'segment') {
                copiedSegmentId.value = selected.id;
                toast.info(t('plannerate.editor.clipboard.segment_copied'));
                event.preventDefault();
            } else if (selected?.type === 'section') {
                moduleClipboard.copyModule(selected.item as Section, 'copy');
                event.preventDefault();
            }

            return;
        }

        // Ctrl+X: recorta (move) o módulo selecionado para o clipboard persistente
        if (isCtrl && event.key === 'x') {
            if (window.getSelection()?.toString()) {
                return;
            }

            const selected = selection.selectedItem.value;

            if (selected?.type === 'section') {
                moduleClipboard.copyModule(selected.item as Section, 'cut');
                event.preventDefault();
            }

            return;
        }

        // Ctrl+V: precedência documentada — cola SEGMENTO quando há segmento
        // copiado E um alvo de prateleira (shelf/segment selecionado); senão,
        // cola MÓDULO do clipboard persistente na gôndola atual.
        if (isCtrl && event.key === 'v') {
            const selected = selection.selectedItem.value;

            if (copiedSegmentId.value) {
                let targetShelfId: string | null = null;

                if (selected?.type === 'shelf') {
                    targetShelfId = selected.id;
                } else if (selected?.type === 'segment') {
                    targetShelfId = (selected.item as Segment).shelf_id ?? null;
                }

                if (targetShelfId) {
                    event.preventDefault();

                    // copySegmentToShelf valida largura e mostra toast de "não cabe";
                    // origem deletada retorna false com warn — converte em toast.
                    if (!editor.copySegmentToShelf(copiedSegmentId.value, targetShelfId)) {
                        toast.error(t('plannerate.editor.clipboard.paste_failed'));
                    }

                    return;
                }
            }

            // Colar módulo (clipboard cross-gôndola) na gôndola aberta
            if (moduleClipboard.clipboard.value) {
                event.preventDefault();
                moduleClipboard.pasteIntoCurrentGondola();

                return;
            }

            // Havia segmento copiado, mas sem prateleira-alvo: orienta o usuário
            if (copiedSegmentId.value) {
                toast.info(t('plannerate.editor.clipboard.paste_select_shelf'));
            }

            return;
        }
    }

    // ==================== LIFECYCLE HOOKS ====================

    onMounted(() => {
        // Incrementa contador de referências
        keyboardListenerRefCount++;

        // Registra o listener apenas se ainda não foi registrado
        if (!keyboardHandler) {
            keyboardHandler = handleKeyboard;
            window.addEventListener('keydown', keyboardHandler);
        }
    });

    onBeforeUnmount(() => {
        // Decrementa contador de referências
        keyboardListenerRefCount--;

        // Remove o listener apenas quando não há mais nenhuma instância usando
        if (keyboardListenerRefCount === 0 && keyboardHandler) {
            window.removeEventListener('keydown', keyboardHandler);
            keyboardHandler = null;
        }
    });

    // ==================== RETURN ====================

    return {
        handleKeyboard,
        // Modal de duplicação de seção
        showDuplicateSectionDialog,
        sectionToDuplicate,
        handleDuplicateSectionConfirm,
        // Modal de confirmação de exclusão
        showDeleteConfirmDialog,
        itemToDelete,
        handleDeleteConfirm,
        // Display de entrada de números
        numberInputDisplay,
    };
}
