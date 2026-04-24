import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
import { Layer, Product, Section, Segment, Shelf } from '@/types/planogram';
import { computed, readonly, ref } from 'vue';

interface SelectedItem {
    id: string;
    type: 'section' | 'shelf' | 'segment' | 'layer' | 'product';
    item: Section | Shelf | Segment | Layer | Product;
    context?: Record<string, any>;
}

// ==================== ESTADO REATIVO GLOBAL ====================
// Movido para FORA da função para ser compartilhado entre componentes

// Seleção única (compatibilidade com código existente)
const selectedType = ref<
    'section' | 'shelf' | 'segment' | 'layer' | 'product' | null
>(null);
const selectedId = ref<string | null>(null);
const selectedItem = ref<SelectedItem | null>(null);

// Seleção múltipla (novo sistema)
const selectedItems = ref<SelectedItem[]>([]);
const multiSelectEnabled = ref<boolean>(true); // Configuração para habilitar/desabilitar seleção múltipla

export function usePlanogramSelection() {
    // ==================== MÉTODOS DE SELEÇÃO ====================
    const editor = usePlanogramEditor();

    /**
     * Seleciona um item no editor (seleção única)
     */
    function selectItem(
        type: 'section' | 'shelf' | 'segment' | 'layer' | 'product',
        id: string,
        item: Section | Shelf | Segment | Layer | Product,
        context?: Record<string, any>,
    ) {
        selectedType.value = type;
        selectedId.value = id;
        selectedItem.value = {
            id,
            type,
            item,
            context,
        };

        // Limpa seleção múltipla ao selecionar único item
        selectedItems.value = [];
    }

    /**
     * Adiciona ou remove item da seleção múltipla (Ctrl+Click)
     */
    function toggleSelection(
        type: 'section' | 'shelf' | 'segment' | 'layer' | 'product',
        id: string,
        item: Section | Shelf | Segment | Layer | Product,
        context?: Record<string, any>,
    ) {
        if (!multiSelectEnabled.value) {
            // Se múltipla seleção desabilitada, comporta como seleção única
            selectItem(type, id, item, context);
            return;
        }

        const existingIndex = selectedItems.value.findIndex(
            (selected) => selected.id === id && selected.type === type,
        );

        if (existingIndex >= 0) {
            // Remove se já está selecionado
            selectedItems.value.splice(existingIndex, 1);
        } else {
            // Adiciona à seleção múltipla
            selectedItems.value.push({
                id,
                type,
                item,
                context,
            });
        }

        // Atualiza seleção única para o último item selecionado
        if (selectedItems.value.length > 0) {
            const lastItem =
                selectedItems.value[selectedItems.value.length - 1];
            selectedType.value = lastItem.type;
            selectedId.value = lastItem.id;
            selectedItem.value = lastItem;
        } else {
            clearSelection();
        }
    }

    /**
     * Adiciona item à seleção múltipla sem remover outros
     */
    function addToSelection(
        type: 'section' | 'shelf' | 'segment' | 'layer' | 'product',
        id: string,
        item: Section | Shelf | Segment | Layer | Product,
        context?: Record<string, any>,
    ) {
        if (!multiSelectEnabled.value) {
            selectItem(type, id, item, context);
            return;
        }

        // Verifica se já está selecionado
        const exists = selectedItems.value.some(
            (selected) => selected.id === id && selected.type === type,
        );

        if (!exists) {
            selectedItems.value.push({
                id,
                type,
                item,
                context,
            });

            // Atualiza seleção única para o último item
            selectedType.value = type;
            selectedId.value = id;
            selectedItem.value = { id, type, item, context };
        }
    }

    /**
     * Verifica se um item está selecionado (única ou múltipla)
     */
    function isSelected(type: string, id: string): boolean {
        // Verifica seleção única
        if (selectedType.value === type && selectedId.value === id) {
            return true;
        }

        // Verifica seleção múltipla
        return selectedItems.value.some(
            (item) => item.type === type && item.id === id,
        );
    }

    /**
     * Verifica se há múltiplos itens selecionados
     */
    function hasMultipleSelections(): boolean {
        return selectedItems.value.length > 1;
    }

    /**
     * Retorna quantidade de itens selecionados
     */
    function getSelectionCount(): number {
        return selectedItems.value.length > 0
            ? selectedItems.value.length
            : selectedItem.value
              ? 1
              : 0;
    }

    /**
     * Verifica se uma secção está selecionada
     */
    function isSectionSelected(section: Section): boolean {
        return isSelected('section', section.id);
    }

    /**
     * Verifica se uma prateleira está selecionada
     */
    function isShelfSelected(shelf: Shelf): boolean {
        return isSelected('shelf', shelf.id);
    }

    /**
     * Verifica se um segmento está selecionado
     */
    function isSegmentSelected(segment: Segment): boolean {
        return isSelected('segment', segment.id);
    }

    /**
     * Verifica se uma layer está selecionada
     */
    function isLayerSelected(layer: Layer): boolean {
        return isSelected('layer', layer.id);
    }

    /**
     * Limpa toda seleção (única e múltipla)
     */
    function clearSelection() {
        selectedType.value = null;
        selectedId.value = null;
        selectedItem.value = null;
        selectedItems.value = [];
    }

    /**
     * Habilita/desabilita seleção múltipla
     */
    function setMultiSelectEnabled(enabled: boolean) {
        multiSelectEnabled.value = enabled;
        if (!enabled) {
            // Limpa seleções múltiplas ao desabilitar
            selectedItems.value = [];
        }
    }

    const getSelectedItem = computed(() => {
        return selectedItem.value;
    });
    /**
     * Deleta o item atualmente selecionado (atalho de teclado)
     */
    async function deleteSelected(): Promise<boolean> {
        const { type } = selectedItem.value as SelectedItem;

        if (!type) return false;
        // const strategy =  deletionStrategies[type as keyof typeof deletionStrategies];
        switch (type) {
            case 'shelf':
                return await deleteShelf(
                    selectedItem.value?.item as Shelf 
                );
            case 'layer':
                return await deleteLayer(
                    selectedItem.value?.item as Layer 
                );
            case 'segment':
                return await deleteSegment(
                    selectedItem.value?.item as Segment 
                );
            case 'section':
                return await deleteSection(selectedItem.value?.item as Section);
        }
        return false;
    }

    async function deleteShelf(
        shelf: Shelf,
    ): Promise<boolean> {
        if (!shelf?.id) return false;

        // Soft delete - marca como deletado
        editor.updateShelf(shelf.id, {
            deleted_at: new Date().toISOString(),
        });

        // Limpa seleção após deletar
        clearSelection();

        return true;
    }

    async function deleteLayer(
        layer: Layer,
    ): Promise<boolean> {
        if (!layer?.id) return false;

        // Soft delete - marca como deletado
        editor.updateLayer(layer.id, {
            deleted_at: new Date().toISOString(),
        });

        // Limpa seleção após deletar
        clearSelection();

        return true;
    }

    async function deleteSegment(
        segment: Segment,
    ): Promise<boolean> {
        if (!segment?.id) return false;

        // Soft delete - marca como deletado
        editor.updateSegment(segment.id, {
            deleted_at: new Date().toISOString(),
        });

        // Limpa seleção após deletar
        clearSelection();

        return true;
    }

    async function deleteSection(section: Section): Promise<boolean> {
        if (!section?.id) return false;

        // Soft delete - marca como deletado
        editor.updateSection(section.id, {
            deleted_at: new Date().toISOString(),
        });

        // Reordena seções no front após deletar
        editor.reorderSectionsByOrdering();

        // Limpa seleção após deletar
        clearSelection();

        return true;
    }

    // ==================== HELPERS PARA SELEÇÃO MÚLTIPLA ====================

    /**
     * Retorna todas as shelves selecionadas
     */
    function getSelectedShelves(): Shelf[] {
        return selectedItems.value
            .filter((item) => item.type === 'shelf')
            .map((item) => item.item as Shelf);
    }

    /**
     * Retorna todos os produtos selecionados
     */
    function getSelectedProducts(): Product[] {
        return selectedItems.value
            .filter((item) => item.type === 'product')
            .map((item) => item.item as Product);
    }

    /**
     * Retorna todas as seções selecionadas
     */
    function getSelectedSections(): Section[] {
        return selectedItems.value
            .filter((item) => item.type === 'section')
            .map((item) => item.item as Section);
    }

    /**
     * Retorna todos os segmentos selecionados
     */
    function getSelectedSegments(): Segment[] {
        return selectedItems.value
            .filter((item) => item.type === 'segment')
            .map((item) => item.item as Segment);
    }

    /**
     * Retorna todas as layers selecionadas
     */
    function getSelectedLayers(): Layer[] {
        return selectedItems.value
            .filter((item) => item.type === 'layer')
            .map((item) => item.item as Layer);
    }

    /**
     * Seleciona shelf e seus produtos
     */
    function selectShelfWithProducts(shelf: Shelf, section: Section) {
        if (!multiSelectEnabled.value) {
            selectItem('shelf', shelf.id, shelf, { section });
            return;
        }

        // Limpa seleção anterior
        selectedItems.value = [];

        // Adiciona a shelf
        addToSelection('shelf', shelf.id, shelf, { section });

        // Adiciona todos os produtos da shelf
        shelf.segments?.forEach((segment: Segment) => {
            const layer = segment.layer;
            if (layer?.product) {
                addToSelection('product', layer.product.id, layer.product, {
                    layer,
                    segment,
                    shelf,
                    section,
                });
            }
        });
    }

    return {
        // Estado (readonly para prevenir mutações diretas)
        selectedType: readonly(selectedType),
        selectedId: readonly(selectedId),
        selectedItem: readonly(selectedItem),
        selectedItems: readonly(selectedItems),
        multiSelectEnabled: readonly(multiSelectEnabled),

        // Métodos de seleção única
        selectItem,
        clearSelection,
        isSelected,
        isSectionSelected,
        isShelfSelected,
        isSegmentSelected,
        isLayerSelected,
        getSelectedItem,
        deleteSelected,

        // Métodos de seleção múltipla
        toggleSelection,
        addToSelection,
        hasMultipleSelections,
        getSelectionCount,
        setMultiSelectEnabled,

        // Helpers para seleção múltipla
        getSelectedShelves,
        getSelectedProducts,
        getSelectedSections,
        getSelectedSegments,
        getSelectedLayers,
        selectShelfWithProducts,
    };
}
