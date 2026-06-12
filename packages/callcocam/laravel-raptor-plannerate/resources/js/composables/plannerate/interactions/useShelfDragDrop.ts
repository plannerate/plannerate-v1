import { inject, ref } from 'vue';
import { usePlanogramEditor } from '../core/usePlanogramEditor';
import { draggingSegmentShelfId } from '../core/useGondolaState';
import {
    getMultipleProductsDragData,
    getProductDragData,
    getSegmentDragData,
    hasMultipleProductsData,
    hasProductData,
    hasSegmentData,
} from '../dnd/transfer';
import { useRejectedProductsStore } from './useRejectedProductsStore';

/**
 * Composable para gerenciar drag & drop na shelf (drop target).
 *
 * Aceita: produto único, múltiplos produtos (seleção), e segmentos vindos de
 * OUTRAS prateleiras (move; com Ctrl = copy). Segmentos da mesma prateleira são
 * ignorados aqui — o swap é tratado pelas drop zones do próprio Segment.vue.
 */
export function useShelfDragDrop(shelfId: string) {
    const editor = usePlanogramEditor();
    const rejectedStore = useRejectedProductsStore();
    const isDropTarget = ref(false);

    // Injeta função para remover produto da lista quando usado
    const removeUsedProduct = inject<((productId: string) => void) | undefined>(
        'removeUsedProduct',
    );

    /**
     * Handler para dragover - define o tipo de operação
     */
    const handleDragOver = (event: DragEvent) => {
        event.preventDefault();

        if (!event.dataTransfer) {
            return;
        }

        if (hasProductData(event.dataTransfer) || hasMultipleProductsData(event.dataTransfer)) {
            // Produtos sempre são "copy" (adicionar à shelf)
            event.dataTransfer.dropEffect = 'copy';
            isDropTarget.value = true;

            return;
        }

        if (hasSegmentData(event.dataTransfer)) {
            // Verifica se o segment está na mesma shelf usando o estado global
            if (draggingSegmentShelfId.value === shelfId) {
                // Mesma shelf - não mostra área de drop (deixa as drop zones do Segment funcionarem)
                isDropTarget.value = false;

                return;
            }

            // Segments de outras shelves: copy se Ctrl pressionado, move caso contrário
            const isCopy = event.ctrlKey || event.metaKey;
            event.dataTransfer.dropEffect = isCopy ? 'copy' : 'move';
            isDropTarget.value = true;
        }
    };

    /**
     * Handler para dragleave
     */
    const handleDragLeave = () => {
        isDropTarget.value = false;
    };

    /**
     * Handler para drop - processa produtos e segments
     */
    const handleDrop = async (event: DragEvent) => {
        event.preventDefault();
        isDropTarget.value = false;

        if (!event.dataTransfer) {
            return;
        }

        const multipleProducts = getMultipleProductsDragData(event.dataTransfer);

        if (multipleProducts.length > 0) {
            handleMultipleProductsDrop(multipleProducts);

            return;
        }

        const product = getProductDragData(event.dataTransfer);

        if (product) {
            addProduct(product);

            return;
        }

        const segment = getSegmentDragData(event.dataTransfer);

        if (segment) {
            // Mesma shelf: ignora (deixa as drop zones do Segment funcionarem)
            if (segment.sourceShelfId === shelfId) {
                return;
            }

            handleSegmentDrop(segment.segmentId, segment.isCopy);
        }
    };

    /**
     * Processa drop de múltiplos produtos (apenas publicados entram)
     */
    const handleMultipleProductsDrop = (products: Array<{ id: string; name?: string; status?: string }>) => {
        for (const product of products) {
            if (product.status !== 'published') {
                console.warn(`⚠️ Produto ${product.name} não está publicado, pulando...`);

                continue;
            }

            addProduct(product);
        }
    };

    /**
     * Adiciona um produto à shelf, removendo-o do painel e notificando o
     * módulo de rejeitados (caso tenha vindo de lá).
     */
    const addProduct = (product: { id: string }) => {
        editor.addProductToShelf(
            shelfId,
            product.id,
            product,
            (addedProductId) => {
                removeUsedProduct?.(addedProductId);
                rejectedStore.notifyProductPlaced(addedProductId);
            },
        );
    };

    /**
     * Processa drop de segment vindo de outra prateleira (move ou copy)
     */
    const handleSegmentDrop = (segmentId: string, isCopy: boolean) => {
        const result = isCopy
            ? editor.copySegmentToShelf(segmentId, shelfId)
            : editor.moveSegmentToShelf(segmentId, shelfId);

        if (!result) {
            console.warn(`⚠️ Erro ao ${isCopy ? 'copiar' : 'mover'} segmento`);
        }
    };

    return {
        isDropTarget,
        handleDragOver,
        handleDragLeave,
        handleDrop,
    };
}
